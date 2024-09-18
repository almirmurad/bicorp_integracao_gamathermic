<?php
    //function index padrão dos controllers, a princípio desnecessário
    // public function index() {
    //     //$total = Deal::select('id')->count();        
    //     $data = [
    //         'pagina' => 'Pedidos',
    //         'loggedUser'=>$this->loggedUser,
    //         //'total'=>$total
    //     ];
    //     $this->render('gerenciador.pages.index', $data);
    // }

     //REPROCESSA O WEBHOOK COM FALHA não esta sendo usado no momento
    // public function reprocessWebhook($hook){
    //     $status = 4;//falhou
    //     //$hook = $this->databaseServices->getWebhook($status, 'Contacts');
    //     //$json = $hook['json'];
    //     $status = 2; //processando
    //     $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
        
    //     if($alterStatus){
            
    //         $createClient = Self::newClient($hook);
            
            
    //         if(!isset($createClient['contactsCreate']['error'])){
    //             $status = 3; //Sucesso
    //             $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
    //             if($alterStatus){
    //                 return $createClient;//card processado pedido criado no Omie retorna mensagem winDeal para salvr no log
    //             }

    //         }else{
    //             $status = 4; //falhou com mensagem
    //             $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                
    //             return $createClient;
    //         }
    //     }
        
    // }