<?php
namespace src\controllers;

use \core\Controller;
use PDOException;
use src\exceptions\WebhookReadErrorException;
use src\exceptions\DealNaoEncontradoBDException;
use src\exceptions\EstagiodavendaNaoAlteradoException;
use src\exceptions\FaturamentoNaoCadastradoException;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\NotaFiscalNaoCadastradaException;
use src\exceptions\NotaFiscalNaoCanceladaException;
use src\exceptions\NotaFiscalNaoEncontradaException;
use src\exceptions\PedidoNaoEncontradoOmieException;
use src\handlers\LoginHandler;
use src\handlers\InvoiceHandler;
use src\models\Deal;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;

class InvoicingController extends Controller {
    
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
        $total = Deal::select('id')->count();        
        $data = [
            'pagina' => 'Deals',
            'loggedUser'=>$this->loggedUser,
            'total'=>$total
        ];
        $this->render('gerenciador.pages.index', $data);
    }

    public function invoiceIssue()
    {
        $json = file_get_contents('php://input');

        // ob_start();
        // var_dump($json);
        // $input = ob_get_contents();
        // ob_end_clean();
        // file_put_contents('./assets/all.log', $input . PHP_EOL, FILE_APPEND);

        try{
            $invoiceHandler = new InvoiceHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $invoiceHandler->readInvoiceHook($json);
            
            // if ($response) {
            //     echo"<pre>";
            //     json_encode($response);
            //     print_r($response);
            //     //grava log
            //     //$decoded = json_decode($response, true);
            //     ob_start();
            //     var_dump($response);
            //     $input = ob_get_contents();
            //     ob_end_clean();
            //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
            //     exit;            
            // }
        }catch(WebhookReadErrorException $e){
                // echo '<pre>';
                // print $e->getMessage();
            }
        catch(DealNaoEncontradoBDException $e){
            // echo '<pre>';
            // print $e->getMessage();           
        }
        catch(EstagiodavendaNaoAlteradoException $e){
            // echo '<pre>';
            // print $e->getMessage();
        }
        catch(FaturamentoNaoCadastradoException $e){
            // echo '<pre>';
            // print $e->getMessage();
        }
        catch(InteracaoNaoAdicionadaException $e){
            // echo '<pre>';
            // print $e->getMessage();
        }
        catch(NotaFiscalNaoEncontradaException $e){
            // echo '<pre>';
            // print $e->getMessage();
        }
        catch(PedidoNaoEncontradoOmieException $e){
            // echo '<pre>';
            // print $e->getMessage();
        }
        finally{
            if(isset($e)){

                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
                return print $e->getMessage();
            }
            return print_r($response);
        }
        
    }

    public function deletedInvoice()
    {

        $json = file_get_contents('php://input');
           
        try{
            $invoiceHandler = new InvoiceHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $invoiceHandler->isDeletedInvoice($json);
            
            // if ($response) {
            //     echo"<pre>";
            //     json_encode($response);
            //     //print_r($response);
            //     //grava log
            //     //$decoded = json_decode($response, true);
            //     ob_start();
            //     var_dump($response);
            //     $input = ob_get_contents();
            //     ob_end_clean();
            //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
            //     exit;        
            //     // return print_r($response);    
            // }
        }catch(WebhookReadErrorException $e){
                // echo '<pre>';
                // print $e->getMessage();
            }
        catch(NotaFiscalNaoCadastradaException $e){
            // echo '<pre>';
            // print $e->getMessage();       
        }
        catch(NotaFiscalNaoCanceladaException $e){
            // echo '<pre>';
            // print $e->getMessage();
        }catch(PDOException $e){
            // echo '<pre>';
            // print $e->getMessage();
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
                
                return print $e->getMessage();
            }    
            return print_r($response);       
        }

    }

}