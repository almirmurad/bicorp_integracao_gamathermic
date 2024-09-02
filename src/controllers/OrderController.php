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
use src\handlers\OmieOrderHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;

class OrderController extends Controller {
    
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

    public function index() {
        //$total = Deal::select('id')->count();        
        $data = [
            'pagina' => 'Pedidos',
            'loggedUser'=>$this->loggedUser,
            //'total'=>$total
        ];
        $this->render('gerenciador.pages.index', $data);
    }

    public function newOmieOrder(){

        $json = file_get_contents('php://input');

        ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/all.log', $input . PHP_EOL, FILE_APPEND);

        try{

            $omieOrderHandler = new OmieOrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $omieOrderHandler->newOmieOrder($json);

            // if ($response) {
            //     echo"<pre>";
            //     json_encode($response);
            //     //grava log
            //     //$decoded = json_decode($response, true);
            //     ob_start();
            //     var_dump($response);
            //     $input = ob_get_contents();
            //     ob_end_clean();
            //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
            // }

        }catch(PedidoDuplicadoException $e){
            // echo $e->getMessage();
        }catch(PDOException $e){
            // echo $e->getMessage();
        }catch(PedidoOutraIntegracaoException $e){
            // echo $e->getMessage();
        }catch(OrderControllerException $e){
            // echo $e->getMessage();
        }catch(ContactIdInexistentePloomesCRM $e){
            // echo $e->getMessage();
        }catch(InteracaoNaoAdicionadaException $e){
            // echo $e->getMessage();
        }finally{
            if (isset($e)){
                ob_start();
                echo $e->getMessage();
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
                return print $e->getMessage();
            }
            return print_r($response);
        }
            
    }

    public function deletedOrder(){
        $json = file_get_contents('php://input');
            //$decoded = json_decode($json, true);

            // ob_start();
            // var_dump($json);
            // $input = ob_get_contents();
            // ob_end_clean();

            // file_put_contents('./assets/all.log', $input . PHP_EOL, FILE_APPEND);
            // $pong = array("pong"=>true);
            // $json = json_encode($pong);
            // return print_r($json);

        try{

            $omieOrderHandler = new OmieOrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $omieOrderHandler->deletedOrder($json);
            // if ($response) {
            //     echo"<pre>";
            //     json_encode($response);
            //     //grava log
            //     //$decoded = json_decode($response, true);
            //     ob_start();
            //     var_dump($response);
            //     $input = ob_get_contents();
            //     ob_end_clean();
            //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
            // }

        }catch(PedidoInexistenteException $e){
            // echo $e->getMessage();
        }catch(PedidoCanceladoException $e){
            // echo $e->getMessage();
        }catch(PedidoNaoExcluidoException $e){
            // echo $e->getMessage();
        }
        catch(PedidoDuplicadoException $e){
            // echo $e->getMessage();
        }catch(OrderControllerException $e){
            // echo $e->getMessage();
        }catch(ContactIdInexistentePloomesCRM $e){
            // echo $e->getMessage();
        }catch(InteracaoNaoAdicionadaException $e){
            // echo $e->getMessage();
        }finally{
            if (isset($e)){
                ob_start();
                echo $e->getMessage();
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
                return print $e->getMessage(); 
            }
            return print_r($response);
        }
    }

    public function alterOrderStage(){
        $json = file_get_contents('php://input');
            //$decoded = json_decode($json, true);

            // ob_start();
            // var_dump($json);
            // $input = ob_get_contents();
            // ob_end_clean();

            // file_put_contents('./assets/whkAlterStageOrder.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

        try{
            $omieOrderHandler = new OmieOrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $omieOrderHandler->alterOrderStage($json);
            // if ($response) {
            //     echo"<pre>";
            //     json_encode($response);
            //     //grava log
            //     //$decoded = json_decode($response, true);
            //     ob_start();
            //     var_dump($response);
            //     $input = ob_get_contents();
            //     ob_end_clean();
            //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
            // }

        }catch(WebhookReadErrorException $e){
            // echo $e->getMessage();
        }catch(InteracaoNaoAdicionadaException $e){
            // echo $e->getMessage();
        }finally{
            if (isset($e)){
                ob_start();
                echo $e->getMessage();
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND); 
                return print $e->getMessage();
            }
        }
        return print_r($response);
    }


}