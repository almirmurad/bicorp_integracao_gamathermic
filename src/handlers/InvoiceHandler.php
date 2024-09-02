<?php

namespace src\handlers;

use PDOException;
use src\exceptions\DealNaoEncontradoBDException;
use src\exceptions\EstagiodavendaNaoAlteradoException;
use src\exceptions\FaturamentoNaoCadastradoException;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\NotaFiscalNaoCadastradaException;
use src\exceptions\NotaFiscalNaoCanceladaException;
use src\exceptions\NotaFiscalNaoEncontradaException;
use src\exceptions\PedidoNaoEncontradoOmieException;
use src\exceptions\WebhookReadErrorException;
use src\functions\DiverseFunctions;
use src\models\Deal;
use src\models\Homologacao_invoicing;
use src\models\Manospr_invoicing;
use src\models\Manossc_invoicing;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use stdClass;

class InvoiceHandler
{
    private $current;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;

    public function __construct(PloomesServices $ploomesServices, OmieServices $omieServices, DatabaseServices $databaseServices)
    {
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
        $this->ploomesServices = $ploomesServices;
        $this->omieServices = $omieServices;
        $this->databaseServices = $databaseServices;
    }
    //LÊ O WEBHOOK E COM A NOTA FATURADA
    public function readInvoiceHook($json)
    {   
        //data atual
        $current = $this->current;
        // Array de retorno
        $message = [];
        
        $decoded = json_decode($json, true);//decodifica o json em array
        $invoicing = new stdClass();//monta objeto da nota fiscal
        $invoicing->authorId = $decoded['author']['userId'];//Id de quem faturou
        $invoicing->authorName = $decoded['author']['name'];//nome de quem faturou
        $invoicing->authorEmail = $decoded['author']['email'];//email de quem faturou
        $invoicing->appKey = $decoded['appKey'];//id do app que faturou (base de faturamento)
        $invoicing->etapa = $decoded['event']['etapa']; // etapa do processo 60 = faturado
        $invoicing->etapaDescr = $decoded['event']['etapaDescr']; // descrição da etapa 
        $invoicing->dataFaturado = $decoded['event']['dataFaturado']; // data do faturamento
        $invoicing->horaFaturado = $decoded['event']['horaFaturado']; // hora do faturamento
        $invoicing->idCliente = $decoded['event']['idCliente']; // Id do Cliente Omie
        $invoicing->idPedido = $decoded['event']['idPedido']; // Id do Pedido Omie
        $invoicing->numeroPedido = $decoded['event']['numeroPedido']; // Numero do pedido
        $invoicing->valorPedido = $decoded['event']['valorPedido']; // Valor Faturado

        $omie = new stdClass;//monta um objeto com informações a enviar ao omie
        $omie->appKey = $decoded['appKey'];
        $omie->codCliente = $decoded['event']['idCliente'];
        //verifica se tem informações no array e se a nota está faturada (etapa 60)
        if(empty($decoded) && $decoded['event']['etapa'] != 60){
            throw new WebhookReadErrorException('Não foi possível ler o Webhook ou não existe nota fiscal emitida! - '. $current,1020);
        }      

        //pega a chave secreta para a base de faturamento vinda no faturamento
        switch($decoded['appKey']){
            case 2337978328686:               
                $omie->appSecret = $_ENV['SECRETS_MHL'];
                $invoicing->target = 'MHL';
                break;
                
            case 2335095664902:
                $omie->appSecret = $_ENV['SECRETS_MPR'];
                $invoicing->target = 'MPR';
                break;
                
            case 2597402735928:
                $omie->appSecret = $_ENV['SECRETS_MSC'];
                $invoicing->target = 'MSC';
                break;
            }

        // busca o pedido através id do pedido no omie retorna exceção se não encontra 
        if(!$pedidoOmie = $this->omieServices->consultaPedidoOmie($omie, $decoded['event']['idPedido'])){throw new PedidoNaoEncontradoOmieException('Pedido '.$decoded['event']['idPedido'].' não encontrado no Omie ERP',1023);}
          
        //verifica se existe o codigo de integração no pedido do  omie que é o OrderId para buscar o Deal no banco ou retorna nulo
        $idPedidoIntegracao = (isset($pedidoOmie['pedido_venda_produto']['cabecalho']['codigo_pedido_integracao']))? $pedidoOmie['pedido_venda_produto']['cabecalho']['codigo_pedido_integracao'] : null;
        //$idPedidoIntegracao=402303406;
        // Busca o Deal salvo no banco com o número do pedido de integração para pegar os dados e montar o cabeçalho da mensagem, caso não encontre retorna nulo e segue  
        if(isset($idPedidoIntegracao)){
            $deal = $this->databaseServices->getDealByLastOrderId($idPedidoIntegracao); 
        }
            
        //busca o cnpj do cliente para consultar o contact id no ploomes
        $cnpjClient = $this->omieServices->clienteCnpjOmie($omie);

        try{     
                   
            //consulta a nota fiscal no omie para retornar o numero da nota.            
            ($nfe = $this->omieServices->consultaNotaOmie($omie, $decoded['event']['idPedido']))?? throw new NotaFiscalNaoEncontradaException('Nota fiscal não encontrada para o pedido: '.$decoded['event']['idPedido'], 1022);  
            $invoicing->nNF =intval($nfe);

            try{
                //salva o faturamento no banco MHL
                $idInvoicing = $this->databaseServices->saveInvoicing($invoicing); 
                switch($invoicing->target){
                    case 'MHL':
                        $target = 'Homologacao';
                        break;
                    case 'MPR':
                        $target = 'Manos-PR';
                        break;
                    case 'MSC':
                        $target = 'Manso-SC';
                        break;
                }
                $message['saveInvoicing'] = 'Novo faturamento armazenado na base de '.$target.' id: '.$idInvoicing;
                
            }catch(PDOException $e){          
                throw new FaturamentoNaoCadastradoException('NOTA FISCAL NÚMERO '.intval($nfe).' JÁ FOI CADASTRADA NA BASE DE DADOS E NÃO PODE SER REPITIDA!'.$e->getMessage(),1021);
            }

            
        }catch(NotaFiscalNaoEncontradaException $e){
            echo $e->getMessage();
        }

        if(!empty($deal)){
            $cabecalho = [
                'contactId' => $deal['contact_id'],
                'dealId'=> $deal['deal_id'],
            ];
            $frase = 'Interação de nota fiscal emitida adicionada no card '.$deal['deal_Id'] .' em: '.$current;
            $stage = ['StageId'=> 40042597];
        }elseif(empty($deal) && !empty($cnpjClient)){
            //busca o contact_id artravés do cnpj do cliente do omie
            $contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient);

            $cabecalho = [
                'contactId' =>$contactId,
                'dealId'=> null,
            ];
            $frase = 'Interação de nota fiscal adicionada no cliente '. $contactId .' em: '.$current;

        }
        else{

            //RETORNA excessão caso não tenha o $deal
            throw new DealNaoEncontradoBDException('Dados do pedido não encontrado na integração PLOOMES CRM X OMIE ERP. <br> A origem do pedido pode não ter sido o Ploomes CRM. Não foi possível enviar a mensagem ao Ploomes<br>',1024);
        }
        //consegui recuperar o Deal com o numero do pedido de integração então grava os dados em $infoDeal
        
