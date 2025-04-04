<?php
namespace src\contracts;

use src\models\Omie;

interface ErpManagerInterface{
    //BUSCA O ID DO CLIENTE OMIE
    public function clienteIdErp(object $erp, string $contactCnpj);
    //BUSCA O VENDEDOR Erp 
    public function vendedorIdErp(object $erp, string $mailVendedor);
    //BUSCA ID DO PRODUTO NO Erp
    public function buscaIdProductErp(object $erp, string $idItem);
    //CRIA O PEDIDO NO Erp 
    public function criaPedidoErp(object $erp, object $order, array $productsOrder);
    //CRIA O SERVIÇO NO Erp 
    public function criaOS(object $erp, object $os, array $serviceOrder, array $produtosUtilizados);
    //ENCONTRA O CNPJ DO CLIENTE NO Erp
    public function clienteCnpjErp(object $erp);
    //ENCONTRA O PEDIDO ATRAVÉS DO ID DO Erp
    public function consultaPedidoErp(object $erp, int $idPedido);
    //CONSULTA NOTA FISCAL NO Erp
    public function consultaNotaErp(object $erp, int $idPedido );
    
}