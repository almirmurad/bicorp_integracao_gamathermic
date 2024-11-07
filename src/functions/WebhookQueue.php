<?php
namespace src\functions;
use Ds\Queue;
use SplQueue;

// Exemplo de classe Singleton para DS_QUEUE
class WebhookQueue {
    private static $queue;

    public static function getInstance() {
        if (self::$queue === null) {
            self::$queue = new SplQueue; // Inicializa a fila
            self::$queue::IT_MODE_FIFO; // Inicializa a fila
        }

        return self::$queue;
    }
}


// // Ao receber o webhook:
// public static function receiveWebhook($webhookData) {
//     // Salva o webhook no banco de dados com status 1 (Recebido)
//     //saveWebhookToDB($webhookData);
//     // $hook = Deals::getClientsDb();

    
//     // Adiciona o webhook à fila
//     $queue = WebhookQueue::getInstance();
//     $queue->push($webhookData);

//     return "Webhook enfileirado e salvo no BD.";
// }