<?php
namespace src\controllers;

use \core\Controller;

use src\handlers\LoginHandler;

class TestController extends Controller {
    
    public function ping(){
        $r = [
            'pong'=>true
        ];
        $jsonR = json_encode($r);

    return print $jsonR;
    }

}