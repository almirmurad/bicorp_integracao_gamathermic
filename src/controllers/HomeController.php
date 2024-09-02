<?php
namespace src\controllers;

use \core\Controller;
use src\handlers\LoginHandler;
use src\models\Deal;

class HomeController extends Controller {
    
    private $loggedUser;

    public function __construct(){
        $this->loggedUser = LoginHandler::checkLogin();
        if($this->loggedUser === false || !in_array('dashboard_view', $this->loggedUser->permission )){
            $this->redirect('/login');
        }   
    }

    public function index() {


        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }
        // print_r($this->loggedUser->permission);
        // exit;
        $total = Deal::select('id')->count();      
        $data = [
            'pagina' => 'Dashboard',
            'loggedUser'=>$this->loggedUser,
            'total'=>$total,
            'flash'=>$flash
        ];
        $this->render('gerenciador.pages.index', $data);
    }
    

}