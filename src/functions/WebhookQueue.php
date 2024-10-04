<?php
namespace src\functions;
use Ds\Queue;


// Exemplo de classe Singleton para DS_QUEUE
class WebhookQueue {
    private static $queue;

    public static function getInstance() {
        if (self::$queue === null) {
            self::$queue = new Queue(); // Inicializa a fila
        }
        return self::$queue;
    }
}

// // Ao receber o webhook:
// function receiveWebhook($webhookData) {
//     // Salva o webhook no banco de dados com status 1 (Recebido)
//     //saveWebhookToDB($webhookData);
//     $clientes = Cliente::getClientsDb();

    
//     // Adiciona o webhook à fila
//     $queue = WebhookQueue::getInstance();
//     $queue->push($webhookData);

//     return "Webhook enfileirado e salvo no BD.";
// }
