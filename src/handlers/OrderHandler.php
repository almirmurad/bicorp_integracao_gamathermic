<?php

namespace src\handlers;

use src\exceptions\PedidoInexistenteException;

use src\exceptions\WebhookReadErrorException;

use src\models\Webhook;

use src\models\Omie;
use src\models\Order;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
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
        $webhook = new stdClass();
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

    //PROCESSA E CRIA O PEDIDO.
    public function startProcess($json)
    {   
        //inicia o processo de crição de pedido, caso de certo retorna mensagem de success, e caso de erro retorna error
        $newOrder=[];
        
        try{
            //resposta do processo de inclusão 
            $res = Self::newOrder($json); 

            if(!isset($res['newOrder']['error'])){
                
                $newOrder['success'] = $res;
                
                //card processado pedido criado no Omie retorna mensagem newOrder para salvr no log
                return $newOrder; 
            }
            else{
                // se der erro finaliza o processo e lança excessão ao controller                        
                throw new WebhookReadErrorException('Erro ao gravar pedido: ' . $res['newOrder']['error'] . ' - ' . date('d/m/Y H:i:s'), 500);                    
            }

        }
        catch(PedidoInexistenteException $e)
        {
            //Caso de erro na inclusão do peddido monta a mensagem para atualizar o card do ploomes    
            $decoded = json_decode($json,true);
        
            $msg=[
                'DealId' => $decoded['New']['DealId'],
                'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$e->getMessage(),
                'Title' => 'Erro na integração'
            ];

            //cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: ' . $decoded['New']['Id'] . ' card nº: ' .$decoded['New']['DealId'] .' e client id: ' . $decoded['New']['ContactId'] . ' - '. $e->getMessage() . 'Mensagem enviada com sucesso em: '.date('d/m/Y : H:i:s') : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda do ploomes',500);
            throw new WebhookReadErrorException($e->getMessage());
        }                    
    }

    public function newOrder($json)
    {
        $m = [];
        $current = date('d/m/Y H:i:s');
        $message = [];
        $decoded = json_decode($json, true);

        //cria objeto order
        $order = new Order();            
        //order_24F97378-4AFA-446C-834C-FD5EC4A0453E = Campo Para Condicionais
        //order_6B1470FA-DAAF-4F88-AF61-F2FD99EDEE80 = Condição de Pagamento
        //order_943040FD-4DEF-4AEB-B21A-2190529FE4B9 = Número de Revisão da Proposta
        //order_DF2C63CB-3893-4211-91C9-6A2C4FE1D0CA = Em branco ?(AParentemente informações adicioanis da nota fiscal)
        //order_ACB7C14D-2E32-4FB1-BC18-181EC06593A0 = Atualizar Informações
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
        //order_C59D726E-A2A5-42B7-A18E-9898E12F203A // descrição do serviço
        $prop = [];
        foreach ($decoded['New']['OtherProperties'] as $key => $op) {
            $prop[$key] = $op;
        }
            
        $order->id = $decoded['New']['Id']; //Id da order
        $order->orderNumber = $decoded['New']['OrderNumber']; // numero da venda
        $order->contactId = $decoded['New']['ContactId']; // Id do Contato,
        
        $contact = $this->ploomesServices->getClientById($order->contactId);
        //busca o Id do cliente no contact do ploomes
        $ids = [];
        foreach($contact['OtherProperties'] as $op){
            foreach($op as $k => $v){
                if($v === "contact_6DB7009F-1E58-4871-B1E6-65534737C1D0"){
                    $id = $op['StringValue'];
                    $ids['IdGTC'] = $id;
                }
                if($v === "contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3"){
                    $id = $op['StringValue'];
                    $ids['IdEPT'] = $id;
                }
                if($v === "contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2"){
                    $id = $op['StringValue'];
                    $ids['IdSMN'] = $id;
                }
                if($v === "contact_07784D81-18E1-42DC-9937-AB37434176FB"){
                    $id = $op['StringValue'];
                    $ids['IdGSU'] = $id;
                }
            }
        }

        // Busca o CNPJ do contato 
        // ($contactCnpj = $this->ploomesServices->contactCnpj($order)) ? $contactCnpj : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Cliente não informado ou não cadastrado no Omie ERP. Id do card Ploomes CRM: '.$decoded['New']['DealId'].' e pedido de venda Ploomes CRM: '.$decoded['New']['LastOrderId'].'em'.$current; //cnpj do cliente
        $order->contactName = $decoded['New']['ContactName']; // Nome do Contato no Deal
        $order->personId = $decoded['New']['PersonId']; // Id do Contato
        $order->personName = $decoded['New']['PersonName']; // nome do contato
        $order->stageId = $decoded['New']['StageId']; // Estágio
        $order->dealId = $decoded['New']['DealId']; // Id do card
        $order->createDate = $decoded['New']['CreateDate']; // data de criação da order
        $order->ownerId = $decoded['New']['OwnerId']; // Responsável
        ($mailVendedor = $this->ploomesServices->ownerMail($order)) ? $mailVendedor: $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Não foi encontrado o email deste vendedor. Id do card Ploomes CRM: '.$decoded['New']['DealId'].' e pedido de venda Ploomes CRM: '.$decoded['New']['LastOrderId'].'em'.$current;
        $order->amount = $decoded['New']['Amount']; // Valor
        $order->ownerMail = $mailVendedor;

        //Encontra a base de faturamento                                                                            
        $order->baseFaturamento = $prop['order_94FD0EA3-9219-4E27-8BBC-87D7EB505CD9'];
        $omie = new Omie();
        switch ($order->baseFaturamento) {
            case '420197140':
                $order->baseFaturamentoTitle = 'ENGEPARTS';
                $order->idClienteOmie = $ids['IdEPT'];
                $omie->baseFaturamentoTitle = 'ENGEPARTS';
                $omie->target = 'EPT'; 
                $omie->ncc = $_ENV['NCC_EPT'];
                $omie->appSecret = $_ENV['SECRETS_EPT'];
                $omie->appKey = $_ENV['APPK_EPT'];
                break;

            case '420197141':
                $order->baseFaturamentoTitle = 'GAMATERMIC';
                $order->idClienteOmie = $ids['IdGTC'];
                $omie->baseFaturamentoTitle = 'GAMATERMIC'; 
                $omie->target = 'GTC'; 
                $omie->ncc = $_ENV['NCC_GTC'];
                $omie->appSecret = $_ENV['SECRETS_GTC'];
                $omie->appKey = $_ENV['APPK_GTC'];
                break;
                
            case '420197143':
                $order->baseFaturamentoTitle = 'SEMIN';
                $order->idClienteOmie = $ids['IdSMN'];
                $omie->baseFaturamentoTitle = 'SEMIN';
                $omie->target = 'SMN'; 
                $omie->ncc = $_ENV['NCC_SMN'];
                $omie->appSecret = $_ENV['SECRETS_SMN'];
                $omie->appKey = $_ENV['APPK_SMN'];
                break;
                
            case '420197142':
                $order->baseFaturamentoTitle = 'GSU';
                $order->idClienteOmie = $ids['IdGSU'];
                $omie->baseFaturamentoTitle = 'GSU';
                $omie->target = 'MHL'; 
                $omie->ncc = $_ENV['NCC_GSU'];
                $omie->appSecret = $_ENV['SECRETS_GSU'];
                $omie->appKey = $_ENV['APPK_GSU'];
                break;
                
                default:
                throw new PedidoInexistenteException ('Erro ao montar pedido para enviar ao Omie ERP: Base de faturamento não encontrada. Impossível fazer consultas no omie', 500);
                break;
        }
        //previsão de faturamento
        $order->previsaoFaturamento =(isset($prop['order_5ABC5118-2AA4-493A-B016-67E26C723DD1']) && !empty($prop['order_5ABC5118-2AA4-493A-B016-67E26C723DD1']))? $prop['order_5ABC5118-2AA4-493A-B016-67E26C723DD1'] : date('Y-m-d');//$m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Previsão de Faturamento não foi preenchida';
        //template id (tipo de venda produtos ou serviços)
        $order->templateId =(isset($prop['order_F90FC615-C3B1-4E9A-9061-88C0AF944CC5']) && !empty($prop['order_F90FC615-C3B1-4E9A-9061-88C0AF944CC5']))? $prop['order_F90FC615-C3B1-4E9A-9061-88C0AF944CC5'] : $m[] = 'Erro: não foi possível identificar o tipo de venda (Produtos ou serviços)';
        //numero do pedido do cliente (preenchido na venda) localizado em pedidos info. adicionais
        $order->numPedidoCliente = (isset($prop['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']) && !empty($prop['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']))?$prop['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']:null;//$m[] = 'Erro ao montar pedido para enviar ao Omie ERP: O número do Pedido do Cliente não foi preenchido';
        $order->descricaoServico = (isset($prop['order_C59D726E-A2A5-42B7-A18E-9898E12F203A']) && !empty($prop['order_C59D726E-A2A5-42B7-A18E-9898E12F203A']))?htmlspecialchars_decode(strip_tags($prop['order_C59D726E-A2A5-42B7-A18E-9898E12F203A'],'\n')):null;//$m[] = 'Erro ao montar pedido para enviar ao Omie ERP: O número do Pedido do Cliente não foi preenchido';
        //Numero pedido de compra (id da proposta) localizado em item da venda info. adicionais
        $order->numPedidoCompra = (isset($prop['order_4AF9E1C1-6DB9-45B2-89E4-9062B2E07B87']) && !empty($prop['order_4AF9E1C1-6DB9-45B2-89E4-9062B2E07B87'])? $prop['order_4AF9E1C1-6DB9-45B2-89E4-9062B2E07B87']: null); //$m[]='Erro ao montar pedido para enviar ao Omie ERP:  Não havia Número do Pedido de Compra');//em caso de obrigatoriedade deste campo $m[]='Erro ao criar pedido. Não havia Ordem de compra         //array de produtos da venda
        //id modalidade do frete
        if ((isset($prop['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D'])) && (!empty($prop['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D']) || $prop['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D'] === "0"))
        {
            $order->modalidadeFrete = $prop['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D'];
        }
        else{$order->modalidadeFrete = null;
        }//$m[]='Erro ao montar pedido para enviar ao Omie ERP:  Modalidade de Frete não informado';
        //projeto 
        $order->projeto = ($prop['order_BBBEB889-6888-4451-81A7-29AB821B1402']) ?? $m[]='Erro ao montar pedido para enviar ao Omie ERP: Não foi informado o Projeto';
        //observações da nota
        $order->notes = (isset($prop['order_F438939E-F11E-4024-8F3D-6496F2B11778']) ? htmlspecialchars_decode(strip_tags($prop['order_F438939E-F11E-4024-8F3D-6496F2B11778'])): null);  
        $order->idParcelamento = $prop['order_B14B38B7-FB43-4E8E-A57A-61EFC97725A6'] ?? null;//$m[]='Erro ao montar pedido para enviar ao Omie ERP: Não foi informado o código do parcelamento';
        //insere o projeto e retorna o id
        $id = $this->omieServices->insertProject($omie,  $order->projeto);
        $omie->codProjeto =(isset($id['codigo'])) ? $id['codigo'] : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: ' . $id['faultstring'];
        //pega o id do cliente do Omie através do CNPJ do contact do ploomes           
        // (!empty($idClienteOmie = $this->omieServices->clienteIdOmie($omie, $contactCnpj))) ? $order->idClienteOmie = $idClienteOmie : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Id do cliente não encontrado no Omie ERP! Id do card Ploomes CRM: '.$order->dealId.' e pedido de venda Ploomes CRM: '.$order->id.' em: '.$current;
        //pega o id do vendedor Omie através do email do vendedor do ploomes           
        (!empty($codVendedorOmie = $this->omieServices->vendedorIdOmie($omie, $mailVendedor))) ? $order->codVendedorOmie = $codVendedorOmie : null;//$m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Id do vendedor não encontrado no Omie ERP!Id do card Ploomes CRM: '.$order->dealId.' e pedido de venda Ploomes CRM: '.$order->id.' em: '.$current;
        
        //Array de detalhes do item da venda
        $arrayRequestOrder = $this->ploomesServices->requestOrder($order);
        //tipo da venda
        $type = match($order->templateId){
            '40130624' => "servicos",
            '40124278' => "produtos"
        };
        //verifica se é um serviço
        $isService = ($type === 'servicos') ? true : false;
        //id dos itens no omie registrados no ploomes
        $idItemOmie =  match(strtolower($order->baseFaturamentoTitle)){
            'gamatermic'=> 'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
            'semin'=> 'product_429C894A-708E-4125-A434-2A70EDCAFED6',
            'engeparts'=> 'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
            'gsu'=> 'product_08A41D8E-F593-4B74-8CF8-20A924209A09',
        };
        //se não houver erro nos dados
        if(!empty($m)){
            
            throw new PedidoInexistenteException($m[0],500);            
            
        }
        
        //separa e monta os arrays de produtos e serviços
        $productsOrder = []; 
        $det = [];  
        $det['ide'] = [];
        $det['produto'] = [];
        $opServices = [];
        $serviceOrder = [];
        $pu = [];
        $service = [];
        $produtosUtilizados = [];
        
        foreach($arrayRequestOrder['Products'] as $prdItem)
        {   
            foreach($prdItem['Product']['OtherProperties'] as $otherp){
                $opServices[$otherp['FieldKey']] = $otherp['ObjectValueName'] ?? 
                $otherp['BigStringValue'] ?? $otherp['StringValue'] ??  $otherp['IntegerValue'] ?? $otherp['DateTimeValue'];
            }
            //verifica se é venda de serviço 
            if($isService){
                //verifica se tem serviço com produto junto
                if($prdItem['Product']['Group']['Name'] !== 'Serviços'){
                    
                    //monts o produtos utilizados (pu)
                    $pu['nCodProdutoPU'] = $opServices[$idItemOmie];
                    $pu['nQtdePU'] = $prdItem['Quantity'];
                    
                    $produtosUtilizados[] = $pu;
                    
                }else{
                    
                    //monta o serviço
                    $service['nCodServico'] = $opServices[$idItemOmie];
                    $service['nQtde'] = $prdItem['Quantity'];
                    $service['nValUnit'] = $prdItem['UnitPrice'];
                    $service['cDescServ'] = $order->descricaoServico;
                    
                    $serviceOrder[] = $service;
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
        //verifica se é uma venda de serviço ou produto pra incluir no modulo certo do omie
        if($isService){
            
            //inclui a ordem de serviço
            $incluiOS = $this->omieServices->criaOS($omie, $order, $serviceOrder, $produtosUtilizados);

            /**
             * array de retorno da inclusão de OS
            * [cCodIntOS] => SRV/404442017
            * [nCodOS] => 6992578495
            * [cNumOS] => 000000000000018
            * [cCodStatus] => 0
            * [cDescStatus] => Ordem de Serviço adicionada com sucesso!
            */

            //se incluiu a OS
            if(isset($incluiOS['cCodStatus']) && $incluiOS['cCodStatus'] == "0"){

                $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' em: '.$current ;

                //monta mensagem pra enviar ao ploomes
                $msg=[
                    'DealId' => $order->dealId,
                    'Content' => 'Ordem de Serviço ('.intval($incluiOS['cNumOS']).') criada no OMIE via API BICORP na base '.$order->baseFaturamentoTitle.'.',
                    'Title' => 'Ordem de Serviço Criada'
                ];

                // //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' e mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);

                $message['winDeal']['incluiOS']['Success'] = $incluiOS['cDescStatus']. 'Numero: ' . intval($incluiOS['cNumOS']);
            }else{
                
                $deleteProject = $this->omieServices->deleteProject($omie);
                $msg=[
                    'DealId' => $order->dealId,
                    'Content' => 'Ordem de Serviço não pode ser criado no OMIE ERP. '.$incluiOS['faultstring'],
                    'Title' => 'Erro na integração'
                ];
            
                //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis, pedido: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' - '.$incluiOS['faultstring']. 'Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);


                if($deleteProject['codigo'] === "0"){

                    $message['winDeal']['error'] ='Não foi possível gravar o Ordem de Serviço no Omie! '. $incluiOS['faultstring'] . $deleteProject['descricao'];

                }else{

                    $message['winDeal']['error'] ='Não foi possível gravar o Ordem de Serviço no Omie! '. $incluiOS['faultstring'] . $deleteProject['faultstring'];

                }
        
            }

        }else{

            $incluiPedidoOmie = $this->omieServices->criaPedidoOmie($omie, $order, $productsOrder);

            //verifica se criou o pedido no omie
            if (isset($incluiPedidoOmie['codigo_status']) && $incluiPedidoOmie['codigo_status'] == "0") 
            {
                $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).' e mensagem enviada com sucesso em: '.$current;

                //monta a mensagem para atualizar o card do ploomes
                $msg=[
                    'DealId' => $order->dealId,
                    'Content' => 'Venda ('.intval($incluiPedidoOmie['numero_pedido']).') criada no OMIE via API BICORP na base '.$order->baseFaturamentoTitle.'.',
                    'Title' => 'Pedido Criado'
                ];
            
                // //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).' e mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
                
                $message['winDeal']['returnPedidoOmie'] ='Pedido criado no Omie via BICORP INTEGRAÇÃO pedido numero: '.intval($incluiPedidoOmie['numero_pedido']);

            }else{
                
               
                $deleteProject = $this->omieServices->deleteProject($omie); 
                
                //monta a mensagem para atualizar o card do ploomes
                $msg=[
                    'DealId' => $order->dealId,
                    'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$incluiPedidoOmie['faultstring'],
                    'Title' => 'Erro na integração'
                ];
            
                //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' - '.$incluiPedidoOmie['faultstring']. 'Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);

                
                if($deleteProject['codigo'] === "0"){

                    $message['winDeal']['error'] ='Não foi possível gravar o peddido no Omie! '. $incluiPedidoOmie['faultstring'] . $deleteProject['descricao'];

                }else{

                    $message['winDeal']['error'] ='Não foi possível gravar o peddido no Omie! '. $incluiPedidoOmie['faultstring'] . $deleteProject['faultstring'];

                }
            }  
        }           

        return $message;
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
    //     $current = date('d/m/Y H:i:s');
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