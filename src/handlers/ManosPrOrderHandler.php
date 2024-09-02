<?php

namespace src\handlers;

use PDOException;
use src\models\Manospr_order;

class ManosPrOrderHandler
{
    
    public static function saveManosPrOrder($order)
    {   
        $id = Manospr_order::insert(
            [
                'id_omie'=>$order->idOmie,
                'cod_cliente'=>$order->codCliente,
                'cod_pedido_integracao'=>$order->codPedidoIntegracao ?? null,
                'num_pedido_omie'=>$order->numPedidoOmie,
                'cod_cliente_integracao'=>$order->codClienteIntegracao ?? null,
                'data_previsao'=>$order->dataPrevisao,
                'num_conta_corrente'=>$order->numContaCorrente,
                'cod_vendedor_omie'=>$order->codVendedorOmie,
                'id_vendedor_ploomes'=>$order->idVendedorPloomes ?? null,   
                'app_key'=>$order->appKey ?? null,             
                'created_at'=>date('Y-m-d H:i:s'),
             ]
        )->execute();

        return ($id > 0 )? $id : false;
    }


    public static function isIssetOrder($orderNumber){

        try{

        $id = Manospr_order::select('id')
                ->where('id_omie',$orderNumber)
                ->execute();       
        
        return $id;
                    

        }catch(PDOException $e){
            return $e->getMessage();
        }

    }

    public static function alterManosPrOrder($orderNumber){


        try{

            Manospr_order::update()
                ->set('is_canceled', 1)
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->where('id_omie',$orderNumber)
                ->execute();

            return true;

        }catch(PDOException $e){
            return $e->getMessage();
        }

    }

    public static function excluiManosPrOrder($orderNumber){
        try{
         Manospr_order::delete()
         ->where('id_omie', $orderNumber)
         ->execute();
 
        }catch(PDOException $e){
             return $e->getMessage();
        }
         
         return true;
     }
}