<?php
use core\Router;

$router = new Router();
$router->get('/', 'HomeController@index');
//
//ping
//
$router->get('/ping', 'TestController@ping');
//Dashboard
$router->get('/dashboard', 'DashboardController@index');
//
//login-logout no gerenciador
$router->get('/login', 'LoginController@signin'); 
$router->post('/login', 'LoginController@signinAction');
$router->get('/logout', 'LoginController@signout'); 
//
//webhooks "Gerencia os webhooks no ploomes através do gerenciador"
$router->get('/integrar', 'IntegracaoController@index');
$router->post('/integrar', 'IntegracaoController@integraAction');
$router->get('/getAll', 'IntegracaoController@getAll');
$router->get('/delHook/{id}', 'IntegracaoController@delHook');
//
//contacts ploomes clientes Nasajon Integração Bilateral
//
//Ploomes CRM 
//https://gamatermic.bicorp.online/public/ploomesContacts
$router->post('/ploomesContacts', 'ContactController@ploomesContacts');//Novo cliente no ploomes
//
//Nasajon ERP
$router->post('/erpClients', 'ContactController@nasajonClients');//Novo cliente no nasajon
//
//processa o contato de ambos os lados
$router->post('/processNewContact', 'ContactController@processNewContact'); //inicia o processo com cron job
//
//products "produtos são enviados do ERP ao CRM Integração Unilateral
//https://gamatermic.bicorp.online/public/
$router->post('/erpProducts', 'ProductController@nasajonProducts');//Novo produto no nasajon
//processa o produto ERP para CRM
$router->post('/processNewProduct', 'ProductController@processNewProduct'); //inicia o processo com cron job
//
//services "services são enviados do ERP ao CRM Integração Unilateral"
$router->post('/erpServices', 'ServiceController@erpClients'); //Novo serviço no nasajon
//processa o serviço ERP para CRM
$router->post('/processNewService', 'ServiceController@processNewService'); //inicia o processo com cron job
//
//Orders "Pedidos são enviados do CRM ao ERP Integração Unilateral"
//https://gamatermic.bicorp.online/public/ploomesOrder
$router->post('/ploomesOrder', 'OrderController@ploomesOrder');//novo pedido no ploomes
//processa o pedido CRM para ERP
$router->post('/processNewOrder', 'OrderController@processNewOrder');
//
//Invoices NFE "Envia notificações ao ploomes da nota fiscal emitida"
$router->post('/invoiceIssue', 'InvoicingController@invoiceIssue');
$router->post('/deletedInvoice', 'InvoicingController@deletedInvoice');
$router->post('/processNewInvoice', 'InvoicingController@processNewInvoice');
//
//Interactions "Envia as mensagens ao Ploomes"
$router->get('/interactions', 'InteractionController@index');
$router->post('/newInteraction', 'InteractionController@createInteraction');
//
//Configurações
$router->get('/configs', 'ConfigController@index');
$router->post('/define', 'ConfigController@defineConfig');
//
//permissões
$router->get('/permissions', 'PermissionController@index');
$router->get('/addPermissionGroup', 'PermissionController@addPermissionGroup');
$router->get('/delGroupPermission/{id}', 'PermissionController@delGroupPermission');
$router->post('/addPermissionGroupAction', 'PermissionController@addPermissionGroupAction');
$router->get('/editPermissionGroup/{id}', 'PermissionController@editPermissionGroup');
$router->post('/editPermissionGroupAction/{id}', 'PermissionController@editPermissionGroupAction');
//
//Users
$router->get('/users', 'UserController@listUsers');
$router->get('/addUser','UserController@addUser');
$router->post('/addUser','UserController@addUserAction');
$router->get('/delUser/{id}', 'UserController@delUser');
$router->get('/user/{id}/editUser', 'UserController@editUser');
$router->post('/user/{id}/editUser','UserController@editUserAction');