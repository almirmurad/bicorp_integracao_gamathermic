<?php

namespace src\handlers;

use PDOException;
use phpseclib3\Math\BigInteger\Engines\PHP;
use RabbitMQService;
use src\exceptions\BaseFaturamentoInexistenteException;
use src\exceptions\ClienteInexistenteException;
use src\exceptions\CnpjClienteInexistenteException;
use src\exceptions\DealNaoEncontradoBDException;
use src\exceptions\DealNaoExcluidoBDException;
use src\exceptions\EmailVendedorNaoExistenteException;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\PedidoDuplicadoException;
use src\exceptions\PedidoInexistenteException;
use src\exceptions\PedidoRejeitadoException;
use src\exceptions\ProdutoInexistenteException;
use src\exceptions\ProjetoNaoEncontradoException;
use src\exceptions\PropostaNaoEncontradaException;
use src\exceptions\VendedorInexistenteException;
use src\exceptions\WebhookReadErrorException;
use src\models\Deal;
use src\models\Webhook;
use src\functions\DiverseFunctions;
use src\models\Omie;
use src\models\Order;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;

use src\workers\Workers;
use stdClass;


class OrderHandler
{
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;

    public function __construct(PloomesServices $ploomesServices, OmieServices $omieServices, DatabaseServices $databaseServices)
    {
        $this->ploomesServices = $ploomesServices;
        $this->omieServices = $omieServices;
        $this->databaseServices = $databaseServices;

    }

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveDealHook($json){

        $decoded = json_decode($json, true);

        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';

        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity']??'Deals';
        $webhook->origem = $origem;

        if($this->databaseServices->saveWebhook($webhook))
        {
            $m= [ 'msg' =>'Webhook Salvo com sucesso id = às '.date('d/m/Y H:i:s')];

            return $m;
        } 
    }

    //PROCESSA E CRIA O PEDIDO. CHAMA O REPROCESS CASO DE ERRO
    public function startProcess($json)
    {   
        /*
        * inicia o processo de crição de pedido, caso de certo retorna mensagem de ok pra gravar em log, e caso de erro retorna falso
        */

            // $status = 2; //processando
            // $alterStatus = $this->databaseServices->alterStatusWebhook($h['id'], $status);
            // print'startProcess';
            // print_r($json);
            // exit;
            
            $newOrder=[];
            // if(!isset($newOrder['newOrder']['error'])){
            try{

                $res = Self::newOrder($json);
                if(!isset($res['newOrder']['error'])){
                    // $status = 3; //Success
                    // $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                        $newOrder['success'] = $res;
                        
                        return $newOrder;//card processado pedido criado no Omie retorna mensagem newOrder para salvr no log

                }
                else{
                    //     //$status = 4; //falhou
                    //     //$alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                        
                    //     // $reprocess = Self::reprocessWebhook($hook);

                    //     // if($reprocess['newOrder']['error']){

                    //     //     $log = $this->databaseServices->registerLog($hook['id'], $reprocess['newOrder']['error'], $hook['entity']); 

                            
                    throw new WebhookReadErrorException('Erro ao gravar pedido: ' . $res['newOrder']['error'] . ' - ' . date('d/m/Y H:i:s'), 500);
                            
                    //     return $newOrder;

                    //     // }
                        
                }

            }catch(PedidoInexistenteException $e){
                
                $decoded = json_decode($json,true);
                
                //monta a mensagem para atualizar o card do ploomes
                
                $msg=[
                    'ContactId' => $decoded['New']['ContactId'],
                    'DealId' => $decoded['New']['Id'],
                    'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$e->getMessage(),
                    'Title' => 'Erro na integração'
                ];

                
                //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: ' . $decoded['New']['Id'] . ' card nº: ' .$decoded['New']['DealId'] .' e client id: ' . $decoded['New']['ContactId'] . ' - '. $e->getMessage() . 'Mensagem enviada com sucesso em: '.date('d/m/Y : H:i:s') : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda do ploomes',500);
                throw new WebhookReadErrorException($e->getMessage());
            }
    
            
        
                    
    }

