<?php

namespace src\handlers;

use src\exceptions\ClienteInexistenteException;
use src\exceptions\WebhookReadErrorException;
use src\functions\ClientsFunctions;
use src\models\Webhook;
use src\services\ContactServices;
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
    public function saveClientHook($json)
    { 
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

    }

    //PROCESSA E CRIA O cliente. CHAMA O REPROCESS CASO DE ERRO
    // public function startProcess($status, $entity)
    public function startProcess($json)
    {   
        //inicia o processo de crição de cliente, caso de certo retorna mensagem de ok pra gravar em log, e caso de erro retorna falso
        //$webhook = $this->databaseServices->getWebhook($status, $entity);
        
        //$status = 2; //processando
        //$alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
        
        //talvez o ideal fosse devolver ao controller o ok de que o processo foi iniciado e um novo processo deve ser inciado 
        //if($alterStatus){
            // $action = ClientsFunctions::findAction($webhook);
            
            $action = ClientsFunctions::findAction($json);
            // print_r($action);
            // exit;
                          
            if($action){
                //se tiver action cria o objeto de contacs
                switch($action){
                    case 'createCRMToERP':
                        $contact = ClientsFunctions::createObj($json, $this->ploomesServices);
                        $process = ContactServices::createContact($contact);
                        break;
                    case 'updateCRMToERP':
                        $contact = ClientsFunctions::createObj($json, $this->ploomesServices);
                        $process = ContactServices::updateContactCRMToERP($contact);
                        break;
                    case 'deleteCRMToERP':
                        $contact = ClientsFunctions::createOldObj($json, $this->ploomesServices);
                        $process = ContactServices::deleteContact($contact);                    
                        break;
                    case 'createERPToCRM':
                        $contact  = ClientsFunctions::createOmieObj($json, $this->omieServices);
                        $process = ContactServices::createContactERP($contact);                   
                        break;
                    case 'updateERPToCRM':
                        $contact  = ClientsFunctions::createOmieObj($json, $this->omieServices);
                        $contactJson = ClientsFunctions::createPloomesContactFromOmieObject($contact, $this->ploomesServices, $this->omieServices);
                        $process = ContactServices::updateContactERP($contactJson, $contact, $this->ploomesServices);                 
                        break;
                    case 'deleteERPToCRM':
                        $decoded = json_decode($json, true);

                        $contact = new stdClass();
                        $contact->cnpjCpf = $decoded['event']['cnpj_cpf'];
                        $contact->nomeFantasia = $decoded['event']['nome_fantasia'];
                        
                        $process = ContactServices::deleteContactERP($contact, $this->ploomesServices);
                    
                        break;
                } 
            }
            return self::response($json, $contact, $process);
           // return $process;
        // }
                 
    }

    //Trata a respostas para devolver ao controller
    public function response($json, $contact, $process)
    {
        $decoded = json_decode($json, true);
        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';
        //Quando a origem é omie x ploomes então apenas uma base para uma base
        if($origem === 'Omie'){

            if(!empty($process['error'])){

                // $status = 4; //falhou
                // $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
                
                //$reprocess = Self::reprocessWebhook($webhook);
                //$this->databaseServices->registerLog($webhook['id'], $process['error'], $webhook['entity']); 

                //$decoded = json_decode($webhook['json'],true);
                
                if($decoded['topic'] === 'ClienteFornecedor.Incluido'){
                    throw new WebhookReadErrorException($process['error']. ' em: '.$this->current, 500);
                }elseif($decoded['topic'] === 'ClienteFornecedor.Excluido'){
                    throw new WebhookReadErrorException($process['error']. ' em: '.$this->current, 500);
                }elseif($decoded['topic'] === 'ClienteFornecedor.Alterado'){
                    throw new WebhookReadErrorException($process['error']. ' em: '.$this->current, 500);
                }
            }

            // $status = 3; //Success
            // $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            
            // if($alterStatus){
                // $this->databaseServices->registerLog($webhook['id'], $process['success'], $webhook['entity']);
                
                return $process;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
            // }
        }

        
        //quando a origem é ploomes x omie verifica quantas bases haviam para integrar
        $totalBasesIntegrar = 0;
        foreach($contact->basesFaturamento as $bf){
            if($bf['integrar']>0){
                $totalBasesIntegrar++;
            }
        }
  
        //sucesso absoluto contato cadastrado em todas as bases que estavam marcadas para integrar
        if(count($process['success']) == $totalBasesIntegrar){
            // // $status = 3; //Success
            // // $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            // foreach($process['success'] as $success){
                
            //     $this->databaseServices->registerLog($webhook['id'], $success, $webhook['entity']);
            // }
            
            // if($alterStatus){
            $process['success'] = 'Sucesso: ação executada em todos os clientes';
                
            return $process;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
            // }
        //falha absoluta erro no cadastramento do contato em todas as bases
        }elseif(count($process['error']) == $totalBasesIntegrar){
            
            // $status = 4; //falhou
            // $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            
            //$reprocess = Self::reprocessWebhook($webhook);

            //if($reprocess['error']){
                $m = '';
                foreach($process['error'] as $error){
                   $m .= $error .  "\r\n";
                }
                throw new WebhookReadErrorException('Erro ao gravar cliente(s): '.$m.' em: ' .$this->current, 500);
                
                //return $reprocess['error'];

            //}
        //parcial cadastrou eum alguma(s) bases e em outara(s) não
        }else{

            // $status = 5; 
            // $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            
            //$reprocess = Self::reprocessWebhook($webhook);

            //if($reprocess['error']){
                // foreach($process['success'] as $success){

                //     $this->databaseServices->registerLog($webhook['id'], $success, $webhook['entity']);
                // }
                $m = '';
                foreach($process['error'] as $error){
                   $m .= $error .  "\r\n";
                }
                throw new WebhookReadErrorException('Nem todos os clientes foram cadastrados, houveram falhas as gravar clientes: '.$m . 'em: '. $this->current, 500);
                
                // return $process;

            //}

        }
    }
    

}