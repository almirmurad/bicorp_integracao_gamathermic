<?php

require '/opt/consumers/vendor/autoload.php';

use Dotenv\Dotenv;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createUnsafeImmutable('/opt/consumers/gamatermic', '.env');
$dotenv->load();

// Constantes para configuração do consumidor
define('EXCHANGE_MAIN', 'products_exc');
define('EXCHANGE_TRASH', 'products_exc_trash');
define('QUEUE_MAIN', 'omie_products');
define('QUEUE_TRASH', 'omie_products_trash');
define('BINDING_KEY_MAIN', 'Omie.Products');
define('BINDING_KEY_TRASH', 'Omie.Products.Trash');
define('RETRY_LIMIT', 2);
define('PROCESS_URI', 'https://gamatermic.bicorp.online/public/processNewOrder');

// Variável de controle para encerramento
$stop = false;

// Captura sinais do sistema para encerramento seguro
pcntl_signal(SIGTERM, function () use (&$stop) {
    $stop = true;
});
pcntl_signal(SIGINT, function () use (&$stop) {
    $stop = true;
});

// Função para criar e retornar a conexão RabbitMQ
function createConnection()
{
    try {
        return new AMQPStreamConnection(
            $_ENV['IP'],
            $_ENV['PORT'],
            $_ENV['RABBITMQ_USER'],
            $_ENV['RABBITMQ_PASS'],
            $_ENV['RABBITMQ_VHOST']
        );
    } catch (Exception $e) {
        echo "[Erro] Conexão: " . $e->getMessage() . "\n";
        sleep(5); // Aguarda antes de tentar novamente
        return createConnection(); // Tenta reconectar
    }
}

// Configuração inicial
$connection = createConnection();
$channel = $connection->channel();

// Função para declarar a estrutura das filas e exchanges
function setupRabbitMQ($channel)
{
    try {
        // Declarar exchanges
        $channel->exchange_declare(EXCHANGE_MAIN, 'x-delayed-message', false, true, false, false, false, [
            'x-delayed-type' => ['S', 'topic']
        ]);
        $channel->exchange_declare(EXCHANGE_TRASH, 'direct', false, true, false);

        // Declarar as filas
        $channel->queue_declare(QUEUE_MAIN, false, true, false, false, false, [
            'x-dead-letter-exchange' => ['S', EXCHANGE_MAIN . '_wait'],
            'x-dead-letter-routing-key' => ['S', BINDING_KEY_MAIN . '.Wait']
        ]);
        $channel->queue_declare(QUEUE_TRASH, false, true, false, false, false);

        // Binding entre filas e exchanges
        $channel->queue_bind(QUEUE_MAIN, EXCHANGE_MAIN, BINDING_KEY_MAIN);
        $channel->queue_bind(QUEUE_TRASH, EXCHANGE_TRASH, BINDING_KEY_TRASH);

        return [QUEUE_MAIN, BINDING_KEY_TRASH];
    } catch (Exception $e) {
        echo "[Erro] Configuração RabbitMQ: " . $e->getMessage() . "\n";
        exit;
    }
}

// Configurar estrutura RabbitMQ
[$queue_name, $trash_binding_key] = setupRabbitMQ($channel);

// Callback para processar mensagens
$callback = function ($msg) use ($channel, $trash_binding_key) {
    $isAcked = false;

    try {
        $application_headers = $msg->get('application_headers');
        $xDeath = isset($application_headers['x-death']) ? $application_headers['x-death'] : [];
        $retryCount = !empty($xDeath) ? $xDeath[0]['count'] : 0;

        if ($retryCount >= RETRY_LIMIT) {
            // Envia para fila de trash após 3 tentativas
            $newMsg = new AMQPMessage($msg->getBody(), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]);
            $channel->basic_publish($newMsg, EXCHANGE_TRASH, $trash_binding_key);
            $msg->ack(); // Remove mensagem da fila wait
            $isAcked = true;
            throw new Exception('Mensagem reprocessada mais de 3 vezes, enviada para o lixo', 500);
        }

        // Processa a mensagem
        $headers = ['Content-Type: application/json'];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => PROCESS_URI,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $msg->getBody(),
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        if (isset($response['status_code']) && $response['status_code'] === 200) {
            $msg->ack(); // Marca mensagem como processada
            echo "[Sucesso] Mensagem processada: " . $response['status_message'] . PHP_EOL;
        } else {
            $statusMessage = $response['status_message'] ?? 'Mensagem indefinida';
            throw new Exception($statusMessage, 500);
        }
    } catch (Exception $e) {
        echo "[Erro] Processamento: " . $e->getMessage() . PHP_EOL;

        if (!$isAcked) {
            $msg->nack(false, false); // Retorna mensagem para DLX
        }
    }
};

// Configurar consumo
$channel->basic_qos(null, 1, false); // Mensagem por vez
$channel->basic_consume($queue_name, '', false, false, false, false, $callback);

// Loop principal
while (!$stop) {
    try {
        $channel->wait();
        pcntl_signal_dispatch(); // Verifica sinais do sistema
    } catch (\PhpAmqpLib\Exception\AMQPConnectionClosedException $e) {
        echo "[Erro] Conexão perdida. Tentando reconectar...\n";
        $connection = createConnection();
        $channel = $connection->channel();
        [$queue_name, $trash_binding_key] = setupRabbitMQ($channel);
        $channel->basic_consume($queue_name, '', false, false, false, false, $callback);
    } catch (Exception $e) {
        echo "[Erro] Geral: " . $e->getMessage() . "\n";
    }
}

// Encerrar conexões
$channel->close();
$connection->close();
