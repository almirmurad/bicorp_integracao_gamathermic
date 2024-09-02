<?php

namespace src\handlers;

use GuzzleHttp\Client;
use PDOException;
use src\exceptions\ContactIdInexistentePloomesCRM;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\OrderControllerException;
use src\exceptions\PedidoCanceladoException;
use src\exceptions\PedidoDuplicadoException;
use src\exceptions\PedidoInexistenteException;
use src\exceptions\PedidoNaoExcluidoException;
use src\exceptions\WebhookReadErrorException;
use src\models\Cliente;
use src\models\Contact;
use src\models\Homologacao_invoicing;
use src\models\Homologacao_order;
use src\models\Manospr_order;
use src\models\Manossc_order;
use src\models\Omie;
use src\models\Omieorder;
use src\models\User;
use src\models\Webhook;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use stdClass;

class ClientHandler
{
    private $current;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;

    public function __construct(PloomesServices $ploomesServices, OmieServices $omieServices, DatabaseServices $databaseServices)
    {
        $this->ploomesServices = $ploomesServices;
        $this->omieServices = $omieServices;
        $this->databaseServices = $databaseServices;
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveClientHook($json){
      

        $decoded = json_decode($json, true);

        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';
     

        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity']??'Contacts';
        $webhook->origem = $origem;
        //salva o hook no banco
        return ($id = $this->databaseServices->saveWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;
        
        return ['id'=> 0, 'msg' =>$msg];

    }

    //PROCESSA E CRIA O cliente. CHAMA O REPROCESS CASO DE ERRO
    public function startProcess($status, $entity)
    {   
  
        /*
        * inicia o processo de crição de cliente, caso de certo retorna mensagem de ok pra gravar em log, e caso de erro retorna falso
        */
        $hook = $this->databaseServices->getWebhook($status, $entity);
        
        $status = 2; //processando
        $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
        
        if($alterStatus){
            $createClient = Self::newClient($hook);
            if(!isset($createClient['contactsCreate']['error'])){
                $status = 3; //Success
                $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                if($alterStatus){
                    
                    return $createClient;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
                }

            }else{
                $status = 4; //falhou
                $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                
                //$reprocess = Self::reprocessWebhook($hook);

                //if($reprocess['contactsCreate']['error']){

                    $log = $this->databaseServices->registerLog($hook['id'], $createClient['contactsCreate']['error'], $hook['entity']); 

                    
                    throw new WebhookReadErrorException('Erro ao gravar cliente: '.$createClient['contactsCreate']['error'].'Salvo em logs do sistema (log id: '.$log.')'. date('d/m/Y H:i:s'), 500);
                    
                    //return $reprocess['contactsCreate']['error'];

                }
                
            }
        }
                 
    }

    //REPROCESSA O CARD COM FALHA
    public function reprocessWebhook($hook){
        $status = 4;//falhou
        //$hook = $this->databaseServices->getWebhook($status, 'Contacts');
        //$json = $hook['json'];
        $status = 2; //processando
        $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
        
        if($alterStatus){
            
            $createClient = Self::newClient($hook);
            
            
            if(!isset($createClient['contactsCreate']['error'])){
                $status = 3; //Sucesso
                $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                if($alterStatus){
                    return $createClient;//card processado pedido criado no Omie retorna mensagem winDeal para salvr no log
                }

            }else{
                $status = 4; //falhou com mensagem
                $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                
                return $createClient;
            }
        }
        
    }
    //recebe um novo cliente ploomes
    public function newClient($webhook){
        
        $current = $this->current;
        $message = [];
        $m = [];
        //decodifica o json de clientes vindos do webhook
        $json = $webhook['json'];
        $decoded = json_decode($json,true);

        /**
         * Lista de status de crédito base de testes
         * Pendente = 410783393
         * Aprovado = 410783395
         * Reprovado = 410783394
         * Pagamento Antecipado = 410807643
         */
         /**
         * Lista de status de crédito base de Fiel
         * Pendente = 411170343
         * Aprovado = 411170346
         * Reprovado = 411170345
         * Pagamento Antecipado = 411170344
         */
        $statusCreditoOld = ($decoded['Old']['OtherProperties']['contact_7FBEF4F4-FEF3-4B4A-BB83-D109E7DAC801']) ?? null;
        $statusCreditoNew = ($decoded['New']['OtherProperties']['contact_7FBEF4F4-FEF3-4B4A-BB83-D109E7DAC801']) ?? null;
        
        
        $statusCredito = [];
        $statusCredito['Pendente'] = 411170343;
        $statusCredito['Aprovado'] = 411170346;
        $statusCredito['Reprovado'] = 411170345;
        $statusCredito['Pagamento Antecipado'] = 411170344;
        
        $statusAtual = match($statusCreditoNew){
            411170343 => 'Pendente',
            411170346 => 'Aprovado',
            411170345 => 'Reprovado',
            411170344 => 'Pagamento Antecipado',
            default => 'Pendente'
        };

        
        if(isset($decoded['Entity']) && $decoded['Entity'] == "Contacts" &&  isset($decoded['Action']) && $decoded['Action'] == "Create") 
        {
            $status = 4;
            $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            $m[]= 'Cliente: '.$decoded['New']['Name'].', Id: '.$decoded['New']['Id'].', criado com status de crédito '.$statusAtual.'.';
            $log = $this->databaseServices->registerLog($webhook['id'], $m[0], $decoded['Entity']);

            $msg=[
                'ContactId' => $decoded['New']['Id'],
                'Content' => $m[0],
                'Title' => 'Cliente Criado'
            ];
           
            //cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['contacts']['interactionMessage'] = $m[0]  : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);

            throw new WebhookReadErrorException($m[0] ,500);
            
        }
        elseif(
            
                (
                    isset($decoded['Entity']) && $decoded['Entity'] == "Contacts"
                )
                && (
                        isset($decoded['Action']) && $decoded['Action'] == "Update"
                    )
                && (
                        (
                            $statusCreditoOld == $statusCredito['Pendente'] && $statusCreditoNew == $statusCredito['Aprovado']
                        )
                        ||  
                        (
                            $statusCreditoOld == $statusCredito['Pendente'] && $statusCreditoNew == $statusCredito['Pagamento Antecipado']
                        )
                        ||
                        (
                            $statusCreditoOld == $statusCredito['Reprovado'] && $statusCreditoNew == $statusCredito['Aprovado']
                        )
                        ||  
                        (
                            $statusCreditoOld == $statusCredito['Reprovado'] && $statusCreditoNew == $statusCredito['Pagamento Antecipado']
                        )
                        
                    )
            ) 
        {    
           
            $cliente = $this->ploomesServices->getClientById($decoded['New']['Id']);
            
            //cria objeto contacts
            $contact = new Contact();            
            
            /************************************************************
             *                   Other Properties                        *
             *                                                           *
             * No webhook do Contact pegamos os campos de Other Properies*
             * para encontrar a chave da base de faturamento do Omie     *
             *                                                           *
             *************************************************************/
            $prop = [];
            //contact_D29BC9BD-9A68-4161-8B85-64B1B3D6DEC8 = inscrição estadual
            //contact_3765DFD8-684B-4A15-A67E-4ECC8A81BE84 = email de compras
            //contact_4A79414B-BF9E-4638-8298-939E46EBD784 = contato1
            //contact_1F276950-E173-4AB1-BE20-67AD17B2E940 = num de funcionários
            //contact_616EE3C5-7676-46BF-A1D9-4C87836A5BC9 = Faturamento Anual
            //contact_68977172-38CB-4430-9664-09997FA3ECBE = Referência 1
            //contact_6C906DCC-7ED9-4B48-8A4A-51D6B054FC53 = Telefone Referência 1
            //contact_15A6DDFC-9849-48D1-85D6-23D496866C79 = Referência 2
            //contact_274E3232-D233-40CA-AE95-66FBB61F2F50 = Telefone Referência 2
            //contact_DB4CC1AC-C88C-4D14-BA2C-5ADF0C75DCF6 = Referência 3
            //contact_E04F5EF4-1FE2-46F6-988D-4B94444B00E0 = Telefone Referencia 3
            //contact_2E5C65B7-5398-4D5E-98EA-05094AB58A88 = Região na base de testes
            //contact_71F7D230-C8AD-40D4-BBBE-954B4C1A3962 = Status de Crédito
            //contact_5756C3AC-833D-4B22-9C4A-B86856E51BE4 = options de Porte
            //contact_198FC5BA-9E97-4BAF-BA8B-F6770AC23A94 = options de Região
            //contact_A96C5C9E-7ED9-469B-BA92-727560061F68 = Características
            foreach ($decoded['New']['OtherProperties'] as $key => $op) {
                $prop[$key] = $op;
            }
            
            $phones = [];
            foreach($decoded['New']['Phones'] as $phone){
                
                $partes = explode(' ',$phone['PhoneNumber']);
                $ddd = $partes[0];
                $nPhone = $partes[1];
                $phones[] = [
                    'ddd'=>$ddd,
                    'nPhone' => $nPhone
                ];
                
            }

 
            // Base de Faturamento para fiel não precisa pois integra e depois a automação distribui em todas as bases, em gamathermic precisa
            // $contact->baseFaturamento = (isset($prop['deal_A965E8F5-EF81-4CF3-939D-1D7FE6F1556C']) && !empty($prop['deal_A965E8F5-EF81-4CF3-939D-1D7FE6F1556C']))? $prop['deal_A965E8F5-EF81-4CF3-939D-1D7FE6F1556C'] : $m[] = 'Base de faturamento inexistente';
            
            $contact->id = $cliente['Id']; //Id do Contact
            $contact->name = $cliente['Name']; // Nome ou nome fantasia do contact
            $contact->legalName = $cliente['LegalName'] ?? null; // Razão social do contact
            $contact->cnpj = $cliente['CNPJ'] ?? null; // Contatos CNPJ
            $contact->cpf = $cliente['CPF'] ?? null; // Contatos CPF
            $contact->email = $cliente['Email']; // Contatos Email obrigatório
            $contact->ddd1 = $phones[0]['ddd']; //"telefone1_ddd": "011",
            $contact->phone1 = $phones[0]['nPhone']; //"telefone1_numero": "2737-2737",
            $contact->contato1 = $prop['contact_E6008BF6-A43D-4D1C-813E-C6BD8C077F77'] ?? null;
            $contact->streetAddress = $cliente['StreetAddress']; // Endereço Obrigatório
            $contact->streetAddressNumber = $cliente['StreetAddressNumber']; // Número Endereço Obrigatório
            $contact->streetAddressLine2 = $cliente['StreetAddressLine2'] ?? null; // complemento do Endereço 
            $contact->neighborhood = $cliente['Neighborhood']; // bairro do Endereço é obrigatório
            $contact->zipCode = $cliente['ZipCode']; // CEP do Endereço é obrigatório
            $contact->cityId = $cliente['City']['IBGECode']; // Id da cidade é obrigatório
            $contact->cityName = $cliente['City']['Name']; // Nome da cidade é obrigatório
            $contact->cityLagitude = $cliente['City']['Latitude']; // Latitude da cidade é obrigatório
            $contact->cityLongitude = $cliente['City']['Longitude']; // Longitude da cidade é obrigatório
            $contact->stateShort = $cliente['State']['Short']; // Sigla do estado é obrigatório
            $contact->stateName = $cliente['State']['Name']; // Nome do estado é obrigatório
            $contact->countryId = $cliente['CountryId']; // Id do país é obrigatório
            $contact->cnaeCode = $cliente['CnaeCode'] ?? null; // Id do cnae 
            $contact->cnaeName = $cliente['CnaeName'] ?? null; // Name do cnae 
            $contact->ownerId = $cliente['Owner']['Id'] ?? null; // Responsável (Vendedor)  
            $regiao = $prop['contact_7DD44E68-3CC6-4C2E-A42B-AE39620D49B4'] ?? null;
            
            switch($regiao)
            {
                case 410919123:
                     $contact->regiao = "ZONA SUL/SP";
                     break;
                case 410919124:
                    $contact->regiao = "ZONA OESTE/SP";
                   break;
                case 410919125:
                    $contact->regiao = "ZONA NORTE/SP";
                    break;
                case 410919126:
                    $contact->regiao = "ZONA LESTE/SP";
                    break;
                case 410919127:
                    $contact->regiao = "VALE DO RIBEIRA/SP";
                    break;
                case 410919128:
                    $contact->regiao = "VALE DO PARAÍBA/SP";
                    break;
                case 410919129:
                    $contact->regiao = "SUL D'OESTE/PR";
                    break;
                case 410919130:
                    $contact->regiao = "SOROCABA/SP";
                    break;
                case 410919131:
                    $contact->regiao = "PRUDENTE/SP";
                    break;
                case 410919132:
                    $contact->regiao = "OESTE/PR";
                    break;
                case 410919133:
                    $contact->regiao = "NORTE/PR";
                    break;
                case 410919135:
                    $contact->regiao = "LITORAL/SP";
                    break;
                case 410919136:
                    $contact->regiao = "LITORAL/PR";
                    break;
                case 410919137:
                    $contact->regiao = "FRANCA/SP";
                    break;
                case  410919138:
                    $contact->regiao = "CWB5";
                    break;
                case 410919139:
                    $contact->regiao = "CWB4";
                    break;
                case 410919140:
                    $contact->regiao = "CWB3";
                    break;
                case 410919141:
                    $contact->regiao = "CWB2";
                    break;
                case 410919142:
                    $contact->regiao = "CWB1";
                    break;
                case 410919143:
                    $contact->regiao = "CAMPOS GERAIS/PR";
                    break;
                case 410919144:
                    $contact->regiao = "CAMPINAS/SP";
                    break;
                case 410919145:
                    $contact->regiao = "BAURU/SP";
                    break;
                case 410919146:
                    $contact->regiao = "ARAÇATUBA/SP";
                    break;
                case 410919152:
                    $contact->regiao = "ZONA BRANCA";
                    break;
                case 410947846:
                    $contact->regiao = "ZONA CENTRO/SP";
                    break;
                case 410947847:
                    $contact->regiao = "NOROESTE/PR";
                    break;

        }
            $contact->statusCredito = $prop['contact_7FBEF4F4-FEF3-4B4A-BB83-D109E7DAC801'];
            $tags= [];
            $tag=[];

            if($cliente['Tags']){

                foreach($cliente['Tags'] as $iTag){
    
                    $tag['tag']=$iTag['Tag']['Name'];
                    
                    $tags[]=$tag;
                }
            }
            $contact->tags = $tags;
        

            switch($contact){
                case isset($contact->name) && empty($contact->name):
                    $m[] = 'Campo Nome do cliente não pode ser vazio.';
                    break;
                case (isset($contact->cpf) && empty($contact->cpf)) || (isset($contact->cnpj) && empty($contact->cnpj)):
                    $m[] = 'Ambos os campos CPF ou CNPJ estão vazios, preencha ao menos um deles.';
                    break;
                case isset($contact->email) && empty($contact->email) :
                    $m[] = 'Email está vazio ';
                    break;
                case isset($contact->streetAddress) && empty($contact->streetAddress) ||
                    isset($contact->streetAddressNumber) && empty($contact->streetAddressNumber) ||
                    isset($contact->neighborhood) && empty($contact->neighborhood) ||
                    isset($contact->zipCode) && empty($contact->zipCode) ||
                    isset($contact->cityId) && empty($contact->cityId) ||
                    isset($contact->stateId) && empty($contact->stateId) ||
                    isset($contact->countryId) && empty($contact->countryId):
                    $m[] = 'Endereço está incompleto';
                    break;
                // default:
                //     $m[] = null;


            }

            //encontra o id do vendedor no ploomes pelo email
             //($mailVendedor = $this->ploomesServices->ownerMail($deal)) ? $mailVendedor: $m[] = 'Não foi encontrado o email deste vendedor. Id do card Ploomes CRM: '.$decoded['New']['Id'].' e pedido de venda Ploomes CRM: '.$decoded['New']['LastOrderId'].'em'.$current;
            //$mailVendedor = 'vendas9@fielpapeis.com.br';
            $contact->createDate = $decoded['New']['CreateDate']; // Data de criação
    
            $contact->webhookId = $webhook['id']; //inclui o id do webhook no deal
            
            /**************************************************** 
            *        Encontra a base de faturamento             *
            *                                                   *
            * NO webhook do Card pegamos os dados do checklist  * 
            * para encontrar a base de faturamento do Omie      *
            * pra fiel não há necessidade pois ao integrar      *
            * a automação distribui para todas as bases         *
            *****************************************************/
            
            $omie = new Omie();
            $omie->baseFaturamentoTitle = 'Manos Homologação';
            $omie->target = 'MHL'; 
            //$omie->ncc = $_ENV['NCC_MHL'];
            $omie->appSecret = $_ENV['SECRETS_MHL'];
            $omie->appKey = $_ENV['APPK_MHL'];
            //$m[] = 'Base de faturamento não encontrada. Impossível fazer consultas no omie';
            
            if(!empty($m)){

                $status = 4;
                $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
                $log = $this->databaseServices->registerLog($webhook['id'], $m[0], $decoded['Entity']);

                if($alterStatus && $log){

                    //monta a mensagem com erro para atualizar o card do ploomes
                    $msg=[
                        'ContactId' => $contact->id,
                        'Content' => 'Erro ao criar cliente no OMIE ERP  na base via API BICORP. Mensagem : '.$m[0],
                        'Title' =>'Erro ao criar cliente no Omie ERP'
                    ];
               
                //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['contactsCreate']['interactionMessage'] = 'Mensagem de erro enviada com sucesso! Cliente Ploomes: '.$contact->id.'. Cliente não foi gravado no Omie ERP em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem de erro na venda ',500);

                }

                throw new WebhookReadErrorException('Erro nos dados obrigatórios para incluir cliente no Omie. Erro: '.$m[0],500);
            }
            
            
            /****************************************************************
            *                     Cria Cliente no Omie                       *
            *                                                               *
            * Cria um cliente no omie. Obrigatório enviar:                   *
            * chave app do omie, chave secreta do omie, id do cliente omie, *
            * data de previsão(finish date), id pedido integração (id pedido* 
            * no ploomes), array de produtos($prodcutsOrder), numero conta  *    
            * corrente do Omie ($ncc), id do vendedor omie($codVendedorOmie)*
            * Total do pedido e Array de parcelamento                       *
            *                                                               *
            *****************************************************************/
            
            $criaClienteOmie = $this->omieServices->criaClienteOmie($omie, $contact);

            //verifica se criou o pedido no omie
            if (isset($criaClienteOmie['codigo_status']) && $criaClienteOmie['codigo_status'] == "0") {
                
                //monta a mensagem para atualizar o card do ploomes
                $msg=[
                    'ContactId' => $contact->id,
                    'Content' => 'Cliente '.$contact->name.' criada no OMIE via API BICORP',
                    'Title' => 'Pedido Criado'
                ];
               
                //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['contactsCreate']['interactionMessage'] = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
                
                
                //inclui o id do pedido no omie na tabela deal
                // if($criaClienteOmie['codigo_cliente_omie']){
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
               
                //         $id = $this->databaseServices->saveOrder($omie);
                //         $message['winDeal']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos '.$omie->baseFaturamentoTitle.' id '.$id.'em: '.$current;
                //     }
                    
                // }

            }else{
                //monta a mensagem para atualizar o card do ploomes
                $msg=[
                    'ContactId' => $contact->id,
                    'Content' => 'Erro ao gravar cliente no Omie: '. $criaClienteOmie['faultstring'].' Data = '.$current,
                    'Title' => 'Erro ao Gravar cliente'
                ];
               
                //cria uma interação no card
                ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['contactsCreate']['interactionMessage'] = 'Erro ao gravar cliente no Omie: '. $criaClienteOmie['faultstring'].' Data = '.$current: throw new WebhookReadErrorException('Não foi possível gravar a mensagem no cliente',500);
                
                $message['contactsCreate']['error'] ='Não foi possível gravar o cliente no Omie! Erro: '.$criaClienteOmie['faultstring'];
                 
            }           
            
            return $message;
        } 
        elseif(
            (
                isset($decoded['Entity']) && $decoded['Entity'] == "Contacts"
            )
            && (
                    isset($decoded['Action']) && $decoded['Action'] == "Update"
                )
            && (
                    (
                        ($statusCreditoOld == $statusCredito['Pendente'] || $statusCreditoOld == $statusCredito['Aprovado'] ||
                        $statusCreditoOld == $statusCredito['Pagamento Antecipado']) && $statusCreditoNew == $statusCredito['Reprovado']
                    )
                    
                )
        ){

            $status = 4;
            $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            $m[]= 'Cliente: '.$decoded['New']['Name'].', Id: '.$decoded['New']['Id'].', teve o status Reprovado.';
            $log = $this->databaseServices->registerLog($webhook['id'], $m[0], $decoded['Entity']);

            $msg=[
                'ContactId' => $decoded['New']['Id'],
                'Content' => $m[0],
                'Title' => 'Erro na integração'
            ];
           
            //cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['contacts']['interactionMessage'] = $m[0]  : throw new InteracaoNaoAdicionadaException('Não foi possível gravar a mensagem na venda',500);

            throw new WebhookReadErrorException($m[0] ,500);

        }
        else{

            $status = 4;
            $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            $m[]= 'Cliente: '.$decoded['New']['Name'].', Id: '.$decoded['New']['Id'].', Cliente alterado, porém nenhuma ação foi necessária.';
            $log = $this->databaseServices->registerLog($webhook['id'], $m[0], $decoded['Entity']);

            // $msg=[
            //     'ContactId' => $decoded['New']['Id'],
            //     'Content' => $m[0],
            //     'Title' => 'Erro na integração'
            // ];
           
            // //cria uma interação no card
            // ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['contacts']['interactionMessage'] = $m[0]  : throw new InteracaoNaoAdicionadaException('Não foi possível gravar a mensagem na venda',500);

            throw new WebhookReadErrorException($m[0] ,500);
        }          

    }

    //start alter omie lote biscaia


    public function startAlterClientOmieProcess($status)
    {   

        /*
        * inicia o processo de crição de cliente, caso de certo retorna mensagem de ok pra gravar em log, e caso de erro retorna falso
        */
        $hook = $this->databaseServices->getClient($status);
      
        $status = 2; //processando
        $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
        
        if($alterStatus){
            $alterClient = Self::alterClientOmie($hook);
           
            if(!isset($alterClient['alterClient']['error'])){
                $status = 3; //Success
                $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                if($alterStatus){
                    
                    return $alterClient;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
                }

            }else{
                $status = 4; //falhou
                $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                
                //$reprocess = Self::reprocessWebhook($hook);

                //if($reprocess['alterClient']['error']){
                    $hook['entity'] = 'Cliente';

                    $log = $this->databaseServices->registerLog($hook['id'], $alterClient['alterClient']['error'], $hook['entity']); 

                    
                    throw new WebhookReadErrorException('Erro ao gravar cliente: '.$alterClient['alterClient']['error'].'Salvo em logs do sistema (log id: '.$log.')'. date('d/m/Y H:i:s'), 500);
                    
                    return $alterClient['alterClient']['error'];

                //}
                
            }
        }
                 
    }

    public function alterClientOmie($hook){

        

        $current = $this->current;
        $message = [];
        $m = [];

        $omie = new stdClass();
        $omie->baseFaturamentoTitle = 'Manos Paraná';
        $omie->target = 'MHL'; 
        //$omie->ncc = $_ENV['NCC_MHL'];
        $omie->appSecret = $_ENV['SECRETS_MHL'];
        $omie->appKey = $_ENV['APPK_MHL'];

        $cliente = new Cliente();
        $cliente->codigoOmie = $hook['cOmie'];
        $cliente->regiao = $hook['regiao'];
        
        return $this->omieServices->alteraCliente($omie, $cliente);
        


    }



    

}