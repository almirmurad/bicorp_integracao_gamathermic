<?php

require_once 'C:/xampp/htdocs/gamatermic/vendor/autoload.php';
require_once 'C:/xampp/htdocs/gamatermic/src/services/RabbitMQServices.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

// Conectar ao RabbitMQ
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

// Declarar a exchange
$channel->exchange_declare('contacts_exc', 'topic', false, true, false);

// Declarar a fila de clientes
$queue_name = 'ploomes_contacts';
$channel->queue_declare($queue_name, false, true, false, false);

//binding_keys = origem.entidade.ação
$binding_key = 'Ploomes.Contacts';
//bind entre a fila e a exchange
$channel->queue_bind($queue_name, 'contacts_exc', $binding_key);

// Função callback para processar as mensagens da fila
$callback = function($msg) {
    try {
          echo ' [x] ', $msg->getRoutingKey(), ':', $msg->getBody(), "\n";

        // Processa a mensagem aqui
        // $headers = [
    
        //     'Content-Type: application/json',
        // ];

        // $uri = 'http://localhost/gamatermic/public/processNewContact';

        // $curl = curl_init();

        // curl_setopt_array($curl, array(
        //     CURLOPT_URL => $uri,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_ENCODING => '',
        //     CURLOPT_MAXREDIRS => 10,
        //     CURLOPT_TIMEOUT => 0,
        //     CURLOPT_FOLLOWLOCATION => true,
        //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //     CURLOPT_CUSTOMREQUEST => 'POST',
        //     CURLOPT_POSTFIELDS =>$msg->getBody(),
        //     CURLOPT_HTTPHEADER => $headers

        // ));

        // $response = curl_exec($curl);

        // curl_close($curl);
        
       // print_r($response);


        // Reconhece a mensagem
        $msg->ack();
    } catch (Exception $e) {
        // Lida com o erro
        echo 'Erro ao processar a mensagem: ', $e->getMessage(), "\n";
    }
};

// Consumir as mensagens da fila
//$channel->basic_qos(null, 1, false); //dispacha a mensagem apenas quando a anterior estiver sido processada
$channel->basic_consume($queue_name, '', false, false, false, false, $callback);

try {
    $channel->consume();
} catch (\Throwable $exception) {
    echo $exception->getMessage();
}

// Loop para manter o script rodando
while($channel->is_consuming()) {
    $channel->wait();
}

// Fechar conexões
$channel->close();
$connection->close();


