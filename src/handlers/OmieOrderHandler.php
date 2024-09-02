<?php

namespace src\handlers;

use PDOException;
use src\exceptions\ContactIdInexistentePloomesCRM;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\OrderControllerException;
use src\exceptions\PedidoCanceladoException;
use src\exceptions\PedidoDuplicadoException;
use src\exceptions\PedidoInexistenteException;
use src\exceptions\PedidoNaoExcluidoException;
use src\exceptions\PedidoOutraIntegracaoException;
use src\exceptions\WebhookReadErrorException;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use stdClass;

class OmieOrderHandler
{
    private $current;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;

    public function __construct(PloomesServices $ploomesServices, OmieServices $omieServices, DatabaseServices $databaseServices) {
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
        $this->ploomesServices = $ploomesServices;
        $this->omieServices = $omieServices;
        $this->databaseServices = $databaseServices;
    }
   

    public function newOmieOrder(string $json):array
    {
        $current = $this->current;
        $message = [];
        
        //decodifica o json de pedidos do webhook
        $decoded = json_decode($json, true);

        if($decoded['topic'] === "VendaProduto.Incluida" && $decoded['event']['etapa'] == "10" && $decoded['event']['usuarioInclusao'] !== 'WEBSERVICE'){

            switch($decoded['appKey']){

                case 2337978328686:               
                    // Monta o objeto de Order Homologação com os dados do webhook
                    $order = new stdClass();
                    $order->target = 'MHL';
                    $order->appSecret = $_ENV['SECRETS_MHL'];
                    $order->idOmie = $decoded['event']['idPedido'];
                    $order->codCliente = $decoded['event']['idCliente'];
                    //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                    $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
                    $order->numPedidoOmie = intval($decoded['event']['numeroPedido']);
                    //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                    $order->ncc = $decoded['event']['idContaCorrente'];
                    $order->codVendedorOmie = $decoded['author']['userId'];
                    //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
                    $order->appKey = $decoded['appKey'];  

                    
                    try{

                        $id = $this->databaseServices->saveOrder($order);
                        $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Homologação, id '.$id.'em: '.$current;
                                                
                    }catch(PDOException $e){
                        echo $e->getMessage();
                        throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
                    }
                    
                    break;
                    
                    case 2335095664902:
                        // Monta o objeto de Order Homologação com os dados do webhook
                        $order = new stdClass();
                        $order->target = 'MPR';
                        $order->appSecret = $_ENV['SECRETS_MPR'];
                        $order->idOmie = $decoded['event']['idPedido'];
                        $order->codCliente = $decoded['event']['idCliente'];
                        //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                        $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
                        $order->numPedidoOmie = intval($decoded['event']['numeroPedido']);
                        //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                        $order->ncc = $decoded['event']['idContaCorrente'];
                        $order->codVendedorOmie = $decoded['author']['userId'];
                        //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
                        $order->appKey = $decoded['appKey'];

                        try{
                            
                            $id = $this->databaseServices->saveOrder($order);
                            $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Manos-PR id '.$id.'em: '.$current;
                           
        
                    }catch(PDOException $e){
                        echo $e->getMessage();
                        throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
                    }

                    break;
                    
                case 2597402735928:
                    // Monta o objeto de Order Homologação com os dados do webhook
                    $order = new stdClass();
                    $order->target = 'MSC';
                    $order->appSecret = $_ENV['SECRETS_MSC'];
                    $order->idOmie = $decoded['event']['idPedido'];
                    $order->codCliente = $decoded['event']['idCliente'];
                    //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                    $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
                    $order->numPedidoOmie = intval($decoded['event']['numeroPedido']);
                    //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                    $order->ncc = $decoded['event']['idContaCorrente'];
                    $order->codVendedorOmie = $decoded['author']['userId'];
                    //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
                    $order->appKey = $decoded['appKey'];

                    try{

                        $id = $this->databaseServices->saveOrder($order);
                        $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Manos-SC id '.$id.'em: '.$current;
                       
        
                    }catch(PDOException $e){
                        echo $e->getMessage();
                        throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
                    }

                    break;
                }
            
            
            //busca o cnpj do cliente através do id do omie
            $cnpjClient = ($this->omieServices->clienteCnpjOmie($order));
            //busca o contactId do cliente no ploomes pelo cnpj
            (!empty($contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient)))?$contactId : throw new ContactIdInexistentePloomesCRM('Não foi Localizado no Ploomes CRM cliente cadastrado com o CNPJ: '.$cnpjClient.'',1505);
            //monta a mensadem para atualizar o ploomes 
            $msg=[
                'ContactId' => $contactId,
                'Content' => 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.',
                'Title' => 'Pedido Criado Manualmente no Omie ERP'
            ];

            //cria uma interação no Ploomes
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['order']['orderInteraction'] = 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.' : throw new InteracaoNaoAdicionadaException('Não foi possível enviar a mensagem de pedido criado manualmente no Omie ERP ao Ploomes CRM.',1010);

        }
        elseif($decoded['topic'] === "VendaProduto.Incluida" && $decoded['event']['etapa'] == "10" && $decoded['event']['usuarioInclusao'] == 'WEBSERVICE' && $decoded['author']['userId'] == 89 && $decoded['event']['codIntPedido'] != "")
        {
            //se o pedido vier de uma integração por exemplo mercos

            $order = new stdClass();
            
            $order->lastOrderId = $decoded['event']['codIntPedido'];//verifica se o pedido é do ploomes
            (!$this->ploomesServices->requestOrder($order))? throw new PedidoOutraIntegracaoException('Não foi possível encontrar o pedido ['.$order->lastOrderId.'] no ploomes, pode ter sido enviado por outro webservice.'): true ;
           
            
            switch($decoded['appKey']){
                case 2337978328686:               
                    $order->appSecret = $_ENV['SECRETS_MHL'];
                    $order->target = 'MHL';
                    $order->baseTitle = 'Manos Homologação';
                    break;
                    
                case 2335095664902:
                    $order->appSecret = $_ENV['SECRETS_MPR'];
                    $order->target = 'MPR';
                    $order->baseTitle = 'Manos-PR';
                    break;
                    
                case 2597402735928:
                    $order->appSecret = $_ENV['SECRETS_MSC'];
                    $order->target = 'MSC';
                    $order->baseTitle = 'Manos-SC';
                    break;
                }

            $order->idOmie = $decoded['event']['idPedido'];
            $order->codCliente = $decoded['event']['idCliente'];
            $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
            $order->numPedidoOmie = intval($decoded['event']['numeroPedido']);
            $order->ncc = $decoded['event']['idContaCorrente'];
            $order->codVendedorOmie = $decoded['author']['userId'];
            $order->appKey = $decoded['appKey'];

            try{
                
                if($this->databaseServices->isIssetOrder($order->numPedidoOmie, $order->target)){

                    //busca o cnpj do cliente através do id do omie
                    $cnpjClient = ($this->omieServices->clienteCnpjOmie($order));
                    //busca o contactId do cliente no ploomes pelo cnpj
                    (!empty($contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient)))?$contactId : throw new ContactIdInexistentePloomesCRM('Não foi Localizado no Ploomes CRM cliente cadastrado com o CNPJ: '.$cnpjClient.'',1505);
                    //monta a mensadem para atualizar o ploomes 
                    $msg=[
                        'ContactId' => $contactId,
                        'Content' => 'Confirmação de pedido ('.intval($order->numPedidoOmie).') criado com sucesso no Omie ERP na base '.$order->baseTitle,
                        'Title' => 'Venda Integrada via API Bicorp'
                    ];
    
                    //cria uma interação no Ploomes
                    ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['order']['orderInteraction'] = 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.' : throw new InteracaoNaoAdicionadaException('Não foi possível enviar a mensagem de pedido criado manualmente no Omie ERP ao Ploomes CRM.',1010);

                }else{
                    $id = $this->databaseServices->saveOrder($order);

                    $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de '.$order->baseTitle. ' id: '. $id.'em: '.$current.'Obs.: Criado após integração via bicorp Api ter falhado a gravação na base de dados da integração.';
                
                    //busca o cnpj do cliente através do id do omie
                    $cnpjClient = ($this->omieServices->clienteCnpjOmie($order));
                    //busca o contactId do cliente no ploomes pelo cnpj
                    (!empty($contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient)))?$contactId : throw new ContactIdInexistentePloomesCRM('Não foi Localizado no Ploomes CRM cliente cadastrado com o CNPJ: '.$cnpjClient.'',1505);
                    //monta a mensadem para atualizar o ploomes 
                    $msg=[
                        'ContactId' => $contactId,
                        'Content' => 'Confirmação de pedido ('.intval($order->numPedidoOmie).') criado com sucesso no Omie ERP na base '.$order->baseTitle,
                        'Title' => 'Venda Integrada via API Bicorp'
                    ];
    
                    //cria uma interação no Ploomes
                    ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['order']['orderInteraction'] = 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.' : throw new InteracaoNaoAdicionadaException('Não foi possível enviar a mensagem de pedido criado manualmente no Omie ERP ao Ploomes CRM.',1010);
                }

            }catch(PDOException $e){
                echo $e->getMessage();
                throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
            }
            
        }else{
            throw new OrderControllerException('Este pedido já foi salvo pela integração ou era apenas um orçamento '. $current, 1500);
        }

        $message['order']['orderCreate'] = 'Pedido ('.intval($order->numPedidoOmie).'), criado manualmente no Omie ERP e Interação enviada ao ploomes em: '.$current;

        return $message;
    }

    public function deletedOrder($json)
    {   
        
        $current = $this->current;
        $message = [];
        $decoded = json_decode($json, true);
        $omie = new stdClass();
        $omie->codCliente = $decoded['event']['idCliente'];
        $omie->appKey = $decoded['appKey'];

        if(($decoded['topic'] !== "VendaProduto.Cancelada" && isset($decoded['event']['cancelada']) && $decoded['event']['cancelada'] ="S") || $decoded['topic'] !== "VendaProduto.Excluida" && !isset($decoded['event']['cancelada'])  ){
            throw new OrderControllerException('Não havia um pedido cancelado ou excluido no webhook em '.$current);
        }

        

        switch($decoded['appKey'])
            {
                case 2337978328686: //MHL
                    $omie->appSecret = $_ENV['SECRETS_MHL'];
                    $omie->target = 'MHL';       
                    try{
                        $id = $this->databaseServices->isIssetOrder($decoded['event']['idPedido'], $omie->target);

                        if(is_string($id)){
                            throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos Homologação. Erro: '.$id. ' - '.$current, 1030);
                            }elseif(empty($id)){
                            throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos Homologação. - '.$current, 1030);
                        }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
                        }

                    }catch(PedidoInexistenteException $e){
                        throw new PedidoInexistenteException($e->getMessage());
                    }
                    
                    //exclui pedido da base de dados caso seja uma venda excluída
                    if($decoded['topic'] === "VendaProduto.Excluida"){
                        try{                           
                            $message['order']['isdeleted'] = $this->databaseServices->excluiOrder($decoded['event']['idPedido'], $omie->target);

                            if(is_string($message['order']['isdeleted'])){
                                throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
                            }
                        }
                        catch(PedidoNaoExcluidoException $e)
                        {
                            throw new PedidoNaoExcluidoException($e->getMessage());
                        }
                    }

                    //altera o pedido no banco para cancelado
                    try{
                        //Altera o pedido para cancelado no banco MHL
                        $altera = $this->databaseServices->alterOrder($decoded['event']['idPedido'], $omie->target);

                        if(is_string($altera)){
                            throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos Homologação. Erro: '.$altera. ' - '.$current, 1030);                     
                        }

                        $message['invoice']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso! - '.$current;
                        
                    }catch(PedidoCanceladoException $e){          
                        throw new PedidoCanceladoException($e->getMessage(), 1031);
                    }

                 break;
                    
                case 2335095664902: // MPR
                    $omie->appSecret = $_ENV['SECRETS_MPR'];
                    $omie->target = 'MPR';
                    try{
                        $id = $this->databaseServices->isIssetOrder($decoded['event']['idPedido'], $omie->target);
                        if(is_string($id)){
                            throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos-PR. Erro: '.$id. ' - '.$current, 1030);
                            }elseif(empty($id)){
                            throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos-PR , ou já foi cancelado. - '.$current, 1030);
                        }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
                        }

                    }catch(PedidoInexistenteException $e){
                        throw new PedidoInexistenteException($e->getMessage());
                    }

                    //exclui pedido da base de dados caso seja uma venda excluída
                    if($decoded['topic'] === "VendaProduto.Excluida"){
                        try{                           
                            $message['order']['isdeleted'] = $this->databaseServices->excluiOrder($decoded['event']['idPedido'], $omie->target);

                            if(is_string($message['order']['isdeleted'])){
                                throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
                            }
                        }
                        catch(PedidoNaoExcluidoException $e)
                        {
                            throw new PedidoNaoExcluidoException($e->getMessage());
                        }
                    }
                            
                    //altera o pedido no banco para cancelado
                    try{
                        //Altera o pedido para cancelado no banco MPR
                        $altera = $this->databaseServices->alterOrder($decoded['event']['idPedido'], $omie->target);

                        if(is_string($altera)){
                            throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos-PR. Erro: '.$altera. 'em '.$current, 1030);                     
                        }

                        $message['invoice']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso em '.$current;
                        
                    }catch(PedidoCanceladoException $e){          
                        throw new PedidoCanceladoException($e->getMessage(), 1031);
                    }

                break;
                    
                case 2597402735928: // MSC
                    $omie->appSecret = $_ENV['SECRETS_MSC'];
                    $omie->target = 'MSC';
                    try{
                        $id = $this->databaseServices->isIssetOrder($decoded['event']['idPedido'], $omie->target);
                        if(is_string($id)){
                            throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos-SC. Erro: '.$id. ' - '.$current, 1030);
                            }elseif(empty($id)){
                            throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos-SC , ou já foi cancelado. - '.$current, 1030);
                        }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
                        }

                    }catch(PedidoInexistenteException $e){
                        throw new PedidoInexistenteException($e->getMessage());
                    }

                    //exclui pedido da base de dados caso seja uma venda excluída
                    if($decoded['topic'] === "VendaProduto.Excluida"){
                        try{                           
                            $message['order']['isdeleted'] = $this->databaseServices->excluiOrder($decoded['event']['idPedido'], $omie->target);

                            if(is_string($message['order']['isdeleted'])){
                                throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
                            }
                        }
                        catch(PedidoNaoExcluidoException $e)
                        {
                            throw new PedidoNaoExcluidoException($e->getMessage());
                        }
                    }
                            
                    //altera o pedido no banco para cancelado
                    try{
                        //Altera o pedido para cancelado no banco MPR
                        $altera = $this->databaseServices->alterOrder($decoded['event']['idPedido'], $omie->target);

                        if(is_string($altera)){
                            throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos-SC. Erro: '.$altera. 'em '.$current, 1030);                     
                        }

                        $message['order']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso em '.$current;
                        
                    }catch(PedidoCanceladoException $e){          
                        throw new PedidoCanceladoException($e->getMessage(), 1031);
                    }
                    
                break;
            }

            
            //busca o cnpj do cliente através do id do omie
            $cnpjClient = ($this->omieServices->clienteCnpjOmie($omie));
            //busca o contact id do cliente no P`loomes CRM através do cnpj do cliente no Omie ERP
            $contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient);
            //monta a mensadem para atualizar o card do ploomes
            if($message['order']['isdeleted']){
                $msg=[
                        'ContactId' => $contactId,
                        'Content' => 'Pedido ('.$decoded['event']['numeroPedido'].') EXCLUÍDO no Omie ERP em: '.$current,
                        'Title' => 'Pedido EXCLUIDO no Omie ERP'
                    ];
                $message['order']['deleted'] = "Pedido excluído no Omie ERP e na base de dados do sistema!";
            }else{
                $msg=[
                        'ContactId' => $contactId,
                        'Content' => 'Pedido ('.$decoded['event']['numeroPedido'].') cancelado no Omie ERP em: '.$current,
                        'Title' => 'Pedido Cancelado no Omie ERP'
                    ];
                $message['order']['deleted'] = "Pedido excluído no Omie ERP e na base de dados do sistema!";
            }

            //cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['interactionMessage'] = 'Integração de cancelamento/exclusão de Pedido concluída com sucesso!<br> Pedido ('.$decoded['event']['numeroPedido'].') foi cancelado/excluído no Omie ERP, no sistema de integração e interação criada no cliente id: '.$contactId.' - '.$current : throw new InteracaoNaoAdicionadaException('Não foi possível gravar a mensagem de nota cancelada no Ploomes CRM',1032);

        return $message;
    }

    public function alterOrderStage($json)
    {
      
        $current = $this->current;
        $message = [];
        $decoded =json_decode($json, true);
        $omie = new stdClass();
        $omie->codCliente = $decoded['event']['idCliente'];
        $omie->appKey = $decoded['appKey'];
        
        if($decoded['topic'] !== 'VendaProduto.EtapaAlterada'){
            throw new WebhookReadErrorException('Não havia mudança de etapa no webhook - '.$current, 1040);
        }

        switch($decoded['appKey']){
            case 2337978328686: //MHL
                $omie->appSecret = $_ENV['SECRETS_MHL'];
                break;

            case 2335095664902: // MPR
                $omie->appSecret = $_ENV['SECRETS_MPR']; 
                break;

            case 2597402735928: // MSC
                $omie->appSecret = $_ENV['SECRETS_MSC'];
                break;
        }

        //busca o cnpj do cliente através do id do omie
        $cnpjClient = ($this->omieServices->clienteCnpjOmie($omie));
        //busca o contact id do cliente no P`loomes CRM através do cnpj do cliente no Omie ERP
        $contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient);
        //monta a mensadem para atualizar o card do ploomes
        $msg=[
            'ContactId' => $contactId,
            'Content' => 'Etapa do pedido ('.$decoded['event']['numeroPedido'].') ALTERADA no Omie ERP para '.$decoded['event']['etapaDescr'].' em: '.$current,
            'Title' => 'Etapa do pedido ALTERADA no Omie ERP'
        ];
        //cria uma interação no card
        ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['order']['interactionMessage'] = 'Etapa do pedido alterada com sucesso!<br> Etapa do pedido ('.$decoded['event']['numeroPedido'].') foi alterada no Omie ERP para '.$decoded['event']['etapaDescr'].'! - '.$current : throw new InteracaoNaoAdicionadaException('Não foi possível criar interação no Ploomes CRM ',1042);

        if ($decoded['event']['etapa'] === '60' && !empty($decoded['event']['codIntPedido'])){
            

            $orderId = $decoded['event']['codIntPedido'];
            $omie->lastOrderId = $orderId;
            $orderPloomes = $this->ploomesServices->requestOrder($omie);
            if($orderPloomes !== null && $orderPloomes[0]->Id == $orderId){
                
                $stageId= ['StageId'=>40011765];
                $stage = json_encode($stageId);
                ($this->ploomesServices->alterStageOrder($stage, $orderId))?$message['order']['alterStagePloomes'] = 'Estágio do pedido de venda do Ploomes CRM alterado com sucesso! \n Id Pedido Ploomes: '.$orderPloomes[0]->Id.' \n Card Id: '.$orderPloomes[0]->DealId.' \n omieOrderHandler - '.$current : $message['order']['alterStagePloomes'] = 'Não foi possível mudar o estágio do pedido no Ploomes CRM. Pedido não foi encontrado no Ploomes CRM. - omieOrderHandler - '.$current;
            }

            $message['order']['alterStagePloomes'] = 'Não foi possível mudar o estágio da venda no Ploomes CRM, possívelmente o pedido foi criado direto no Omie ERP. - omieOrderHandler - '.$current;

        }

        $message['order']['alterStage'] = 'Integração de mudança de estágio de pedido de venda no omie ERP concluída com sucesso!  - '.$current;

        return $message;

    }
    
}