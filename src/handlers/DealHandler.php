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
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;

use src\workers\Workers;
use stdClass;


class DealHandler
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
            // print_r($json);
            // exit;
            
            $winDeal=[];
            // if(!isset($winDeal['winDeal']['error'])){
            try{

                $res = Self::winDeal($json);
                if(!isset($res['winDeal']['error'])){
                    // $status = 3; //Success
                    // $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                        $winDeal['success'] = $res;
                        
                        return $winDeal;//card processado pedido criado no Omie retorna mensagem winDeal para salvr no log

                }
                else{
                    //     //$status = 4; //falhou
                    //     //$alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                        
                    //     // $reprocess = Self::reprocessWebhook($hook);

                    //     // if($reprocess['winDeal']['error']){

                    //     //     $log = $this->databaseServices->registerLog($hook['id'], $reprocess['winDeal']['error'], $hook['entity']); 

                            
                    throw new WebhookReadErrorException('Erro ao gravar pedido: ' . $res['winDeal']['error'] . ' - ' . date('d/m/Y H:i:s'), 500);
                            
                    //     return $winDeal;

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
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: ' . $decoded['New']['LastOrderId'] . ' card nº: ' .$decoded['New']['Id'] .' e client id: ' . $decoded['New']['ContactId'] . ' - '. $e->getMessage() . 'Mensagem enviada com sucesso em: '.date('d/m/Y : H:i:s') : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda do ploomes',500);
                throw new WebhookReadErrorException($e->getMessage());
            }
    
            
        
                 
    }

    //REPROCESSA O CARD COM FALHA
    // public function reprocessWebhook($rHook){
        
    //     $status = 4;//falhou
    //     //$hook = $this->databaseServices->getWebhook($status, $rHook['entity']);
    //     //$json = $hook['json'];
    //     $status = 2; //processando
    //     $alterStatus = $this->databaseServices->alterStatusWebhook($rHook['id'], $status);
    //     $rHook['reprocess'] = 1;
        
    //     if($alterStatus){
            
    //         $winDeal = Self::winDeal($rHook);
            
    //         if(!isset($winDeal['winDeal']['error'])){
    //             $status = 3; //Sucesso
    //             $alterStatus = $this->databaseServices->alterStatusWebhook($rHook['id'], $status);
    //             if($alterStatus){
    //                 return $winDeal;//card processado pedido criado no Omie retorna mensagem winDeal para salvr no log
    //             }

    //         }else{
    //             $status = 4; //falhou com mensagem
    //             $alterStatus = $this->databaseServices->alterStatusWebhook($rHook['id'], $status);
    //             //for($i=0;$i<1;$i++){
                                  
    //             // $reprocess = $this->reprocessWebhook();
                
    //             //throw new WebhookReadErrorException($winDeal['winDeal']['error'],500);
    //             // if(!isset($reprocess['winDeal']['error'])){
    //             //     return $reprocess;
    //             // }
                
    //             // // var_dump($reprocess);
    //             // // exit;
    //             // // $i++;
    //             // // }
    //             return $winDeal;
    //         }
    //     }
        
    // }
 
    public function winDeal($json){

        $m = [];
        $current = date('d/m/Y H:i:s');
        $message = [];
        $decoded = json_decode($json, true);   

        if (isset($decoded['Action']) && $decoded['Action'] == "Win" && !empty($decoded['New']['LastOrderId']) && !empty($decoded['New']['LastQuoteId'])) 
        {    
            
            
            //cria objeto deal
            $deal = new Deal();            
            /************************************************************
             *                   Other Properties                        *
             *                                                           *
             * No webhook do Card pegamos os campos de Other Properies   *
             * para encontrar a chave da base de faturamento do Omie     *
             *                                                           *
             *************************************************************/
            $prop = [];
            foreach ($decoded['New']['OtherProperties'] as $key => $op) {
                $prop[$key] = $op;
            }
            
            //infos do Deal new
            // //$deal->attachmentsItems = $decoded['New']['AttachmentsItems'];
            // //$deal->collaboratingUsers = (isset($decoded['New']['CollaboratingUsers'][0]['UserId'])) ? $decoded['New']['CollaboratingUsers'][0]['UserId'] : 'Não definido'; // Usuários colaboradores
            // //$deal->contacts = $decoded['New']['Contacts']; // Contatos relacionados
            // //$deal->contactsProducts = $decoded['New']['ContactsProducts']; // Produtos de cliente
            // // Base de Faturamento Fiel
            //  $deal->baseFaturamento = (isset($prop['deal_A965E8F5-EF81-4CF3-939D-1D7FE6F1556C']) && !empty($prop['deal_A965E8F5-EF81-4CF3-939D-1D7FE6F1556C']))? $prop['deal_A965E8F5-EF81-4CF3-939D-1D7FE6F1556C'] : $m[] = 'Base de faturamento inexistente';
            // // Base de Faturamento Teste
            // //$deal->baseFaturamento = (isset($prop['deal_70C6418B-B6A9-4026-9A30-C838F3793244']) && !empty($prop['deal_70C6418B-B6A9-4026-9A30-C838F3793244']))? $prop['deal_70C6418B-B6A9-4026-9A30-C838F3793244'] : $m[] = 'Base de faturamento inexistente';
            // $deal->previsaoFaturamento =(isset($quoteOtherProperties['order_5ABC5118-2AA4-493A-B016-67E26C723DD1']) && !empty($quoteOtherProperties['order_5ABC5118-2AA4-493A-B016-67E26C723DD1']))? $quoteOtherProperties['order_5ABC5118-2AA4-493A-B016-67E26C723DD1'] : "";//$m[] = 'Previsão de Faturamento inexistente';
            
            // Fim de $prop Outras Propriedades //
            //$deal->products = $decoded['New']['Products']; //Produtos relacionados
            //$products = $deal->products; //Produtos relacionados
            // $produtos = [];
            // foreach ($products as $prdt) {
                //     $produtos['codigo_produto'] = $prdt['ProductId'];
                // }
                // $produtos['codigo_produto'] = '3448900782';
                // $produtos['quantidade'] = '1';
                // $produtos['valor_unitario'] = 150;
                // $deal->products = $produtos; //Produtos relacionados
                //$deal->tags = (isset($decoded['New']['Tags'][0]['TagId'])) ? $decoded['New']['Tags'][0]['TagId'] : 'Não definido'; //Marcadores
                $deal->id = $decoded['New']['Id']; //Id do Deal
                $deal->title = $decoded['New']['Title']; // Título do Deal
                $deal->contactId = $decoded['New']['ContactId']; // Contatos relacionados
                
            
                // Busca o CNPJ do contato 
                ($contactCnpj = $this->ploomesServices->contactCnpj($deal)) ? $contactCnpj : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Cliente não informado ou não cadastrado no Omie ERP. Id do card Ploomes CRM: '.$decoded['New']['Id'].' e pedido de venda Ploomes CRM: '.$decoded['New']['LastOrderId'].'em'.$current; //cnpj do cliente
                
                $deal->contactName = $decoded['New']['ContactName']; // Nome do Contato no Deal
                $deal->personId = $decoded['New']['PersonId']; // Id do Contato
                // $deal->personName = $decoded['New']['PersonName']; // Nome do contato
            $deal->pipelineId = $decoded['New']['PipelineId']; // Funil
            $deal->stageId = $decoded['New']['StageId']; // Estágio
            $deal->statusId = $decoded['New']['StatusId']; // Situação
            // $deal->firstTaskId = $decoded['New']['FirstTaskId'];
            // $deal->firstTaskDate = $decoded['New']['FirstTaskDate'];
            // $deal->firstTaskNoTime = $decoded['New']['FirstTaskNoTime'];
            // $deal->hasScheduledTasks = $decoded['New']['HasScheduledTasks']; //Possui tarefas agendadas
            // $deal->tasksOrdination = $decoded['New']['TasksOrdination'];
            // $deal->contactProductId = $decoded['New']['ContactProductId']; // Produtos de cliente
            $deal->lastQuoteId = $decoded['New']['LastQuoteId']; // Proposta
            // $deal->isLastQuoteApproved = $decoded['New']['IsLastQuoteApproved']; // Proposta aprovada
            $deal->wonQuoteId = $decoded['New']['WonQuoteId']; // Proposta ganha
            $deal->wonQuote = $decoded['New']['WonQuote']; // Proposta ganha
            $deal->lastStageId = $decoded['New']['LastStageId'];
            // $deal->lossReasonId = $decoded['New']['LossReasonId']; // Motivo de perda
            // $deal->originId = $decoded['New']['OriginId']; // Origem
            
            $deal->ownerId = $decoded['New']['OwnerId']; // Responsável
            ($mailVendedor = $this->ploomesServices->ownerMail($deal)) ? $mailVendedor: $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Não foi encontrado o email deste vendedor. Id do card Ploomes CRM: '.$decoded['New']['Id'].' e pedido de venda Ploomes CRM: '.$decoded['New']['LastOrderId'].'em'.$current;
            //$mailVendedor = 'vendas9@fielpapeis.com.br';
            
            // $deal->startDate = $decoded['New']['StartDate']; // Início
            $deal->finishDate = $decoded['New']['FinishDate']; // Término
            // $deal->currencyId = $decoded['New']['CurrencyId']; // Moeda
            $deal->amount = $decoded['New']['Amount']; // Valor
            // $deal->startCurrencyId = $decoded['New']['StartCurrencyId'];
            // $deal->startAmount = $decoded['New']['StartAmount'];
            // $deal->read = $decoded['New']['Read'];
            // $deal->lastInteractionRecordId = $decoded['New']['LastInteractionRecordId']; // Último contato
            $deal->lastOrderId = $decoded['New']['LastOrderId']; // Última venda
            //$deal->lastOrderIdOld = $decoded['Old']['LastOrderId']; // Última venda
            // $deal->daysInStage = $decoded['New']['DaysInStage']; // Dias no estágio
            // $deal->hoursInStage = $decoded['New']['HoursInStage']; // Horas no estágio
            // $deal->length = $decoded['New']['Length']; // Duração
            // $deal->createImportId = $decoded['New']['CreateImportId'];
            // $deal->updateImportId = $decoded['New']['UpdateImportId'];
            // $deal->leadId = $decoded['New']['LeadId']; // Lead origem
            // $deal->originDealId = $decoded['New']['OriginDealId']; // Negócio origem
            // $deal->reevId = $decoded['New']['ReevId'];
            $deal->creatorId = $decoded['New']['CreatorId']; // Criador
            $deal->updaterId = $decoded['New']['UpdaterId']; // Último atualizador
            $deal->createDate = $decoded['New']['CreateDate']; // Data de criação
            // $deal->lastUpdateDate = $decoded['New']['LastUpdateDate']; // Data da última atualizaçãop
            // $deal->lastDocumentId = $decoded['New']['LastDocumentId']; // Último documento
            // $deal->dealNumber = $decoded['New']['DealNumber']; // Número
            // $deal->importationIdCreate = $decoded['New']['ImportationIdCreate']; // Id da importação de criação (novo)
            // $deal->importationIdUpdate = $decoded['New']['ImportationIdUpdate']; // Id da importação de atualização (novo)
            // $deal->publicFormIdCreate = $decoded['New']['PublicFormIdCreate']; // Id do formulário externo de criação
            // $deal->publicFormIdUpdate = $decoded['New']['PublicFormIdUpdate']; // Id do formulário externo de atualização
            //$deal->webhookId = $webhook['id']; //inclui o id do webhook no deal
                        
            /**************************************************** 
             *        busca dados da venda no ploomes            *
             *                                                   *
             * Na venda encontramos o array de itens da venda    * 
             * Montamos o det (array de items da venda no omie)  *
             *                                                   *
             *****************************************************/
            (!empty($arrayRequestOrder = $this->ploomesServices->requestOrder($deal))) ? $arrayRequestOrder : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Venda ['. $deal->lastOrderId .'] não foi localizada no Ploomes CRM';
            
            foreach ($arrayRequestOrder['OtherProperties'] as $ops) {
                
                $arrayRequestOrder[$ops['FieldKey']] = $ops['ObjectValueName'] ?? 
                $ops['BigStringValue'] ?? $ops['StringValue'] ??  $ops['IntegerValue'] ?? $ops['DateTimeValue'];
                
            }
            
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
            //order_F438939E-F11E-4024-8F3D-6496F2B11778 // dados adicionais NF
            //order_1268DD4B-1E32-4CCA-A208-DCA5693613E8 // cod da proposta
            //order_943040FD-4DEF-4AEB-B21A-2190529FE4B9 // num de revisão da proposta
            //order_E768DCD5-D0B0-4417-9F58-6A4333C1846C// valor anterior da venda

           

             /**************************************************** 
             *        Encontra a base de faturamento             *
             *                                                   *
             * NO webhook do Card pegamos os dados do checklist  * 
             * para encontrar a base de faturamento do Omie      *
             *                                                   *
            *****************************************************/
            $deal->baseFaturamento = strtolower($arrayRequestOrder['order_94FD0EA3-9219-4E27-8BBC-87D7EB505CD9']);
            $omie = new Omie();
            switch ($deal->baseFaturamento) {
                case 'gamatermic':
                    $deal->baseFaturamentoTitle = 'Manos PR';
                    $omie->baseFaturamentoTitle = 'Manos PR'; 
                    $omie->target = 'MPR'; 
                    $omie->ncc = $_ENV['NCC_MPR'];
                    $omie->appSecret = $_ENV['SECRETS_MPR'];
                    $omie->appKey = $_ENV['APPK_MPR'];
                    break;
                    
                case 'semin':
                    $deal->baseFaturamentoTitle = 'Manos SC';
                    $omie->baseFaturamentoTitle = 'Manos SC';
                    $omie->target = 'MSC'; 
                    $omie->ncc = $_ENV['NCC_MSC'];
                    $omie->appSecret = $_ENV['SECRETS_MSC'];
                    $omie->appKey = $_ENV['APPK_MSC'];
                    break;
                    
                case 'engeparts':
                    $deal->baseFaturamentoTitle = 'Engeparts';
                    $omie->baseFaturamentoTitle = 'Engeparts';
                    $omie->target = 'MHL'; 
                    $omie->ncc = $_ENV['NCC_DEMO'];
                    $omie->appSecret = $_ENV['SECRETS_DEMO'];
                    $omie->appKey = $_ENV['APPK_DEMO'];
                    break;

                case 'gsu':
                    $deal->baseFaturamentoTitle = 'Engeparts';
                    $omie->baseFaturamentoTitle = 'Engeparts';
                    $omie->target = 'MHL'; 
                    $omie->ncc = $_ENV['NCC_DEMO'];
                    $omie->appSecret = $_ENV['SECRETS_DEMO'];
                    $omie->appKey = $_ENV['APPK_DEMO'];
                    break;
                    
                    default:
                    throw new PedidoInexistenteException ('Erro ao montar pedido para enviar ao Omie ERP: Base de faturamento não encontrada. Impossível fazer consultas no omie', 500);
                    break;
                }
            //     print 'aqui';
            // print_r($omie);
            // //print_r($serviceOrder);
            // exit;

            //previsão de faturamento
            $deal->previsaoFaturamento =(isset($arrayRequestOrder['order_5ABC5118-2AA4-493A-B016-67E26C723DD1']) && !empty($arrayRequestOrder['order_5ABC5118-2AA4-493A-B016-67E26C723DD1']))? $arrayRequestOrder['order_5ABC5118-2AA4-493A-B016-67E26C723DD1'] : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Previsão de Faturamento não foi preenchida';
            //numero do pedido do cliente (preenchido na venda) localizado em pedidos info. adicionais
            $deal->numPedidoCliente = (isset($arrayRequestOrder['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']) && !empty($arrayRequestOrder['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']))?$arrayRequestOrder['order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A']:$m[] = 'Erro ao montar pedido para enviar ao Omie ERP: O número do Pedido do Cliente não foi preenchido';
            //Numero pedido de compra (id da proposta) localizado em item da venda info. adicionais
            $deal->numPedidoCompra = (isset($arrayRequestOrder['OriginQuoteId']) && !empty($arrayRequestOrder['OriginQuoteId'])?$arrayRequestOrder['OriginQuoteId']:$m[]='Erro ao montar pedido para enviar ao Omie ERP:  Não havia Número do Pedido de Compra');//em caso de obrigatoriedade deste campo $m[]='Erro ao criar pedido. Não havia Ordem de compra         //array de produtos da venda
            //id modalidade do frete
            $deal->modalidadeFrete =  (isset($arrayRequestOrder['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D']) && !empty($arrayRequestOrder['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D']))?$arrayRequestOrder['order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D']:$m[]='Erro ao montar pedido para enviar ao Omie ERP:  Modalidade de Frete não informado';
            //projeto 
            $deal->projeto = ($arrayRequestOrder['order_BBBEB889-6888-4451-81A7-29AB821B1402']) ?? $m[]='Erro ao montar pedido para enviar ao Omie ERP: Não foi informado o Projeto';
            //observações da nota
            $notes = strip_tags($arrayRequestOrder['order_F438939E-F11E-4024-8F3D-6496F2B11778']) ?? null;
            

            $id = $this->omieServices->insertProject($omie,  $deal->projeto);
             $omie->codProjeto =(isset($id['codigo'])) ? $id['codigo'] : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: ' . $id['faultstring'];

                
            //array de produtos da venda
            $productsRequestOrder = $arrayRequestOrder['Products'];
                // print_r($productsRequestOrder);
                // exit;
            //Array de detalhes do item da venda
            $det = [];
            $productsOrder = [];
            $serviceOrder = [];
            $isService = false;         
            foreach ($productsRequestOrder as $prdItem) { 
                
                $groupPrd = $prdItem['Product']['Group']['Name'];
                $isService = ($groupPrd === 'Serviços') ? true : false;     
                if($isService){
                    $idServiceOmie =  match($deal->baseFaturamento){
                        'gamatermic'=> 'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
                        'semin'=> 'product_429C894A-708E-4125-A434-2A70EDCAFED6',
                        'engeparts'=> 'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
                        'gsu'=> 'product_08A41D8E-F593-4B74-8CF8-20A924209A09',
                    };
                    foreach ($prdItem['Product']['OtherProperties'] as $prodOps) {
                
                        $prdItem[$prodOps['FieldKey']] =  
                         $prodOps['StringValue'];
                        
                    }
                    $service['nCodServico'] = $prdItem[$idServiceOmie];
                    $service['nQtde'] = $prdItem['Quantity'];
                    $service['nValUnit'] = $prdItem['UnitPrice'];

                    $serviceOrder[] = $service;
                }else{

                    $det['ide'] = [];
                    
                    $det['ide']['codigo_item_integracao'] = $prdItem['Id'];
                    $det['produto'] = [];
                    $idPrd = $prdItem['Product']['Code'];     
                    //encontra o id do produto no omie atraves do Code do ploomes (é necessário pois cada base omie tem código diferente pra cada item)
                    (!empty($idProductOmie = $this->omieServices->buscaIdProductOmie($omie, $idPrd))) ? $idProductOmie : $m[]='Erro ao montar pedido para enviar ao Omie ERP: Id do Produto inexistente no Omie ERP. Id do card Ploomes CRM: '.$deal->id.' e pedido de venda Ploomes CRM: '.$deal->lastOrderId.'em'.$current;
                    $det['produto']['codigo_produto'] = $idProductOmie;//mudei aqui de $idproductOmie para $idPrd
                    $det['produto']['quantidade'] = $prdItem['Quantity'];
                    //$det['produto']['tipo_desconto'] = 'P';
                    //$dicount =$prdItem['Discount'] ?? 0;
                    //$det['produto']['percentual_desconto'] = number_format($dicount, 2, ',', '.');
                    $det['produto']['valor_unitario'] = $prdItem['UnitPrice'];
                    $det['inf_adic'] = [];
                    $det['inf_adic']['numero_pedido_compra'] = $deal->numPedidoCompra;
                    $det['inf_adic']['item_pedido_compra'] =$prdItem['Ordination']+1;
                    
                    $productsOrder[] = $det;
                }
            }
            
            /****************************************************************
            *                          Request Quote                        *
            *                                                               *
            * Busca os dados da proposta (quote) para pegar a observação    *
            * e o parcelamento escolhido em campos personalizados do ploomes*
            *                                                               *
            *****************************************************************/
            
            // (!empty($deal->lastQuoteId)) ? $quote = $this->ploomesServices->requestQuote($deal): $m[] = 'Não havia uma proposta no card '. $deal->id . ' em: '.$current;
            // $quoteOtherProperties = [];
            // foreach ($quote['OtherProperties'] as $ops) {   
            //     $quoteOtherProperties[$ops['FieldKey']]= $ops['StringValue'] ?? $ops['ObjectValueName'];         
            // }
            //$deal->baseFaturamento = strtolower($quoteOtherProperties['quote_E53A6847-1AE1-4C30-8323-7AACFCC23723']);
        //    $deal->numPedidoCliente = (isset($arrayRequestOrder['OrderNumber']) && !empty($arrayRequestOrder['OrderNumber'])?$arrayRequestOrder['OrderNumber']:null);//em caso de obrigatoriedade deste campo $m[]='Erro ao criar pedido. Não havia numero pedido do Cliente

        //    $productsRequestOrder = $arrayRequestOrder['Products'];
           
            //Array de detalhes do item da venda
            // $det = [];
            // $productsOrder = [];
            // foreach ($productsRequestOrder as $prdItem) { 
                
            //     $det['ide'] = [];
                
            //     $det['ide']['codigo_item_integracao'] = $prdItem['Id'];
            //     $det['produto'] = [];
            //     $idPrd = $prdItem['Product']['Code'];              
            //     //encontra o id do produto no omie atraves do Code do ploomes (é necessário pois cada base omie tem código diferente pra cada item)
            //     (!empty($idProductOmie = $this->omieServices->buscaIdProductOmie($omie, $idPrd))) ? $idProductOmie : $m[] = 'Id do Produto inexistente no Omie ERP. Id do card Ploomes CRM: '.$deal->id.' e pedido de venda Ploomes CRM: '.$deal->lastOrderId.'em'.$current;
                
            //     //$det['produto']['codigo_produto_integracao'] =$prdItem['ProductId'];
            //     $det['produto']['codigo_produto'] =$idProductOmie;
            //     //6879399630;//teste $idProductOmie;//6879399626
            //     $det['produto']['quantidade'] = $prdItem['Quantity'];
            //     $det['produto']['tipo_desconto'] = 'P';
            //     $det['produto']['percentual_desconto'] = $prdItem['Discount'];
            //     $det['produto']['valor_unitario'] = $prdItem['UnitPrice'];
            //     $det['inf_adic'] = [];
            //     $det['inf_adic']['numero_pedido_compra'] = $ocCliente ?? "0";
            //     $det['inf_adic']['item_pedido_compra'] =$prdItem['Ordination']+1;//num sequencial dos itens no pedido de compra

            //     $productsOrder[] = $det;
            // }
            
            //busca Observação da Proposta (Quote)
            
            //busca Parcelamento na Proposta (Quote) obrigatório
            //$quote['OtherProperties'][0]['ObjectValueName']
            // ($intervalo = $quote['OtherProperties'][0]['ObjectValueName']) ? $intervalo : $m[] = 'Prazo de pagamento não foi informado na proposta. Id do card Ploomes CRM: '.$deal->id.' e pedido de venda Ploomes CRM: '.$deal->lastOrderId.' em: '.$current;
            //verifica se exixtem parcelas ou se é a vista
            
            // $parcelamento = $intervalo;
            
            //installments é o parcelamento padrão do ploomes
            //print_r(count($quote['value'][0]['Installments']));
            // if(isset($rHook['reprocess']) && $rHook['reprocess'] == 1){
            //     $omie->reprocess = 1;
            // }
            
            //pega o id do cliente do Omie através do CNPJ do contact do ploomes           
            (!empty($idClienteOmie = $this->omieServices->clienteIdOmie($omie, $contactCnpj))) ? $idClienteOmie : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Id do cliente não encontrado no Omie ERP! Id do card Ploomes CRM: '.$deal->id.' e pedido de venda Ploomes CRM: '.$deal->lastOrderId.' em: '.$current;
            
            //pega o id do vendedor Omie através do email do vendedor do ploomes           
            (!empty($codVendedorOmie = $this->omieServices->vendedorIdOmie($omie, $mailVendedor))) ? $codVendedorOmie : $m[] = 'Erro ao montar pedido para enviar ao Omie ERP: Id do vendedor não encontrado no Omie ERP!Id do card Ploomes CRM: '.$deal->id.' e pedido de venda Ploomes CRM: '.$deal->lastOrderId.' em: '.$current;
            // $codVendedorOmie = 4216876829;
            
            // busca codigo do Projeto no Omie
            
            // (($codProjeto = $this->omieServices->buscaIdProjetoOmie($omie,$deal->projeto))? $codProjeto : $m[] = 'Projeto não encontrado no Omie ERP ou está inativo! Card número: '.$deal->id.' Pedido de venda número '.$deal->lastOrderId.' data: '.$current);
            
            // if($deal->projeto !== null){
                
            //     ($codProjeto = $this->omieServices->buscaIdProjetoOmie($omie,$deal->projeto))? $codProjeto : null;
            //     $omie->codProjeto = $codProjeto;
            // }
            
            // if(!empty($m)){

            //     $status = 4;
            //     $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            //     $log = $this->databaseServices->registerLog($webhook['id'], $m[0], $decoded['Entity']);

            //     if($alterStatus && $log){

            //         //monta a mensagem com erro para atualizar o card do ploomes
            //     $msg=[
            //         'ContactId' => $deal->contactId,
            //         'DealId' => $deal->id ?? null,
            //         'Content' => 'Erro ao criar pedido no OMIE ERP  na base '.$deal->baseFaturamentoTitle.' via API BICORP. Mensagem : '.$m[0],
            //         'Title' =>'Erro ao criar pedido'
            //     ];
            
            //     //cria uma interação no card
            //     ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Mensagem de erro enviada com sucesso! Pedido Ploomes: '.$deal->lastOrderId.' card nº: '.$deal->id.' e client id: '.$deal->contactId.' Pedido não foi gravado no Omie ERP em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem de erro na venda',500);
            
            //     }

            //     throw new WebhookReadErrorException('Erro ao montar o pedido pra enviar ao omie:'.$m[0],500);
            // }
            
            // print 'deal: '.PHP_EOL; 
            // print_r($deal);
            // print 'quote: '.PHP_EOL;
            // print_r($quote);
            // print 'quoteOtherProperties: '.PHP_EOL;
            // print_r($quoteOtherProperties);
            // print 'omie: '.PHP_EOL;
            // print_r($omie);
            // print 'arrayRequestOrder: '.PHP_EOL;
            // print_r($arrayRequestOrder);
            // print 'productsOrder: '.PHP_EOL;
            // print_r($productsOrder);
            // print 'Mail Vendedor: '.$mailVendedor.PHP_EOL;
            // print 'CNPJ Cliente: '.$contactCnpj.PHP_EOL;
            // print 'Id cliente Omie: '.$idClienteOmie.PHP_EOL;
            // print 'Id Vendedor Omie: '. $codVendedorOmie.PHP_EOL;
            // print 'Id Projeto: '.$deal->projeto.PHP_EOL;
             $parcelamento = '21/28/35';
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

            if(!empty($m)){
                
                throw new PedidoInexistenteException($m[0],500);
            //     $status = 4;
            //     $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            //     $log = $this->databaseServices->registerLog($webhook['id'], $m[0], $decoded['Entity']);
            
            //     if($alterStatus && $log){
                
            //         //monta a mensagem com erro para atualizar o card do ploomes
            //     $msg=[
            //         'ContactId' => $deal->contactId,
            //         'DealId' => $deal->id ?? null,
            //         'Content' => 'Erro ao criar pedido no OMIE ERP  na base '.$deal->baseFaturamentoTitle.' via API BICORP. Mensagem : '.$m[0],
            //         'Title' =>'Erro ao criar pedido'
            //     ];
               
            //     //cria uma interação no card
            //     ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Mensagem de erro enviada com sucesso! Pedido Ploomes: '.$deal->lastOrderId.' card nº: '.$deal->id.' e client id: '.$deal->contactId.' Pedido não foi gravado no Omie ERP em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem de erro na venda ',500);
            
            //     }
            

            }
           
            if($isService){

                $incluiOS = $this->omieServices->criaOS($omie, $idClienteOmie, $deal, $serviceOrder, $codVendedorOmie, $notes, $parcelamento);

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
            }
            

            $incluiPedidoOmie = $this->omieServices->criaPedidoOmie($omie, $idClienteOmie, $deal, $productsOrder, $codVendedorOmie, $notes, $parcelamento);


            //verifica se criou o pedido no omie
            if (isset($incluiPedidoOmie['codigo_status']) && $incluiPedidoOmie['codigo_status'] == "0") {

                //monta a mensagem para atualizar o card do ploomes
                $msg=[
                    'ContactId' => $deal->contactId,
                    'DealId' => $deal->id ?? null,
                    'Content' => 'Venda ('.intval($incluiPedidoOmie['numero_pedido']).') criada no OMIE via API BICORP na base '.$deal->baseFaturamentoTitle.'.',
                    'Title' => 'Pedido Criado'
                ];
               
                //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$deal->lastOrderId.' card nº: '.$deal->id.' e client id: '.$deal->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).' e mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
                
                $message['winDeal']['returnPedidoOmie'] ='Pedido criado no Omie via BICORP INTEGRAÇÃO pedido numero: '.intval($incluiPedidoOmie['numero_pedido']);
                //inclui o id do pedido no omie na tabela deal
                // if($incluiPedidoOmie['codigo_pedido']){
                //     //salva um deal no banco
                //     $deal->omieOrderId = $incluiPedidoOmie['codigo_pedido'];
                //     $dealCreatedId = $this->databaseServices->saveDeal($deal);   
                //     $message['winDeal']['dealMessage'] ='Id do Deal no Banco de Dados: '.$dealCreatedId;  
                //     if($dealCreatedId){

                //         $omie->idOmie = $deal->omieOrderId;
                //         $omie->codCliente = $idClienteOmie;
                //         $omie->codPedidoIntegracao = $deal->lastOrderId;
                //         $omie->numPedidoOmie = intval($incluiPedidoOmie['numero_pedido']);
                //         $omie->codClienteIntegracao = $deal->contactId;
                //         $omie->dataPrevisao = $deal->finishDate;
                //         $omie->codVendedorOmie = $codVendedorOmie;
                //         $omie->idVendedorPloomes = $deal->ownerId;   
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
                        'ContactId' => $deal->contactId,
                        'DealId' => $deal->id ?? null,
                        'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$incluiPedidoOmie['faultstring'],
                        'Title' => 'Erro na integração'
                    ];
                   
                    //cria uma interação no card
                    ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: '.$deal->lastOrderId.' card nº: '.$deal->id.' e client id: '.$deal->contactId.' - '.$incluiPedidoOmie['faultstring']. 'Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
                // }  
             
            }           
        
            return $message;
        } else {

            $status = 4;
            $m[]= 'Erro ao montar pedido para enviar ao Omie ERP: Não havia proposta ou venda no card Nº '.$decoded['New']['Id'].', possívelmente não é proviniente de nenhum funil de vendas.';
            //$alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            //$log = $this->databaseServices->registerLog($webhook['id'], $m[0], $decoded['Entity']);

            $msg=[
                'ContactId' => $decoded['New']['ContactId'],
                'DealId' => $decoded['New']['Id'] ?? null,
                'Content' => 'Não havia proposta ou venda no card Nº '.$decoded['New']['Id'].', possívelmente não é proviniente de nenhum funil de vendas.'.$current ,
                'Title' => 'Erro na integração'
            ];
           
            //cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, Não existia venda no card nº: '.$decoded['New']['Id'].' do client id: '.$decoded['New']['ContactId'].'. Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);

            throw new WebhookReadErrorException('Não era um Card Ganho ou não haviam proposta e venda no card Nº '.$decoded['New']['Id'].' data '.$current ,500);
        }          

    }

    //LÊ O WEBHOOK DE CARD EXCLUIDO
    public function deletedDealHook($json)
    {

        $current = date('d/m/Y H:i:s');
        $message = [];

        $decoded = json_decode($json, true);
        //verifica se o webhook é de card excluido
        if($decoded['Entity'] !== 'Deals' && $decoded['Action'] !== 'Delete'  ) {
            throw new WebhookReadErrorException('Não havia um card deletado no webhook - '.$current . PHP_EOL, 1010);
        }
        //Exclui o Deal da base de dados 
        try{
            
            $total = $this->databaseServices->deleteDeal($decoded['Old']['Id']);
            
            ($total > 0)?
            $message ['deal']['deleted'] = 'Proposta (total = '.$total.') excluída da base de dados do sistema de integração - '.$current . PHP_EOL:$message ['deal']['notdeleted'] = 'Proposta não encontrada na base de dados da integração ou já foi deletada. - '.$current . PHP_EOL;
            
        }
        catch(PDOException $e)
        {
            throw new DealNaoExcluidoBDException('Erro ao consultar a base de dados do sistema de integração: '. $e->getMessage() .' - '. $current . PHP_EOL, 1012);
        }

        return $message;

    }

    
}