        //monta a mensagem para retornar ao ploomes
        $msg = [
            'ContactId'=> $cabecalho['contactId'],
            'DealId'=> $cabecalho['dealId'],
            'TypeId'=> 1,
            'Title'=> 'Nota Fiscal emitida',
            'Content'=> 'Nota Fiscal ('. intval($nfe).') emitida no Omie ERP.',
        ];
        //Cria interação no card específico 
        ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))? $message['addInteraction'] = $frase : throw new InteracaoNaoAdicionadaException('Não foi possível adicionar a interação de nota fiscal emitida no card, possívelmente a venda foi criada direto no omie - '.$current,1025);
        //muda a etapa da venda específica para NF-Emitida stage Id 40042597

        if(isset($stage)){
            ($this->ploomesServices->alterStageOrder(json_encode($stage), $idPedidoIntegracao))? $message['alterStage'] = 'Estágio da venda alterado com sucesso': throw new EstagiodavendaNaoAlteradoException('Não foi possível alterar o estágio da venda. Possivelmente a venda foi criada direto no Omie',1026);
        }
        
        
        return $message;
    }

    //RECEBE O WEBHOOK DE NOTA CANCELADA, CANCELA NO BANCO E ENVIA INTERAÇÃO NO PLOOMES CRM
    public function isDeletedInvoice($json)
    {
        $message = [];
        $current = date('d/m/Y H:i:s');
        $decoded = json_decode($json, true);
        $omie = new stdClass;//monta um objeto com informações a enviar ao omie
        $omie->appKey = $decoded['appKey'];
        //$omie->codCliente = $decoded['event']['idCliente'];


        if( $decoded['topic'] !== "NFe.NotaCancelada" && $decoded['event']['acao'] !== 'cancelada'){
            throw new WebhookReadErrorException('Não foi possível ler o Webhook ou não existe nota fiscal cancelada!',1020);
        }
            
            //VERIFICA PARA QUAL BASE DE NOTAS FISCAIS SERÁ EDITADA A NOTA
            switch($decoded['appKey'])
            {
                case 2337978328686: //MHL    
                    $omie->target = 'MHL';
                    $target = 'Manos Homologação';
                    break;
                    
                case 2335095664902: // MPR
                    $omie->target = 'MPR'; 
                    $target = 'Manos-PR';
                    break;
                    
                case 2597402735928: // MSC
                    $omie->target = 'MSC';                 
                    $target = 'Manos-SC';
                    break;           
            }

            try{
                $id = $this->databaseServices->isIssetInvoice($omie, $decoded['event']['id_pedido']);
                if(is_string($id)){
                    throw new NotaFiscalNaoCadastradaException('Erro ao consultar a base de dados de '.$target.'. Erro: '.$id. 'em '.$current, 1030);
                    }elseif(empty($id)){
                    throw new NotaFiscalNaoCadastradaException('Nota fiscal não cadastrada na base de dados de notas de '.$target.', ou já foi cancelada em '.$current, 1030);
                }else{$message['invoice']['issetInvoice'] = 'Nota fiscal do pedido '. $decoded['event']['id_pedido'] .'encontrada na base de '.$target.' em '.$current;
                }

            }catch(NotaFiscalNaoCadastradaException $e){
                //$message['invoice']['error']['notRegistered'] =;
                throw new NotaFiscalNaoCadastradaException( $e->getMessage());
            }
                    
            //altera a nota fiscal no banco para cancelada
            try{
                //Altera a nota para cancelada no banco MHL
                $altera = $this->databaseServices->alterInvoice($omie, $decoded['event']['id_pedido']);

                if(is_string($altera)){
                    throw new NotaFiscalNaoCanceladaException('Erro ao consultar a base de dados de '.$target.'. Erro: '.$altera. 'em '.$current, 1030);                     
                }

                $message['invoice']['iscanceled'] = 'Nota fiscal do pedido '. $decoded['event']['id_pedido'] .'cancelada com sucesso em '.$current;
                
            }catch(NotaFiscalNaoCanceladaException $e){          
                throw new NotaFiscalNaoCanceladaException($e->getMessage(), 1031);
            }
        
            //busca o contact id do cliente no P`loomes CRM através do cnpj do cliente no Omie ERP
            $contactId = $this->ploomesServices->consultaClientePloomesCnpj($decoded['event']['empresa_cnpj']);
            //monta a mensadem para atualizar o card do ploomes
            $msg=[
                'ContactId' => $contactId,
                'Content' => 'Nota fiscal '.intval($decoded['event']['numero_nf']).' cancelada no Omie ERP em: '.$current,
                'Title' => 'Nota Fiscal Cancelada no Omie ERP'
            ];

            //cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['interactionMessage'] = 'Integração de cancelamento de nota fiscal concluída com sucesso!<br> Nota Fiscal: '.intval($decoded['event']['numero_nf']).' foi cancelada no Omie ERP e interação criada no cliente id: '.$contactId.' em: '.$current : throw new InteracaoNaoAdicionadaException('Não foi possível gravar a mensagem de nota cancelada no Ploomes CRM',1032);

            return $message;

    }

}