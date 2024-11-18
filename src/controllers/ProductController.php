<?php
namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\handlers\LoginHandler;
use src\handlers\ProductHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;


class ProductController extends Controller {
    
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
    //recebe webhook do omie
    public function omieProducts()
    {
        $message = [];
        $json = file_get_contents('php://input');

        ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/products.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

        try{
            $productHandler = new ProductHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $productHandler->saveProductHook($json);
             // $rk = origem.entidade.ação
             $rk = array('Omie','Products');
             $this->rabbitMQServices->publicarMensagem('products_exc', $rk, 'omie_products',  $json);
            
            if ($response > 0) {
                
                $message =[
                    'status_code' => 200,
                    'status_message' => 'Success: '. $response,
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
                $m = json_encode($message);
                 return print_r($m);
            }
             //grava log
             ob_start();
             print_r($message);
             $input = ob_get_contents();
             ob_end_clean();
             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
             
             $m = json_encode($message);
                return print_r($m);
           
        }
            
    }
    //processa webhook de produtos
    public function processNewProduct()
    {
        $json = file_get_contents('php://input');
        // $decoded = json_decode($json,true);
        // $status = $decoded['status'];
        // $entity = $decoded['entity'];
        $message = [];

        // processa o webhook 
        try{
            
            $productHandler = new ProductHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $productHandler->startProcess($json);

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
                $m = json_encode($message);
                 return print_r($m);
            }
             //grava log
             ob_start();
             print_r($message);
             $input = ob_get_contents();
             ob_end_clean();
             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
             
             $m = json_encode($message);
                return print_r($m);
        }

    } 

    // public function ploomesProducts()
    // {
    //     $message = [];
    //     $json = file_get_contents('php://input');

    //     ob_start();
    //     var_dump($json);
    //     $input = ob_get_contents();
    //     ob_end_clean();
    //     file_put_contents('./assets/products.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

    //     try{
    //         $productHandler = new ProductHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
    //         $response = $productHandler->saveProductHook($json);
    //         // $rk = origem.entidade.ação
    //         $rk = array('Omie','Products');
    //         $this->rabbitMQServices->publicarMensagem('products_exc', $rk, 'ploomes_products',  $json);
            
    //         if ($response > 0) {
                
    //             $message =[
    //                 'status_code' => 200,
    //                 'status_message' => 'Success: '. $response['msg'],
    //             ];
                
    //         }

    //     }catch(WebhookReadErrorException $e){        
    //     }
    //     finally{
    //         if(isset($e)){
    //             ob_start();
    //             var_dump($e->getMessage());
    //             $input = ob_get_contents();
    //             ob_end_clean();
    //             file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                
    //             return print $e->getMessage();
    //         }
    //          //grava log
    //          ob_start();
    //          print_r($message);
    //          $input = ob_get_contents();
    //          ob_end_clean();
    //          file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
             
    //          return print $message['status_message'];
           
    //     }
            
    // }
}