    public function newOrder($json){

        // print_r(json_decode($json));
        // print'newOrder';
        // exit;

        $m = [];
        $current = date('d/m/Y H:i:s');
        $message = [];
        $decoded = json_decode($json, true);

        //cria objeto order
        $order = new Order();            

        //order_24F97378-4AFA-446C-834C-FD5EC4A0453E = Campo Para Condicionais
        //order_5ABC5118-2AA4-493A-B016-67E26C723DD1 = Previsão de Faturamento
        //order_F438939E-F11E-4024-8F3D-6496F2B11778 = Dados Adicionais para a Nota Fiscal
        //order_6B1470FA-DAAF-4F88-AF61-F2FD99EDEE80 = Condição de Pagamento
        //order_BBBEB889-6888-4451-81A7-29AB821B1402 = Projeto
        //order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A = Número do Pedido do Cliente
        //order_94FD0EA3-9219-4E27-8BBC-87D7EB505CD9 = Empresa que irá Faturar
        //order_1268DD4B-1E32-4CCA-A208-DCA5693613E8 = Código Da Proposta
        //order_943040FD-4DEF-4AEB-B21A-2190529FE4B9 = Número de Revisão da Proposta
        //order_C59D726E-A2A5-42B7-A18E-9898E12F203A = Descrição do Serviço
        //order_DF2C63CB-3893-4211-91C9-6A2C4FE1D0CA = Em branco ?(AParentemente informações adicioanis da nota fiscal)
        //order_94E64B44-63C4-4068-A992-F197E40DF8C8 = Razão Social Empresa que irá Faturar
        //order_ACB7C14D-2E32-4FB1-BC18-181EC06593A0 = Atualizar Informações
        //order_4AAD4C79-F3EF-4798-B83E-72466B37DB79 = CNPJ Empresa que irá Faturar
        //order_2E8E6008-5AFF-4D89-9F41-4FDA003D7703 = Inscrição Estadual Empresa Que irá Faturar
        //order_BFDB31A9-C17C-4BC0-AB27-3ADF7582EE4E = Modalidade de Frete
        //order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D = Código Modalidade de Frete

        $prop = [];
        foreach ($decoded['New']['OtherProperties'] as $key => $op) {
            $prop[$key] = $op;
        }

        // foreach ($decoded['New']['Products'] as $ops) {
            
        //     $arrayRequestOrder[$ops['FieldKey']] = $ops['ObjectValueName'] ?? 
        //     $ops['BigStringValue'] ?? $ops['StringValue'] ??  $ops['IntegerValue'] ?? $ops['DateTimeValue'];
            
        // }
            
        $order->id = $decoded['New']['Id']; //Id do Deal
        $order->orderNumber = $decoded['New']['OrderNumber']; // Título do Deal
        $order->contactId = $decoded['New']['ContactId']; // Contatos relacionados
        // Busca o CNPJ do contato 
        ($contactCnpj = $this->ploomesServices->contactCnpj($order)) ? $contactCnpj : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Cliente não informado ou não cadastrado no Omie ERP. Id do card Ploomes CRM: '.$decoded['New']['DealId'].' e pedido de venda Ploomes CRM: '.$decoded['New']['LastOrderId'].'em'.$current; //cnpj do cliente
        $order->contactName = $decoded['New']['ContactName']; // Nome do Contato no Deal
        $order->personId = $decoded['New']['PersonId']; // Id do Contato
        $order->personName = $decoded['New']['PersonName']; // Id do Contato
        $order->stageId = $decoded['New']['StageId']; // Estágio
        $order->dealId = $decoded['New']['DealId']; // Proposta ganha
        $order->createDate = $decoded['New']['CreateDate']; // Proposta ganha
        $order->ownerId = $decoded['New']['OwnerId']; // Responsável
        ($mailVendedor = $this->ploomesServices->ownerMail($order)) ? $mailVendedor: $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Não foi encontrado o email deste vendedor. Id do card Ploomes CRM: '.$decoded['New']['DealId'].' e pedido de venda Ploomes CRM: '.$decoded['New']['LastOrderId'].'em'.$current;
        //$mailVendedor = 'vendas9@fielpapeis.com.br';
        $order->amount = $decoded['New']['Amount']; // Valor
        $order->ownerMail = $mailVendedor;
            // Id do formulário externo de atualização
        //$order->webhookId = $webhook['id']; //inclui o id do webhook no deal
            
            
            //order_5ABC5118-2AA4-493A-B016-67E26C723DD1 //previsão de faturamento
            //order_94FD0EA3-9219-4E27-8BBC-87D7EB505CD9 // Empresa que irá faturar 
            //order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D //id modalidade frete
            //order_BFDB31A9-C17C-4BC0-AB27-3ADF7582EE4E // modalidada (string)
            //order_BBBEB889-6888-4451-81A7-29AB821B1402 //projeto
            //order_C59D726E-A2A5-42B7-A18E-9898E12F203A //Descrição do serviço
            //order_94E64B44-63C4-4068-A992-F197E40DF8C8 //Razão social da empresa que irá faturar
            //order_4AAD4C79-F3EF-4798-B83E-72466B37DB79 // CNPJ da empresa que irá faturar
            //order_2E8E6008-5AFF-4D89-9F41-4FDA003D7703 // I>E> da empresa que irá faturar
            //order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A // numero pedido do cliente
            //order_4AF9E1C1-6DB9-45B2-89E4-9062B2E07B87 // numero pedido de compra
            //order_F438939E-F11E-4024-8F3D-6496F2B11778 // dados adicionais NF
            //order_1268DD4B-1E32-4CCA-A208-DCA5693613E8 // cod da proposta
            //order_943040FD-4DEF-4AEB-B21A-2190529FE4B9 // num de revisão da proposta
            //order_E768DCD5-D0B0-4417-9F58-6A4333C1846C // valor anterior da venda
            //order_F90FC615-C3B1-4E9A-9061-88C0AF944CC5 // Template Id (Jéssica)
            //order_B14B38B7-FB43-4E8E-A57A-61EFC97725A6 // codigo do parcelamento

            
            //Encontra a base de faturamento             
                                                               
            $order->baseFaturamento = $prop['order_94FD0EA3-9219-4E27-8BBC-87D7EB505CD9'];
            $omie = new Omie();
            switch ($order->baseFaturamento) {
                case '420197141':
                    $order->baseFaturamentoTitle = 'GAMATERMIC';
                    $omie->baseFaturamentoTitle = 'GAMATERMIC'; 
                    $omie->target = 'MPR'; 
                    $omie->ncc = $_ENV['NCC_MPR'];
                    $omie->appSecret = $_ENV['SECRETS_MPR'];
                    $omie->appKey = $_ENV['APPK_MPR'];
                    break;
                    
                case '420197143':
                    $order->baseFaturamentoTitle = 'SEMIN';
                    $omie->baseFaturamentoTitle = 'SEMIN';
                    $omie->target = 'MSC'; 
                    $omie->ncc = $_ENV['NCC_MSC'];
                    $omie->appSecret = $_ENV['SECRETS_MSC'];
                    $omie->appKey = $_ENV['APPK_MSC'];
                    break;
                    
                case '420197140':
                    $order->baseFaturamentoTitle = 'ENGEPARTS';
                    $omie->baseFaturamentoTitle = 'ENGEPARTS';
                    $omie->target = 'MHL'; 
                    $omie->ncc = $_ENV['NCC_DEMO'];
                    $omie->appSecret = $_ENV['SECRETS_DEMO'];
                    $omie->appKey = $_ENV['APPK_DEMO'];
                    break;

                case '420197142':
                    $order->baseFaturamentoTitle = 'GSU';
                    $omie->baseFaturamentoTitle = 'GSU';
                    $omie->target = 'MHL'; 
                    $omie->ncc = $_ENV['NCC_DEMO'];
                    $omie->appSecret = $_ENV['SECRETS_DEMO'];
                    $omie->appKey = $_ENV['APPK_DEMO'];
                    break;
                    
                    default:
                    throw new PedidoInexistenteException ('Erro ao montar pedido para enviar ao Omie ERP: Base de faturamento não encontrada. Impossível fazer consultas no omie', 500);
                    break;
                }

  
            // print_r($omie);
            // exit;

            //previsão de faturamento
            $order->previsaoFaturamento =(isset($prop['order_5ABC5118-2AA4-493A-B016-67E26C723DD1']) && !empty($prop['order_5ABC5118-2AA4-493A-B016-67E26C723DD1']))? $prop['order_5ABC5118-2AA4-493A-B016-67E26C723DD1'] : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Previsão de Faturamento não foi preenchida';
            $order->templateId =(isset($prop['order_F90FC615-C3B1-4E9A-9061-88C0AF944CC5']) && !empty($prop['order_F90FC615-C3B1-4E9A-9061-88C0AF944CC5']))? $prop['order_F90FC615-C3B1-4E9A-9061-88C0AF944CC5'] : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Previsão de Faturamento não foi preenchida';
            //numero do pedido do cliente (preenchido na venda) localizado em pedidos info. adicionais
            $order->numPedidoCliente = (isset($prop['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']) && !empty($prop['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']))?$prop['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']:$m[] = 'Erro ao montar pedido para enviar ao Omie ERP: O número do Pedido do Cliente não foi preenchido';
            //Numero pedido de compra (id da proposta) localizado em item da venda info. adicionais
            $order->numPedidoCompra = (isset($prop['order_4AF9E1C1-6DB9-45B2-89E4-9062B2E07B87']) && !empty($prop['order_4AF9E1C1-6DB9-45B2-89E4-9062B2E07B87'])? $prop['order_4AF9E1C1-6DB9-45B2-89E4-9062B2E07B87']: null); //$m[]='Erro ao montar pedido para enviar ao Omie ERP:  Não havia Número do Pedido de Compra');//em caso de obrigatoriedade deste campo $m[]='Erro ao criar pedido. Não havia Ordem de compra         //array de produtos da venda
            //id modalidade do frete
            $order->modalidadeFrete =  (isset($prop['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D']) && !empty($prop['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D']) || $prop['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D'] === "0")?$prop['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D']:$m[]='Erro ao montar pedido para enviar ao Omie ERP:  Modalidade de Frete não informado';
            //projeto 
            $order->projeto = ($prop['order_BBBEB889-6888-4451-81A7-29AB821B1402']) ?? $m[]='Erro ao montar pedido para enviar ao Omie ERP: Não foi informado o Projeto';
            //observações da nota
            $notes = strip_tags($prop['order_F438939E-F11E-4024-8F3D-6496F2B11778']) ?? null;  
            $order->idParcelamento = $prop['order_B14B38B7-FB43-4E8E-A57A-61EFC97725A6'] ?? $m[]='Erro ao montar pedido para enviar ao Omie ERP: Não foi informado o código do parcelamento';
            
            $id = $this->omieServices->insertProject($omie,  $order->projeto);
            $omie->codProjeto =(isset($id['codigo'])) ? $id['codigo'] : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: ' . $id['faultstring'];

            //pega o id do cliente do Omie através do CNPJ do contact do ploomes           
            (!empty($idClienteOmie = $this->omieServices->clienteIdOmie($omie, $contactCnpj))) ? $idClienteOmie : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Id do cliente não encontrado no Omie ERP! Id do card Ploomes CRM: '.$order->dealId.' e pedido de venda Ploomes CRM: '.$order->id.' em: '.$current;
            
            //pega o id do vendedor Omie através do email do vendedor do ploomes           
            (!empty($codVendedorOmie = $this->omieServices->vendedorIdOmie($omie, $mailVendedor))) ? $codVendedorOmie : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Id do vendedor não encontrado no Omie ERP!Id do card Ploomes CRM: '.$order->dealId.' e pedido de venda Ploomes CRM: '.$order->id.' em: '.$current;
            // $codVendedorOmie = 4216876829;
            
            // $parcelamento = '21/28/35';
            
            
            //array de produtos da venda
            
            //  print_r($decoded['New']['Products']);
            // exit;
            //Array de detalhes do item da venda
            $arrayRequestOrder = $this->ploomesServices->requestOrder($order);
      
            $type = match($order->templateId){
                '40130624' => "servicos",
                '40124278' => "produtos"
            };

            $isService = ($type === 'servicos') ? true : false;

            $idItemOmie =  match(strtolower($order->baseFaturamentoTitle)){
                'gamatermic'=> 'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
                'semin'=> 'product_429C894A-708E-4125-A434-2A70EDCAFED6',
                'engeparts'=> 'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
                'gsu'=> 'product_08A41D8E-F593-4B74-8CF8-20A924209A09',
            };

            if(!empty($m)){
                
                throw new PedidoInexistenteException($m[0],500);            

            }
          
            
            $productsOrder = []; 
            $det = [];  
            $det['ide'] = [];
            $det['produto'] = [];
            $opServices = [];
            $serviceOrder = [];
            $pu = [];
            $service = [];
            $produtosUtilizados = [];

            foreach($arrayRequestOrder['Products'] as $prdItem){
                
                foreach($prdItem['Product']['OtherProperties'] as $otherp){
                    $opServices[$otherp['FieldKey']] = $otherp['ObjectValueName'] ?? 
                    $otherp['BigStringValue'] ?? $otherp['StringValue'] ??  $otherp['IntegerValue'] ?? $otherp['DateTimeValue'];
                    
                }
                if($isService){
                    
                    //verifica se tem serviço com produto junto
                    if($prdItem['Product']['Group']['Name'] !== 'Serviços'){
                        
                        $pu['nCodProdutoPU'] = $opServices[$idItemOmie];
                        $pu['nQtdePU'] = $prdItem['Quantity'];
                        
                        $produtosUtilizados[] = $pu;

                    }else{

                        $service['nCodServico'] = $opServices[$idItemOmie];
                        $service['nQtde'] = $prdItem['Quantity'];
                        $service['nValUnit'] = $prdItem['UnitPrice'];

                        $serviceOrder[] = $service;
                    }

                    $incluiOS = $this->omieServices->criaOS($omie, $idClienteOmie, $order, $serviceOrder, $codVendedorOmie, $notes, $produtosUtilizados);

                    /**
                     * [cCodIntOS] => SRV/404442017
                     * [nCodOS] => 6992578495
                     * [cNumOS] => 000000000000018
                     * [cCodStatus] => 0
                     * [cDescStatus] => Ordem de Serviço adicionada com sucesso!
                     */
                    if(isset($incluiOS['cCodStatus']) && $incluiOS['cCodStatus'] == "0"){

                        $msg=[
                            'ContactId' => $order->contactId,
                            'DealId' => $order->id ?? null,
                            'Content' => 'Ordem de Serviço ('.intval($incluiOS['cNumOS']).') criada no OMIE via API BICORP na base '.$order->baseFaturamentoTitle.'.',
                            'Title' => 'Orde de Serviço Criada'
                        ];

                        //cria uma interação no card
                        ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' e mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);

                        $message['winDeal']['incluiOS']['Success'] = $incluiOS['cDescStatus']. 'Numero: ' . intval($incluiOS['cNumOS']);
                    }else{
                    
                        $message['winDeal']['error'] ='Não foi possível gravar o Ordem de Serviço no Omie! '.$incluiOS['faultstring'];
                       
                        $msg=[
                            'ContactId' => $order->contactId,
                            'DealId' => $order->id ?? null,
                            'Content' => 'Ordem de Serviço não pode ser criado no OMIE ERP. '.$incluiOS['faultstring'],
                            'Title' => 'Erro na integração'
                        ];
                    
                        //cria uma interação no card
                        ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis, pedido: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' - '.$incluiPedidoOmie['faultstring']. 'Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
                   
                    }

                }else{
  
                    $det['ide']['codigo_item_integracao'] = $prdItem['Id'];
                    $det['produto']['quantidade'] = $prdItem['Quantity'];
                    //$det['produto']['tipo_desconto'] = 'P';
                    //$dicount =$prdItem['Discount'] ?? 0;
                    //$det['produto']['percentual_desconto'] = number_format($dicount, 2, ',', '.');
                    $det['produto']['valor_unitario'] = $prdItem['UnitPrice'];
                    $det['produto']['codigo_produto'] = $opServices[$idItemOmie];

                    $det['inf_adic'] = [];
                    $det['inf_adic']['numero_pedido_compra'] = $order->numPedidoCompra;
                    $det['inf_adic']['item_pedido_compra'] =$prdItem['Ordination']+1;

                    $productsOrder[] = $det;
                    
                }
               

            }

            // $idItemOmie =  match($order->baseFaturamento){
            //     'gamatermic'=> 'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
            //     'semin'=> 'product_429C894A-708E-4125-A434-2A70EDCAFED6',
            //     'engeparts'=> 'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
            //     'gsu'=> 'product_08A41D8E-F593-4B74-8CF8-20A924209A09',
            // };

            // foreach ($arrayRequestOrder['Products'] as $prdItem) 
            // {   
            //     //este codigo buscava no proprio ploomes o codigo do omie em other properties de products
                
            //     $det['ide'] = [];
            //     $det['ide']['codigo_item_integracao'] = $prdItem['Id'];
            //     $det['produto'] = [];
            //     $det['produto']['quantidade'] = $prdItem['Quantity'];
            //     //$det['produto']['tipo_desconto'] = 'P';
            //     //$dicount =$prdItem['Discount'] ?? 0;
            //     //$det['produto']['percentual_desconto'] = number_format($dicount, 2, ',', '.');
            //     $det['produto']['valor_unitario'] = $prdItem['UnitPrice'];
            //     $det['inf_adic'] = [];
            //     $det['inf_adic']['numero_pedido_compra'] = $order->numPedidoCompra;
            //     $det['inf_adic']['item_pedido_compra'] =$prdItem['Ordination']+1;
                    
            //     //$idPrd = $prdItem['ProductCode'];     
            //     //encontra o id do produto no omie atraves do Code do ploomes (é necessário pois cada base omie tem código diferente pra cada item)
            //     //(!empty($idProductOmie = $this->omieServices->buscaIdProductOmie($omie, $idPrd))) ? $idProductOmie : $m[]='Erro ao montar pedido para enviar ao Omie ERP: Id do Produto inexistente no Omie ERP. Id do card Ploomes CRM: '.$order->dealId.' e pedido de venda Ploomes CRM: '.$order->id.'em'.$current;

            //     foreach ($prdItem['Product'] as $ops) {              
                
            //         foreach($ops['OtherProperties'] as $iops){

            //             $det['produto']['codigo_produto'] = $iops[$idItemOmie];;//mudei aqui de $idproductOmie para $idPrd
                         
            //         }

            //         // $[$ops['FieldKey']] = $ops['ObjectValueName'] ?? 
            //         // $ops['BigStringValue'] ?? $ops['StringValue'] ??  $ops['IntegerValue'] ?? $ops['DateTimeValue'];

            //     }
            //     print_r($det);
            //     exit;
               


            //     if($isService){
                    
            //         // foreach ($prdItem['Product']['OtherProperties'] as $prodOps) {
                
            //         //     $prdItem[$prodOps['FieldKey']] =  
            //         //      $prodOps['StringValue'];
                        
            //         // }
            //         $service['nCodServico'] = $idProductOmie;
            //         $service['nQtde'] = $prdItem['Quantity'];
            //         $service['nValUnit'] = $prdItem['UnitPrice'];

            //         $serviceOrder[] = $service;
            //     }else{

            // //    $idProductOmie =  match($order->baseFaturamento){
            // //                 'gamatermic'=> 'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
            // //                 'semin'=> 'product_429C894A-708E-4125-A434-2A70EDCAFED6',
            // //                 'engeparts'=> 'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
            // //                 'gsu'=> 'product_08A41D8E-F593-4B74-8CF8-20A924209A09',
            // //             };

                    
                    
            //         $productsOrder[] = $det;
            //     }
            // }    
            
            // print_r($serviceOrder);
            // exit;
            
            
            // exit;
            /****************************************************************
            *                     Cria Pedido no Omie                       *
            *                                                               *
            * Cria um pedido de venda no omie. Obrigatório enviar:          *
            * chave app do omie, chave secreta do omie, id do cliente omie, *
            * data de previsão(finish date), id pedido integração (id pedido* 
            * no ploomes), array de produtos($prodcutsOrder), numero conta  *    
            * corrente do Omie ($ncc), id do vendedor omie($codVendedorOmie)*
            * Total do pedido e Array de parcelamento                       *
            *                                                               *
            *****************************************************************/

            
           
            if($isService){
                // print 'entrou no if isservice';
                // print_r($serviceOrder);
                // exit;
                $incluiOS = $this->omieServices->criaOS($omie, $idClienteOmie, $order, $serviceOrder, $codVendedorOmie, $notes, $produtosUtilizados);

                // print_r($incluiOS);
                // exit;
                /**
                 * [cCodIntOS] => SRV/404442017
                 * [nCodOS] => 6992578495
                 * [cNumOS] => 000000000000018
                 * [cCodStatus] => 0
                 * [cDescStatus] => Ordem de Serviço adicionada com sucesso!
                 */
               if(isset($incluiOS['cCodStatus']) && $incluiOS['cCodStatus'] == "0"){
                    $message['winDeal']['incluiOS']['Success'] = $incluiOS['cDescStatus']. 'Numero: ' . intval($incluiOS['cNumOS']);
                }
            }else{
                // print 'entrou no else de vendad e produto';
                // print_r($omie);
                // print_r($idClienteOmie);
                // print_r($order);
                // print_r($productsOrder);
                // exit;
                $incluiPedidoOmie = $this->omieServices->criaPedidoOmie($omie, $idClienteOmie, $order, $productsOrder, $codVendedorOmie, $notes);

                //verifica se criou o pedido no omie
                if (isset($incluiPedidoOmie['codigo_status']) && $incluiPedidoOmie['codigo_status'] == "0") {

                    //monta a mensagem para atualizar o card do ploomes
                    $msg=[
                        'ContactId' => $order->contactId,
                        'DealId' => $order->id ?? null,
                        'Content' => 'Venda ('.intval($incluiPedidoOmie['numero_pedido']).') criada no OMIE via API BICORP na base '.$order->baseFaturamentoTitle.'.',
                        'Title' => 'Pedido Criado'
                    ];
                
                    //cria uma interação no card
                    ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->id.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).' e mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
                    
                    $message['winDeal']['returnPedidoOmie'] ='Pedido criado no Omie via BICORP INTEGRAÇÃO pedido numero: '.intval($incluiPedidoOmie['numero_pedido']);
                    //inclui o id do pedido no omie na tabela deal
                    // if($incluiPedidoOmie['codigo_pedido']){
                    //     //salva um deal no banco
                    //     $order->omieOrderId = $incluiPedidoOmie['codigo_pedido'];
                    //     $dealCreatedId = $this->databaseServices->saveDeal($deal);   
                    //     $message['winDeal']['dealMessage'] ='Id do Deal no Banco de Dados: '.$dealCreatedId;  
                    //     if($dealCreatedId){

                    //         $omie->idOmie = $order->omieOrderId;
                    //         $omie->codCliente = $idClienteOmie;
                    //         $omie->codPedidoIntegracao = $order->id;
                    //         $omie->numPedidoOmie = intval($incluiPedidoOmie['numero_pedido']);
                    //         $omie->codClienteIntegracao = $order->contactId;
                    //         $omie->dataPrevisao = $order->finishDate;
                    //         $omie->codVendedorOmie = $codVendedorOmie;
                    //         $omie->idVendedorPloomes = $order->ownerId;   
                    //         $omie->appKey = $omie->appKey;             
                    //         try{
                    //             $id = $this->databaseServices->saveOrder($omie);
                    //             $message['winDeal']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos '.$omie->baseFaturamentoTitle.' id '.$id.'em: '.$current;

                    //         }catch(PedidoDuplicadoException $e){
                    //             $message['winDeal']['error'] ='Não foi possível gravar o pedido no Omie! '.$e->getMessage();
                    //         }
                    //     }
                        
                    // }

                }else{
                    
                    $message['winDeal']['error'] ='Não foi possível gravar o pedido no Omie! '.$incluiPedidoOmie['faultstring'];
                    // if(isset($webhook['reprocess']) && $webhook['reprocess'] == 1){
                        
                        //monta a mensagem para atualizar o card do ploomes
                        $msg=[
                            'ContactId' => $order->contactId,
                            'DealId' => $order->id ?? null,
                            'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$incluiPedidoOmie['faultstring'],
                            'Title' => 'Erro na integração'
                        ];
                    
                        //cria uma interação no card
                        ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: '.$order->id.' card nº: '.$order->id.' e client id: '.$order->contactId.' - '.$incluiPedidoOmie['faultstring']. 'Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
                    // }  
                
                }  
            }           

