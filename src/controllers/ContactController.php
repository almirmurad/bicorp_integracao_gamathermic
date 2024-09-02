<?php
namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\handlers\ClientHandler;
use src\handlers\LoginHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;

class ContactController extends Controller {
    
    private $loggedUser;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;

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

    }

    // public function index() {
    //     //$total = Deal::select('id')->count();        
    //     $data = [
    //         'pagina' => 'Pedidos',
    //         'loggedUser'=>$this->loggedUser,
    //         //'total'=>$total
    //     ];
    //     $this->render('gerenciador.pages.index', $data);
    // }

    public function ploomesContacts(){
        $message = [];
        $json = file_get_contents('php://input');

        ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/contacts.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

        try{
            $clienteHandler = new ClientHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $clienteHandler->saveClientHook($json);
            
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
                return print $e->getMessage();
            }
             //grava log
             ob_start();
             print_r($message);
             $input = ob_get_contents();
             ob_end_clean();
             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
             
             return print $message['status_message'];
           
        }
            
    }

    public function processNewContact(){

        $json = file_get_contents('php://input');
        $decoded = json_decode($json,true);
        $status = $decoded['status'];
        $entity = $decoded['entity'];
        $message = [];

        // processa o webhook 
        try{
            
            $clienteHandler = new ClientHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $clienteHandler->startProcess($status, $entity);

            $message =[
                'status_code' => 200,
                'status_message' => $response,
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
               
                return print 'ERROR: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
               }

            $message['status_message']['contactsCreate']['returnContactOmie'];
        }

    } 


    public function alterClientPloomes(){


        $json = file_get_contents('php://input');

       /* ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/alterContacts.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);*/


        try{
            $clienteHandler = new ClientHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $clienteHandler->saveClientHook($json);
            
            if ($response > 0) {

                $message = [];
                $message =[
                    'status_code' => 200,
                    'status_message' => 'Success: '. $response['msg'],
                ];
                
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally{
            if(isset($e)){
                /*ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);*/
                return print $e->getMessage();
            }
             //grava log
             /*ob_start();
             print_r($message);
             $input = ob_get_contents();
             ob_end_clean();
             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);*/
             
             return print $message['status_message'];
           
        }

    }

    //Omie

    public function newClientOmie(){

        $json = file_get_contents('php://input');
        $message = [];
        ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/contacts.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

        try{
            
            $clienteHandler = new ClientHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            
            $response = $clienteHandler->saveClientHook($json);
    
            
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
                return print $e->getMessage();
            }
             //grava log
             ob_start();
             print_r($message);
             $input = ob_get_contents();
             ob_end_clean();
             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
             
             return print $message['status_message'];
           
        }

    }

    public function proccessAlterClientOmie(){



        
        $json = file_get_contents('php://input');
        $decoded = json_decode($json,true);

        $status = $decoded['status'];
        $message = [];
       

        try{
            
            $clienteHandler = new ClientHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $clienteHandler->startAlterClientOmieProcess($status);

            $message =[
                'status_code' => 200,
                'status_message' => $response['alterClient']['success'],
            ];
                
             
            //grava log
           /* ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logAlterClientOmie.log', $input . PHP_EOL, FILE_APPEND);*/
            //return $message['status_message'];
        
        }catch(WebhookReadErrorException $e){
                
        }
        finally{
            if(isset($e)){
                /*ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/logAlterClientOmie.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);*/
                //print $e->getMessage();
            
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
               
                return print 'ERROR: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
               }

            return print $message['status_message'];
        }

    }




}