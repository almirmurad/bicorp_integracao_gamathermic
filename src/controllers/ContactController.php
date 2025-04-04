<?php
namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\handlers\ClientHandler;
use src\handlers\LoginHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class ContactController extends Controller {
    
    private $loggedUser;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;
    private $rabbitMQServices;


    public function __construct()
    {
        if($_SERVER['REQUEST_METHOD'] !== "POST"){
            $this->loggedUser = LoginHandler::checkLogin();
            if ($this->loggedUser === false) {
                $this->redirect('/login');
            }
        }

        $this->ploomesServices = new PloomesServices();
        $this->omieServices = new OmieServices();
        $this->databaseServices = new DatabaseServices();
        $this->rabbitMQServices = new RabbitMQServices();

    }

    //Ploomes
    //recebe webhook de cliente criado, alterado e excluído do PLOOMES CRM
    public function ploomesContacts()
    {
        $json = file_get_contents('php://input');

        try{
            
            $clienteHandler = new ClientHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);

            $response = $clienteHandler->saveClientHook($json);
            // $rk = origem.entidade.ação
            $rk = array('Ploomes','Contacts');
            $this->rabbitMQServices->publicarMensagem('contacts_exc', $rk, 'ploomes_contacts',  $json);

            if ($response > 0) {
                
                $message =[
                    'status_code' => 200,
                    'status_message' => 'SUCCESS: '. $response['msg'],
                ];
                
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally
        {
            if(isset($e)){
               
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];

                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);

                return print 'ERROR:'. $message['status_code'].'. MESSAGE: ' .$message['status_message'];
            }           
             return print $message['status_message'];         
        }        
    }

    //processa contatos e clientes do ploomes ou do Omie
    public function processNewContact()
    {
        $json = file_get_contents('php://input');

        $message = [];
        // processa o webhook 
        try{
            
            $clienteHandler = new ClientHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            
            $response = $clienteHandler->startProcess($json);

            $message =[
                'status_code' => 200,
                'status_message' => $response['success'],
            ];
        
            //grava log
            ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logClient.log', $input . PHP_EOL, FILE_APPEND);
        
        }catch(WebhookReadErrorException $e){
                
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/logClient.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                //print $e->getMessage();
                
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
               
                // return print 'ERROR: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
                 $m = json_encode($message);
                 return print_r($m);
            }
            
            //return print 'SUCCESS: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
            $m = json_encode($message);
            return print_r($m);
               
        }

    } 

    //Omie
    //recebe webhook de cliente criado, alterado e excluído do OMIE ERP
    public function omieClients(){

        $json = file_get_contents('php://input');
        $message = [];
        // ob_start();
        // var_dump($json);
        // $input = ob_get_contents();
        // ob_end_clean();
        // file_put_contents('./assets/contacts.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

        try{
            
            $clienteHandler = new ClientHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            
            $response = $clienteHandler->saveClientHook($json);
            // $rk = origem.entidade.ação
            $rk = array('Omie','Clientes');
            $this->rabbitMQServices->publicarMensagem('contacts_exc', $rk, 'omie_clientes',  $json);
            
            if ($response > 0) {
                
                $message =[
                    'status_code' => 200,
                    'status_message' => 'Success: '. $response['msg'],
                ];
                
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
               
                // return print 'ERROR: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
                 $m = json_encode($message);
                 return print_r($m);
            }
            
            //return print 'SUCCESS: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
            $m = json_encode($message);
            return print_r($m);
           
        }

    }

    public function nasajonClients(){

         // Definir cabeçalhos CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Content-Type: application/json");

        // Se for uma requisição OPTIONS (preflight), apenas retornar status 200 e encerrar
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        // Receber JSON normalmente
        $json = file_get_contents('php://input');

            ob_start();
            print_r($json);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/nasajon.log', $input . PHP_EOL, FILE_APPEND);

            $r = [
                'pong'=>true
            ];
            $jsonR = json_encode($r);

        return print $jsonR;
    }

}