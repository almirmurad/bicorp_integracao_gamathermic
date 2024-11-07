<?php
namespace src\controllers;

use \core\Controller;

use src\handlers\DealHandler;
use src\handlers\LoginHandler;
use src\exceptions\WebhookReadErrorException;
use src\exceptions\BaseFaturamentoInexistenteException;
use src\exceptions\DealNaoExcluidoBDException;
use src\models\Deal;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class DealController extends Controller {
    
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

    public function index() 
    {
        $total = Deal::select('id')->count();        
        $data = [
            'pagina' => 'Deals',
            'loggedUser'=>$this->loggedUser,
            'total'=>$total
        ];
        $this->render('gerenciador.pages.index', $data);
    }

    public function winDeal()
    {
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
            
            $dealHandler = new DealHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $dealHandler->saveDealHook($json);
                        
             // $rk = origem.entidade.ação
             $rk = array('Ploomes','Deals');
             $this->rabbitMQServices->publicarMensagem('deals_exc', $rk, 'ploomes_deals',  $json);

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

    public function deletedDeal()
    {
        $json = file_get_contents('php://input');

        ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/all.log', $input . PHP_EOL, FILE_APPEND);

        try{
            $dealHandler = new DealHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $dealHandler->deletedDealHook($json);

            if ($response) {
                echo"<pre>";
                json_encode($response);
                print_r($response);
                //grava log
                //$decoded = json_decode($response, true);
                ob_start();
                var_dump($response);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                exit;            
            }
        }catch(WebhookReadErrorException $e){
            echo '<pre>';
            print $e->getMessage();           
        }
        catch(BaseFaturamentoInexistenteException $e){
            echo '<pre>';
            print $e->getMessage();
        }
        catch(DealNaoExcluidoBDException $e){
            echo '<pre>';
            print $e->getMessage();
        }
        finally{
            ob_start();
            var_dump($e->getMessage());
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
            exit; 
        }

        $ping = json_encode([
            'pong' => 'true',
            'message' => $response,
        ]);

        return print_r($ping);
        
    }
    public function processWinDeal(){
        $json = file_get_contents('php://input');
        $message = [];
        /**
         * processa o webhook 
         */
        try{
            
            $dealHandler = new DealHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $dealHandler->startProcess($json);

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