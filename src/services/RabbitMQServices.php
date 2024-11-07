<?php

namespace src\services;
// Arquivo: RabbitMQService.php

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQServices {
    private $connection;
    private $channel;

    public function __construct() {
        // Conecta ao RabbitMQ
        $this->connection = new AMQPStreamConnection($_ENV['IP'], $_ENV['PORT'], $_ENV['RABBITMQ_USER'], $_ENV['RABBITMQ_PASS'], $_ENV['RABBITMQ_VHOST']
    );//aqui trocar por variáveis de ambiente
        $this->channel = $this->connection->channel();
    }
    
    public function publicarMensagem(string $exName, array $rk, string $fila, string $mensagem) {
        //Routing_key
        $routing_key = isset($rk) && !empty($rk) ? $rk[0].'.'.$rk[1] : 'Origem.Entity';
        //Routing_key wait (aguardar para reprocessar)
        $routing_key_dlx = isset($rk) && !empty($rk) ? $rk[0].'.'.$rk[1].'.Wait' : 'Origem.Entity.Wait';
        $routing_key_trash = isset($rk) && !empty($rk) ? $rk[0].'.'.$rk[1].'.Trash' : 'Origem.Entity.Trash';
        try{

            //declara a exchange do tipo topic
            $this->channel->exchange_declare($exName, 'x-delayed-message', false, true, false, false, false,[
                'x-delayed-type' => ['S', 'topic']
            ]);
            //declara a exchange do tipo topic wait (aguardar para reprocessar)
            $this->channel->exchange_declare($exName.'_wait', 'x-delayed-message', false, true, false, false, false,[
                'x-delayed-type' =>  ['S', 'topic']
            ]);
            //declara a exchange do tipo direct trash (lixo)
            $this->channel->exchange_declare($exName.'_trash', 'direct', false, true, false, false, false);
        }catch(Exception $e){

            print $e->getMessage();
            exit;
        }
        try{
            // Garante que a fila existe
            $this->channel->queue_declare($fila, false, true, false, false, false, [   // Argumentos para a DLX
                'x-dead-letter-exchange' =>  ['S', $exName . '_wait'],  // Define a Dead Letter Exchange
                'x-dead-letter-routing-key' => ['S', $routing_key_dlx] // Define a Routing Key para a DLX
            ]);
            // Garante que a fila existe wait (aguardar para reprocessar)
            $this->channel->queue_declare($fila.'_wait', false, true, false, false, false,
            [
                'x-message-ttl' =>  ['I', 30000],  // A mensagem na fila DLX terá um TTL de 30 segundos
                'x-dead-letter-exchange' => ['S', $exName], // Volta para a exchange original
                'x-dead-letter-routing-key' =>['S', $routing_key], // Volta com a mesma routing key
            ]);
            // Garante que a fila existe trash (Lixo após 3 tentativas de processamento)
            $this->channel->queue_declare($fila.'_trash', false, true, false, false, false);
        }catch(Exception $e){

            print $e->getMessage();
            exit;

        }

        // Associar a Dead Letter Queue à Dead Letter Exchange
        $this->channel->queue_bind($fila, 'orders_exc', $routing_key);                  
        $this->channel->queue_bind($fila.'_wait', $exName.'_wait',$routing_key_dlx);
        $this->channel->queue_bind($fila.'_trash', 'orders_exc_trash', $routing_key_trash);                  
        // Cria a mensagem com atraso de 4 segundos
        $headers = new AMQPTable(['x-delay' => 4000]);
        $msg = new AMQPMessage($mensagem, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, 'application_headers' => $headers]);
        
        // Publica a mensagem na fila
        $this->channel->basic_publish($msg, $exName, $routing_key);

    }

    public function getConnection(){
        return $this->connection;
    }

    public function getChannel(){
        return $this->channel;
    }

    public function __destruct() {
        // Fecha conexões e canais
        $this->channel->close();
        $this->connection->close();
    }
}
