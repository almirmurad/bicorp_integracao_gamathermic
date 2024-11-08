<?php
namespace src\controllers;

use core\Controller;
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
use src\handlers\LoginHandler;
use src\handlers\OrderHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class OrderController extends Controller {
    
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

        $this->ploomesServices = new ploomesServices();
        $this->omieServices = new omieServices();
        $this->databaseServices = new DatabaseServices();
        $this->rabbitMQServices = new RabbitMQServices();

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

    // public function newOmieOrder(){

    //     $json = file_get_contents('php://input');

    //     ob_start();
    //     var_dump($json);
    //     $input = ob_get_contents();
    //     ob_end_clean();
    //     file_put_contents('./assets/all.log', $input . PHP_EOL, FILE_APPEND);

    //     try{

    //         $omieOrderHandler = new OmieOrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
    //         $response = $omieOrderHandler->newOmieOrder($json);

    //         // if ($response) {
    //         //     echo"<pre>";
    //         //     json_encode($response);
    //         //     //grava log
    //         //     //$decoded = json_decode($response, true);
    //         //     ob_start();
    //         //     var_dump($response);
    //         //     $input = ob_get_contents();
    //         //     ob_end_clean();
    //         //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
    //         // }

    //     }catch(PedidoDuplicadoException $e){
    //         // echo $e->getMessage();
    //     }catch(PDOException $e){
    //         // echo $e->getMessage();
    //     }catch(PedidoOutraIntegracaoException $e){
    //         // echo $e->getMessage();
    //     }catch(OrderControllerException $e){
    //         // echo $e->getMessage();
    //     }catch(ContactIdInexistentePloomesCRM $e){
    //         // echo $e->getMessage();
    //     }catch(InteracaoNaoAdicionadaException $e){
    //         // echo $e->getMessage();
    //     }finally{
    //         if (isset($e)){
    //             ob_start();
    //             echo $e->getMessage();
    //             $input = ob_get_contents();
    //             ob_end_clean();
    //             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
    //             return print $e->getMessage();
    //         }
    //         return print_r($response);
    //     }
            
    // }

    // public function deletedOrder(){
    //     $json = file_get_contents('php://input');
    //         //$decoded = json_decode($json, true);

    //         // ob_start();
    //         // var_dump($json);
    //         // $input = ob_get_contents();
    //         // ob_end_clean();

    //         // file_put_contents('./assets/all.log', $input . PHP_EOL, FILE_APPEND);
    //         // $pong = array("pong"=>true);
    //         // $json = json_encode($pong);
    //         // return print_r($json);

    //     try{

    //         $omieOrderHandler = new OmieOrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
    //         $response = $omieOrderHandler->deletedOrder($json);
    //         // if ($response) {
    //         //     echo"<pre>";
    //         //     json_encode($response);
    //         //     //grava log
    //         //     //$decoded = json_decode($response, true);
    //         //     ob_start();
    //         //     var_dump($response);
    //         //     $input = ob_get_contents();
    //         //     ob_end_clean();
    //         //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
    //         // }

    //     }catch(PedidoInexistenteException $e){
    //         // echo $e->getMessage();
    //     }catch(PedidoCanceladoException $e){
    //         // echo $e->getMessage();
    //     }catch(PedidoNaoExcluidoException $e){
    //         // echo $e->getMessage();
    //     }
    //     catch(PedidoDuplicadoException $e){
    //         // echo $e->getMessage();
    //     }catch(OrderControllerException $e){
    //         // echo $e->getMessage();
    //     }catch(ContactIdInexistentePloomesCRM $e){
    //         // echo $e->getMessage();
    //     }catch(InteracaoNaoAdicionadaException $e){
    //         // echo $e->getMessage();
    //     }finally{
    //         if (isset($e)){
    //             ob_start();
    //             echo $e->getMessage();
    //             $input = ob_get_contents();
    //             ob_end_clean();
    //             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
    //             return print $e->getMessage(); 
    //         }
    //         return print_r($response);
    //     }
    // }

    // public function alterOrderStage(){
    //     $json = file_get_contents('php://input');
    //         //$decoded = json_decode($json, true);

    //         // ob_start();
    //         // var_dump($json);
    //         // $input = ob_get_contents();
    //         // ob_end_clean();

    //         // file_put_contents('./assets/whkAlterStageOrder.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

    //     try{
    //         $omieOrderHandler = new OmieOrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
    //         $response = $omieOrderHandler->alterOrderStage($json);
    //         // if ($response) {
    //         //     echo"<pre>";
    //         //     json_encode($response);
    //         //     //grava log
    //         //     //$decoded = json_decode($response, true);
    //         //     ob_start();
    //         //     var_dump($response);
    //         //     $input = ob_get_contents();
    //         //     ob_end_clean();
    //         //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
    //         // }

    //     }catch(WebhookReadErrorException $e){
    //         // echo $e->getMessage();
    //     }catch(InteracaoNaoAdicionadaException $e){
    //         // echo $e->getMessage();
    //     }finally{
    //         if (isset($e)){
    //             ob_start();
    //             echo $e->getMessage();
    //             $input = ob_get_contents();
    //             ob_end_clean();
    //             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND); 
    //             return print $e->getMessage();
    //         }
    //     }
    //     return print_r($response);
    // }

    public function ploomesOrder()
    {
        // print'aqui';
        // exit;
        /*
        *Recebe o webhook de card ganho, salva na base e retorna 200
        */
        $json = file_get_contents('php://input');
        // ob_start();
        // var_dump($json);
        // $input = ob_get_contents();
        // ob_end_clean();
        // file_put_contents('./assets/all.log', $input . PHP_EOL, FILE_APPEND);

        try{
            
            $orderHandler = new OrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $orderHandler->saveDealHook($json);

            
                        
             // $rk = origem.entidade.ação
             $rk = array('Ploomes','Orders');
             $this->rabbitMQServices->publicarMensagem('orders_exc', $rk, 'ploomes_orders',  $json);

            if ($response > 0) {

                $message = [];
                $message =[
                    'status_code' => 200,
                    'status_message' => 'SUCCESS: '. $response['msg'],
                ];
                
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally{
            if(isset($e)){
                $message = [];
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
             //grava log
             ob_start();
             print_r($message);
             $input = ob_get_contents();
             ob_end_clean();
             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
             
             return print $message['status_message'];
           
        }        
    }

    public function processNewOrder(){
        $json = file_get_contents('php://input');
        $message = [];
        /**
         * processa o webhook 
         */
        try{
            
            $orderHandler = new OrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $orderHandler->startProcess($json);

            $message =[
                'status_code' => 200,
                'status_message' => $response,
            ];
                
             
            //grava log
            ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
            //return $message['status_message'];
        
        }catch(WebhookReadErrorException $e){                      
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                //print $e->getMessage();
              
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
                $m = json_encode($message);
                 return print_r($m);
                //return print 'ERROR: '.$message['status_code'].' MESSAGE: '.$message['status_message'];
               }
               $m = json_encode($message);
               return print_r($m);
            //return print $message['status_message']['winDeal']['success'];
        }

    }


}