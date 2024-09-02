<?php

namespace src\handlers;

use PDOException;
use src\models\Manospr_invoicing;

class ManosPrInvoicingHandler
{
    
    public static function saveManosPrInvoicing($invoicing)
    {   
        $id = Manospr_invoicing::insert(
            [
                'stage'=>$invoicing->etapa,
                'invoicing_date'=>$invoicing->dataFaturado,
                'invoicing_time'=>$invoicing->horaFaturado,
                'client_id'=>$invoicing->idCliente,
                'order_id'=>$invoicing->idPedido,
                'invoice_number'=>$invoicing->nNF,
                'order_number'=>$invoicing->numeroPedido,
                'order_amount'=>$invoicing->valorPedido,
                'user_id'=>$invoicing->authorId,
                'user_email'=>$invoicing->authorEmail,
                'user_name'=>$invoicing->authorName,
                'appkey'=>$invoicing->appKey,
                'created_at'=>date('Y-m-d H:i:s'),
             ]
        )->execute();

        if(empty($id)){
            return "Erro ao cadastrar Faturamenro no banco de dados.";
        }
        return $id;
    }

    public static function isIssetInvoice($orderNumber){

        try{

        $id = Manospr_invoicing::select('id')
                ->where('order_id',$orderNumber)
                ->where('is_canceled',0)
                ->execute();       
        
        return $id;
                    

        }catch(PDOException $e){
            return $e->getMessage();
        }

    }

    public static function alterManosPrInvoiceHandler($orderNumber){


        try{

            Manospr_invoicing::update()
                ->set('is_canceled', 1)
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->where('order_id',$orderNumber)
                ->execute();

            return true;

        }catch(PDOException $e){
            return $e->getMessage();
        }

    }
}