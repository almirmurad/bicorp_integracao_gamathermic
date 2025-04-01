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

//Orders
//https://gamatermic.bicorp.online/public/ploomesOrder
$router->post('/ploomesOrder', 'OrderController@ploomesOrder');//novo pedido no omie
$router->post('/processNewOrder', 'OrderController@processNewOrder');

//contacts ploomes clientes Omie
//https://gamatermic.bicorp.online/public/ploomesContacts
$router->post('/ploomesContacts', 'ContactController@ploomesContacts');//Novo cliente no ploomes
//https://gamatermic.bicorp.online/public/omieClients
$router->post('/omieClients', 'ContactController@omieClients');//Novo cliente no omie
$router->post('/processNewContact', 'ContactController@processNewContact'); //inicia o processo com cron job

//products
//https://gamatermic.bicorp.online/public/ploomesProducts
//https://gamatermic.bicorp.online/public/omieProducts
$router->post('/omieProducts', 'ProductController@omieProducts');//Novo produto no Omie
$router->post('/processNewProduct', 'ProductController@processNewProduct'); //inicia o processo com cron job
// $router->post('/ploomesProducts', 'ProductController@ploomesProducts'); //Novo produto no ploomes

//services
$router->post('/omieServices', 'ServiceController@omieServices');//Novo cliente no ploomes
$router->post('/processNewService', 'ServiceController@processNewService'); //inicia o processo com cron job

//Invoices NFE
$router->post('/invoiceIssue', 'InvoicingController@invoiceIssue');
$router->post('/deletedInvoice', 'InvoicingController@deletedInvoice');
$router->post('/processNewInvoice', 'InvoicingController@processNewInvoice');

//Nasajon
$router->post('/erpClients', 'ContactController@nasajonClients');//Novo cliente no nasajon
$router->post('/erpProducts', 'ProductController@nasajonProducts');//Novo produto no nasajon
$router->post('/erpServices', 'ServiceController@erpClients'); //Novo serviço no nasajon




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