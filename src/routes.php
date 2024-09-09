<?php
use core\Router;

$router = new Router();

$router->get('/', 'HomeController@index');
//Dashboard
$router->get('/dashboard', 'DashboardController@index');

//login-logout no painel
$router->get('/login', 'LoginController@signin'); 
$router->post('/login', 'LoginController@signinAction');
$router->get('/logout', 'LoginController@signout'); 

//webhooks
$router->get('/integrar', 'IntegracaoController@index');
$router->post('/integrar', 'IntegracaoController@integraAction');
$router->get('/getAll', 'IntegracaoController@getAll');
$router->get('/delHook/{id}', 'IntegracaoController@delHook');

//Deals
$router->get('/deals', 'DealController@index');
$router->post('/winDeal', 'DealController@winDeal');
$router->post('/deletedDeal', 'DealController@deletedDeal');
$router->post('/processWinDeal', 'DealController@processWinDeal');

//Orders
$router->post('/newOmieOrder', 'OrderController@newOmieOrder');//novo pedido no omie
$router->post('/deletedOrder', 'OrderController@deletedOrder');//Pedido deletado no omie
$router->post('/alterOrderStage', 'OrderController@alterOrderStage');//Pedido Alterado no kanban de vendas de produtos do omie


//contacts ploomes
$router->post('/ploomesContacts', 'ContactController@ploomesContacts');//Novo cliente no ploomes
$router->post('/omieClients', 'ContactController@omieClients');//Novo cliente no ploomes
$router->post('/processNewContact', 'ContactController@processNewContact'); //inicia o processo com cron job
$router->post('/alterClientPloomes', 'ContactController@alterClientPloomes'); //recebe um webhhok de cliente alterado no ploomes
//clientes Omie
$router->post('/newClientOmie', 'ClientController@newClientOmie'); //recebe um webhhok de cliente alterado no ploomesproccessAlterClientOmie
$router->post('/proccessAlterClientOmie', 'ClientController@proccessAlterClientOmie'); //recebe um webhhok de cliente alterado no 




//Invoices NFE
$router->post('/invoiceIssue', 'InvoicingController@invoiceIssue');
$router->post('/deletedInvoice', 'InvoicingController@deletedInvoice');



//Interactions
$router->get('/interactions', 'InteractionController@index');
$router->post('/newInteraction', 'InteractionController@createInteraction');

//Configurações
$router->get('/configs', 'ConfigController@index');
$router->post('/define', 'ConfigController@defineConfig');

//permissões
$router->get('/permissions', 'PermissionController@index');
$router->get('/addPermissionGroup', 'PermissionController@addPermissionGroup');
$router->get('/delGroupPermission/{id}', 'PermissionController@delGroupPermission');
$router->post('/addPermissionGroupAction', 'PermissionController@addPermissionGroupAction');

$router->get('/editPermissionGroup/{id}', 'PermissionController@editPermissionGroup');
$router->post('/editPermissionGroupAction/{id}', 'PermissionController@editPermissionGroupAction');

//Users
$router->get('/users', 'UserController@listUsers');
$router->get('/addUser','UserController@addUser');
$router->post('/addUser','UserController@addUserAction');
$router->get('/delUser/{id}', 'UserController@delUser');
$router->get('/user/{id}/editUser', 'UserController@editUser');
$router->post('/user/{id}/editUser','UserController@editUserAction');