            return $message;
        // } else {

        //     $status = 4;
        //     $m[]= 'Erro ao montar pedido para enviar ao Omie ERP: Não havia proposta ou venda no card Nº '.$decoded['New']['Id'].', possívelmente não é proviniente de nenhum funil de vendas.';
        //     //$alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
        //     //$log = $this->databaseServices->registerLog($webhook['id'], $m[0], $decoded['Entity']);

        //     $msg=[
        //         'ContactId' => $decoded['New']['ContactId'],
        //         'DealId' => $decoded['New']['Id'] ?? null,
        //         'Content' => 'Não havia proposta ou venda no card Nº '.$decoded['New']['Id'].', possívelmente não é proviniente de nenhum funil de vendas.'.$current ,
        //         'Title' => 'Erro na integração'
        //     ];
           
        //     //cria uma interação no card
        //     ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, Não existia venda no card nº: '.$decoded['New']['Id'].' do client id: '.$decoded['New']['ContactId'].'. Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);

        //     throw new WebhookReadErrorException('Não era um Card Ganho ou não haviam proposta e venda no card Nº '.$decoded['New']['Id'].' data '.$current ,500);
        // }          

    }



   

    // public function newOmieOrder(string $json):array
    // {
    //     $current = $this->current;
    //     $message = [];
        
    //     //decodifica o json de pedidos do webhook
    //     $decoded = json_decode($json, true);

    //     if($decoded['topic'] === "VendaProduto.Incluida" && $decoded['event']['etapa'] == "10" && $decoded['event']['usuarioInclusao'] !== 'WEBSERVICE'){

    //         switch($decoded['appKey']){

    //             case 2337978328686:               
    //                 // Monta o objeto de Order Homologação com os dados do webhook
    //                 $order = new stdClass();
    //                 $order->target = 'MHL';
    //                 $order->appSecret = $_ENV['SECRETS_MHL'];
    //                 $order->idOmie = $decoded['event']['idPedido'];
    //                 $order->codCliente = $decoded['event']['idCliente'];
    //                 //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
    //                 $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
    //                 $order->numPedidoOmie = intval($decoded['event']['numeroPedido']);
    //                 //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
    //                 $order->ncc = $decoded['event']['idContaCorrente'];
    //                 $order->codVendedorOmie = $decoded['author']['userId'];
    //                 //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
    //                 $order->appKey = $decoded['appKey'];  

                    
    //                 try{

    //                     $id = $this->databaseServices->saveOrder($order);
    //                     $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Homologação, id '.$id.'em: '.$current;
                                                
    //                 }catch(PDOException $e){
    //                     echo $e->getMessage();
    //                     throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
    //                 }
                    
    //                 break;
                    
    //                 case 2335095664902:
    //                     // Monta o objeto de Order Homologação com os dados do webhook
    //                     $order = new stdClass();
    //                     $order->target = 'MPR';
    //                     $order->appSecret = $_ENV['SECRETS_MPR'];
    //                     $order->idOmie = $decoded['event']['idPedido'];
    //                     $order->codCliente = $decoded['event']['idCliente'];
    //                     //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
    //                     $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
    //                     $order->numPedidoOmie = intval($decoded['event']['numeroPedido']);
    //                     //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
    //                     $order->ncc = $decoded['event']['idContaCorrente'];
    //                     $order->codVendedorOmie = $decoded['author']['userId'];
    //                     //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
    //                     $order->appKey = $decoded['appKey'];

    //                     try{
                            
    //                         $id = $this->databaseServices->saveOrder($order);
    //                         $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Manos-PR id '.$id.'em: '.$current;
                           
        
    //                 }catch(PDOException $e){
    //                     echo $e->getMessage();
    //                     throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
    //                 }

    //                 break;
                    
    //             case 2597402735928:
    //                 // Monta o objeto de Order Homologação com os dados do webhook
    //                 $order = new stdClass();
    //                 $order->target = 'MSC';
    //                 $order->appSecret = $_ENV['SECRETS_MSC'];
    //                 $order->idOmie = $decoded['event']['idPedido'];
    //                 $order->codCliente = $decoded['event']['idCliente'];
    //                 //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
    //                 $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
    //                 $order->numPedidoOmie = intval($decoded['event']['numeroPedido']);
    //                 //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
    //                 $order->ncc = $decoded['event']['idContaCorrente'];
    //                 $order->codVendedorOmie = $decoded['author']['userId'];
    //                 //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
    //                 $order->appKey = $decoded['appKey'];

    //                 try{

    //                     $id = $this->databaseServices->saveOrder($order);
    //                     $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Manos-SC id '.$id.'em: '.$current;
                       
        
    //                 }catch(PDOException $e){
    //                     echo $e->getMessage();
    //                     throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
    //                 }

    //                 break;
    //             }
            
            
    //         //busca o cnpj do cliente através do id do omie
    //         $cnpjClient = ($this->omieServices->clienteCnpjOmie($order));
    //         //busca o contactId do cliente no ploomes pelo cnpj
    //         (!empty($contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient)))?$contactId : throw new ContactIdInexistentePloomesCRM('Não foi Localizado no Ploomes CRM cliente cadastrado com o CNPJ: '.$cnpjClient.'',1505);
    //         //monta a mensadem para atualizar o ploomes 
    //         $msg=[
    //             'ContactId' => $contactId,
    //             'Content' => 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.',
    //             'Title' => 'Pedido Criado Manualmente no Omie ERP'
    //         ];

    //         //cria uma interação no Ploomes
    //         ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['order']['orderInteraction'] = 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.' : throw new InteracaoNaoAdicionadaException('Não foi possível enviar a mensagem de pedido criado manualmente no Omie ERP ao Ploomes CRM.',1010);

    //     }
    //     elseif($decoded['topic'] === "VendaProduto.Incluida" && $decoded['event']['etapa'] == "10" && $decoded['event']['usuarioInclusao'] == 'WEBSERVICE' && $decoded['author']['userId'] == 89 && $decoded['event']['codIntPedido'] != "")
    //     {
    //         //se o pedido vier de uma integração por exemplo mercos

    //         $order = new stdClass();
            
    //         $order->id = $decoded['event']['codIntPedido'];//verifica se o pedido é do ploomes
    //         (!$this->ploomesServices->requestOrder($order))? throw new PedidoOutraIntegracaoException('Não foi possível encontrar o pedido ['.$order->lastOrderId.'] no ploomes, pode ter sido enviado por outro webservice.'): true ;
           
            
    //         switch($decoded['appKey']){
    //             case 2337978328686:               
    //                 $order->appSecret = $_ENV['SECRETS_MHL'];
    //                 $order->target = 'MHL';
    //                 $order->baseTitle = 'Manos Homologação';
    //                 break;
                    
    //             case 2335095664902:
    //                 $order->appSecret = $_ENV['SECRETS_MPR'];
    //                 $order->target = 'MPR';
    //                 $order->baseTitle = 'Manos-PR';
    //                 break;
                    
    //             case 2597402735928:
    //                 $order->appSecret = $_ENV['SECRETS_MSC'];
    //                 $order->target = 'MSC';
    //                 $order->baseTitle = 'Manos-SC';
    //                 break;
    //             }

    //         $order->idOmie = $decoded['event']['idPedido'];
    //         $order->codCliente = $decoded['event']['idCliente'];
    //         $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
    //         $order->numPedidoOmie = intval($decoded['event']['numeroPedido']);
    //         $order->ncc = $decoded['event']['idContaCorrente'];
    //         $order->codVendedorOmie = $decoded['author']['userId'];
    //         $order->appKey = $decoded['appKey'];

    //         try{
                
    //             if($this->databaseServices->isIssetOrder($order->numPedidoOmie, $order->target)){

    //                 //busca o cnpj do cliente através do id do omie
    //                 $cnpjClient = ($this->omieServices->clienteCnpjOmie($order));
    //                 //busca o contactId do cliente no ploomes pelo cnpj
    //                 (!empty($contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient)))?$contactId : throw new ContactIdInexistentePloomesCRM('Não foi Localizado no Ploomes CRM cliente cadastrado com o CNPJ: '.$cnpjClient.'',1505);
    //                 //monta a mensadem para atualizar o ploomes 
    //                 $msg=[
    //                     'ContactId' => $contactId,
    //                     'Content' => 'Confirmação de pedido ('.intval($order->numPedidoOmie).') criado com sucesso no Omie ERP na base '.$order->baseTitle,
    //                     'Title' => 'Venda Integrada via API Bicorp'
    //                 ];
    
    //                 //cria uma interação no Ploomes
    //                 ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['order']['orderInteraction'] = 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.' : throw new InteracaoNaoAdicionadaException('Não foi possível enviar a mensagem de pedido criado manualmente no Omie ERP ao Ploomes CRM.',1010);

    //             }else{
    //                 $id = $this->databaseServices->saveOrder($order);

    //                 $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de '.$order->baseTitle. ' id: '. $id.'em: '.$current.'Obs.: Criado após integração via bicorp Api ter falhado a gravação na base de dados da integração.';
                
    //                 //busca o cnpj do cliente através do id do omie
    //                 $cnpjClient = ($this->omieServices->clienteCnpjOmie($order));
    //                 //busca o contactId do cliente no ploomes pelo cnpj
    //                 (!empty($contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient)))?$contactId : throw new ContactIdInexistentePloomesCRM('Não foi Localizado no Ploomes CRM cliente cadastrado com o CNPJ: '.$cnpjClient.'',1505);
    //                 //monta a mensadem para atualizar o ploomes 
    //                 $msg=[
    //                     'ContactId' => $contactId,
    //                     'Content' => 'Confirmação de pedido ('.intval($order->numPedidoOmie).') criado com sucesso no Omie ERP na base '.$order->baseTitle,
    //                     'Title' => 'Venda Integrada via API Bicorp'
    //                 ];
    
    //                 //cria uma interação no Ploomes
    //                 ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['order']['orderInteraction'] = 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.' : throw new InteracaoNaoAdicionadaException('Não foi possível enviar a mensagem de pedido criado manualmente no Omie ERP ao Ploomes CRM.',1010);
    //             }

    //         }catch(PDOException $e){
    //             echo $e->getMessage();
    //             throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
    //         }
            
    //     }else{
    //         throw new OrderControllerException('Este pedido já foi salvo pela integração ou era apenas um orçamento '. $current, 1500);
    //     }

    //     $message['order']['orderCreate'] = 'Pedido ('.intval($order->numPedidoOmie).'), criado manualmente no Omie ERP e Interação enviada ao ploomes em: '.$current;

    //     return $message;
    // }

    // public function deletedOrder($json)
    // {   
        
    //     $current = $this->current;
    //     $message = [];
    //     $decoded = json_decode($json, true);
    //     $omie = new stdClass();
    //     $omie->codCliente = $decoded['event']['idCliente'];
    //     $omie->appKey = $decoded['appKey'];

    //     if(($decoded['topic'] !== "VendaProduto.Cancelada" && isset($decoded['event']['cancelada']) && $decoded['event']['cancelada'] ="S") || $decoded['topic'] !== "VendaProduto.Excluida" && !isset($decoded['event']['cancelada'])  ){
    //         throw new OrderControllerException('Não havia um pedido cancelado ou excluido no webhook em '.$current);
    //     }

        

    //     switch($decoded['appKey'])
    //         {
    //             case 2337978328686: //MHL
    //                 $omie->appSecret = $_ENV['SECRETS_MHL'];
    //                 $omie->target = 'MHL';       
    //                 try{
    //                     $id = $this->databaseServices->isIssetOrder($decoded['event']['idPedido'], $omie->target);

    //                     if(is_string($id)){
    //                         throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos Homologação. Erro: '.$id. ' - '.$current, 1030);
    //                         }elseif(empty($id)){
    //                         throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos Homologação. - '.$current, 1030);
    //                     }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
    //                     }

    //                 }catch(PedidoInexistenteException $e){
    //                     throw new PedidoInexistenteException($e->getMessage());
    //                 }
                    
    //                 //exclui pedido da base de dados caso seja uma venda excluída
    //                 if($decoded['topic'] === "VendaProduto.Excluida"){
    //                     try{                           
    //                         $message['order']['isdeleted'] = $this->databaseServices->excluiOrder($decoded['event']['idPedido'], $omie->target);

    //                         if(is_string($message['order']['isdeleted'])){
    //                             throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
    //                         }
    //                     }
    //                     catch(PedidoNaoExcluidoException $e)
    //                     {
    //                         throw new PedidoNaoExcluidoException($e->getMessage());
    //                     }
    //                 }

    //                 //altera o pedido no banco para cancelado
    //                 try{
    //                     //Altera o pedido para cancelado no banco MHL
    //                     $altera = $this->databaseServices->alterOrder($decoded['event']['idPedido'], $omie->target);

    //                     if(is_string($altera)){
    //                         throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos Homologação. Erro: '.$altera. ' - '.$current, 1030);                     
    //                     }

    //                     $message['invoice']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso! - '.$current;
                        
    //                 }catch(PedidoCanceladoException $e){          
    //                     throw new PedidoCanceladoException($e->getMessage(), 1031);
    //                 }

    //              break;
                    
    //             case 2335095664902: // MPR
    //                 $omie->appSecret = $_ENV['SECRETS_MPR'];
    //                 $omie->target = 'MPR';
    //                 try{
    //                     $id = $this->databaseServices->isIssetOrder($decoded['event']['idPedido'], $omie->target);
    //                     if(is_string($id)){
    //                         throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos-PR. Erro: '.$id. ' - '.$current, 1030);
    //                         }elseif(empty($id)){
    //                         throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos-PR , ou já foi cancelado. - '.$current, 1030);
    //                     }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
    //                     }

    //                 }catch(PedidoInexistenteException $e){
    //                     throw new PedidoInexistenteException($e->getMessage());
    //                 }

    //                 //exclui pedido da base de dados caso seja uma venda excluída
    //                 if($decoded['topic'] === "VendaProduto.Excluida"){
    //                     try{                           
    //                         $message['order']['isdeleted'] = $this->databaseServices->excluiOrder($decoded['event']['idPedido'], $omie->target);

    //                         if(is_string($message['order']['isdeleted'])){
    //                             throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
    //                         }
    //                     }
    //                     catch(PedidoNaoExcluidoException $e)
    //                     {
    //                         throw new PedidoNaoExcluidoException($e->getMessage());
    //                     }
    //                 }
                            
    //                 //altera o pedido no banco para cancelado
    //                 try{
    //                     //Altera o pedido para cancelado no banco MPR
    //                     $altera = $this->databaseServices->alterOrder($decoded['event']['idPedido'], $omie->target);

    //                     if(is_string($altera)){
    //                         throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos-PR. Erro: '.$altera. 'em '.$current, 1030);                     
    //                     }

    //                     $message['invoice']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso em '.$current;
                        
    //                 }catch(PedidoCanceladoException $e){          
    //                     throw new PedidoCanceladoException($e->getMessage(), 1031);
    //                 }

    //             break;
                    
    //             case 2597402735928: // MSC
    //                 $omie->appSecret = $_ENV['SECRETS_MSC'];
    //                 $omie->target = 'MSC';
    //                 try{
    //                     $id = $this->databaseServices->isIssetOrder($decoded['event']['idPedido'], $omie->target);
    //                     if(is_string($id)){
    //                         throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos-SC. Erro: '.$id. ' - '.$current, 1030);
    //                         }elseif(empty($id)){
    //                         throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos-SC , ou já foi cancelado. - '.$current, 1030);
    //                     }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
    //                     }

    //                 }catch(PedidoInexistenteException $e){
    //                     throw new PedidoInexistenteException($e->getMessage());
    //                 }

    //                 //exclui pedido da base de dados caso seja uma venda excluída
    //                 if($decoded['topic'] === "VendaProduto.Excluida"){
    //                     try{                           
    //                         $message['order']['isdeleted'] = $this->databaseServices->excluiOrder($decoded['event']['idPedido'], $omie->target);

    //                         if(is_string($message['order']['isdeleted'])){
    //                             throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
    //                         }
    //                     }
    //                     catch(PedidoNaoExcluidoException $e)
    //                     {
    //                         throw new PedidoNaoExcluidoException($e->getMessage());
    //                     }
    //                 }
                            
    //                 //altera o pedido no banco para cancelado
    //                 try{
    //                     //Altera o pedido para cancelado no banco MPR
    //                     $altera = $this->databaseServices->alterOrder($decoded['event']['idPedido'], $omie->target);

    //                     if(is_string($altera)){
    //                         throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos-SC. Erro: '.$altera. 'em '.$current, 1030);                     
    //                     }

    //                     $message['order']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso em '.$current;
                        
    //                 }catch(PedidoCanceladoException $e){          
    //                     throw new PedidoCanceladoException($e->getMessage(), 1031);
    //                 }
                    
    //             break;
    //         }

            
    //         //busca o cnpj do cliente através do id do omie
    //         $cnpjClient = ($this->omieServices->clienteCnpjOmie($omie));
    //         //busca o contact id do cliente no P`loomes CRM através do cnpj do cliente no Omie ERP
    //         $contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient);
    //         //monta a mensadem para atualizar o card do ploomes
    //         if($message['order']['isdeleted']){
    //             $msg=[
    //                     'ContactId' => $contactId,
    //                     'Content' => 'Pedido ('.$decoded['event']['numeroPedido'].') EXCLUÍDO no Omie ERP em: '.$current,
    //                     'Title' => 'Pedido EXCLUIDO no Omie ERP'
    //                 ];
    //             $message['order']['deleted'] = "Pedido excluído no Omie ERP e na base de dados do sistema!";
    //         }else{
    //             $msg=[
    //                     'ContactId' => $contactId,
    //                     'Content' => 'Pedido ('.$decoded['event']['numeroPedido'].') cancelado no Omie ERP em: '.$current,
    //                     'Title' => 'Pedido Cancelado no Omie ERP'
    //                 ];
    //             $message['order']['deleted'] = "Pedido excluído no Omie ERP e na base de dados do sistema!";
    //         }

    //         //cria uma interação no card
    //         ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['interactionMessage'] = 'Integração de cancelamento/exclusão de Pedido concluída com sucesso!<br> Pedido ('.$decoded['event']['numeroPedido'].') foi cancelado/excluído no Omie ERP, no sistema de integração e interação criada no cliente id: '.$contactId.' - '.$current : throw new InteracaoNaoAdicionadaException('Não foi possível gravar a mensagem de nota cancelada no Ploomes CRM',1032);

    //     return $message;
    // }

    // public function alterOrderStage($json)
    // {
      
    //     $current = $this->current;
    //     $message = [];
    //     $decoded =json_decode($json, true);
    //     $omie = new stdClass();
    //     $omie->codCliente = $decoded['event']['idCliente'];
    //     $omie->appKey = $decoded['appKey'];
        
    //     if($decoded['topic'] !== 'VendaProduto.EtapaAlterada'){
    //         throw new WebhookReadErrorException('Não havia mudança de etapa no webhook - '.$current, 1040);
    //     }

    //     switch($decoded['appKey']){
    //         case 2337978328686: //MHL
    //             $omie->appSecret = $_ENV['SECRETS_MHL'];
    //             break;

    //         case 2335095664902: // MPR
    //             $omie->appSecret = $_ENV['SECRETS_MPR']; 
    //             break;

    //         case 2597402735928: // MSC
    //             $omie->appSecret = $_ENV['SECRETS_MSC'];
    //             break;
    //     }

    //     //busca o cnpj do cliente através do id do omie
    //     $cnpjClient = ($this->omieServices->clienteCnpjOmie($omie));
    //     //busca o contact id do cliente no P`loomes CRM através do cnpj do cliente no Omie ERP
    //     $contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient);
    //     //monta a mensadem para atualizar o card do ploomes
    //     $msg=[
    //         'ContactId' => $contactId,
    //         'Content' => 'Etapa do pedido ('.$decoded['event']['numeroPedido'].') ALTERADA no Omie ERP para '.$decoded['event']['etapaDescr'].' em: '.$current,
    //         'Title' => 'Etapa do pedido ALTERADA no Omie ERP'
    //     ];
    //     //cria uma interação no card
    //     ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['order']['interactionMessage'] = 'Etapa do pedido alterada com sucesso!<br> Etapa do pedido ('.$decoded['event']['numeroPedido'].') foi alterada no Omie ERP para '.$decoded['event']['etapaDescr'].'! - '.$current : throw new InteracaoNaoAdicionadaException('Não foi possível criar interação no Ploomes CRM ',1042);

    //     if ($decoded['event']['etapa'] === '60' && !empty($decoded['event']['codIntPedido'])){
            

    //         $orderId = $decoded['event']['codIntPedido'];
    //         $omie->lastOrderId = $orderId;
    //         $orderPloomes = $this->ploomesServices->requestOrder($omie);
    //         if($orderPloomes !== null && $orderPloomes[0]->Id == $orderId){
                
    //             $stageId= ['StageId'=>40011765];
    //             $stage = json_encode($stageId);
    //             ($this->ploomesServices->alterStageOrder($stage, $orderId))?$message['order']['alterStagePloomes'] = 'Estágio do pedido de venda do Ploomes CRM alterado com sucesso! \n Id Pedido Ploomes: '.$orderPloomes[0]->Id.' \n Card Id: '.$orderPloomes[0]->DealId.' \n omieOrderHandler - '.$current : $message['order']['alterStagePloomes'] = 'Não foi possível mudar o estágio do pedido no Ploomes CRM. Pedido não foi encontrado no Ploomes CRM. - omieOrderHandler - '.$current;
    //         }

    //         $message['order']['alterStagePloomes'] = 'Não foi possível mudar o estágio da venda no Ploomes CRM, possívelmente o pedido foi criado direto no Omie ERP. - omieOrderHandler - '.$current;

    //     }

    //     $message['order']['alterStage'] = 'Integração de mudança de estágio de pedido de venda no omie ERP concluída com sucesso!  - '.$current;

    //     return $message;

    // }
    
}