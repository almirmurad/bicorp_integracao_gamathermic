<?php

namespace src\services;

use src\contracts\OmieManagerInterface;
use src\functions\DiverseFunctions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use src\exceptions\WebhookReadErrorException;

class OmieServices implements OmieManagerInterface{


    public function clienteCnpjOmie($order)
    {
        $jsonOmieIdCliente = [
            'app_key' => $order->appKey,
            'app_secret' => $order->appSecret,
            'call' => 'ConsultarCliente',
            'param' => [
                [
                    'codigo_cliente_omie'=>$order->codCliente
                ]
            ]
                ];

        $jsonCnpj = json_encode($jsonOmieIdCliente);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonCnpj,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $cliente = json_decode($response, true);
        $cnpj = DiverseFunctions::limpa_cpf_cnpj($cliente['cnpj_cpf']);

        return $cnpj;
    }
 
    //PEGA O ID DO CLIENTE DO OMIE
    public function clienteIdOmie($omie, $contactCnpj)
    {
        
        
        $jsonOmieIdCliente = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ListarClientes',
            'param' => [
                [
                    'clientesFiltro'=>['cnpj_cpf'=> $contactCnpj]
                ]
            ]
                ];

        $jsonCnpj = json_encode($jsonOmieIdCliente);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonCnpj,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $cliente = json_decode($response, true);
        
        $idClienteOmie = $cliente['clientes_cadastro'][0]['codigo_cliente_omie'];
        
        return $idClienteOmie;
    }

    //PEGA O ID DO vendedor DO OMIE
    public function vendedorIdOmie($omie, $mailVendedor)
    {

        $jsonOmieVendedor = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ListarVendedores',
            'param' => [
                [
                    'filtrar_por_email'=>$mailVendedor
                ]
            ]
                ];

        $jsonVendedor = json_encode($jsonOmieVendedor);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/vendedores/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonVendedor,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
       
        $vendedor = json_decode($response,true);
       
        $codigoVendedor = '';
        $arrayVendedores = $vendedor['cadastro'];
        if(count($arrayVendedores) > 1){
            foreach($arrayVendedores as $itArrVend){
            
                if($itArrVend['inativo'] && $itArrVend['inativo'] === 'N'){
                    $codigoVendedor = $itArrVend['codigo'];
                }
            }
        }else{
            foreach($arrayVendedores as $itArrVend){
                    $codigoVendedor = $itArrVend['codigo'];
            }
        }
   
        return $codigoVendedor;
    }

    //BUSCA O ID DE UM PRODUTO BASEADO NO CODIGO DO PRODUTO NO PLOOMES
    public function buscaIdProductOmie($omie, $idItem)
    {
        $jsonId = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ConsultarProduto',
            'param' => [
                [
                    'codigo'=>$idItem
                ]
            ],
        ];

        $jsonId = json_encode($jsonId);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/produtos/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonId,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $item = json_decode($response);
        
        $id = $item->codigo_produto;
        
        return $id;

    }

    //CRIA PEDIDO NO OMIE
    public function criaPedidoOmie(object $omie, string $idClienteOmie, object $deal, array $productsOrder, string $codVendedorOmie, string $notes, string $parcelamento)
    {   
        //$det = [];//informações dos produtos da venda(array de arrays)
        //$ide=[];//array de informações do produto vai dentro do array det com por exemplo codigo_item_integracao(codigo do item no ploomes)
        //$produto = [];//array de informações do produto específico, codigo quantidade valor unitário. infos do item no omie. dentro de det
        //$parcela = []; //info de cada parcela individualmente data_vencimento, numero_parcela, percentual, valor (array de arrays) vai dentro de lista_parcelas
        
        // cabeçalho da requisição ($appKey,$appSecret, call(metodo))
        $top = [
            'app_key' =>   $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'IncluirPedido',
            'param'=>[],
        ];
        // $parcelas = explode('/',$parcelamento);
        // $qtdeParcelas = count($parcelas);
        
        // cabecalho
        $cabecalho = [];//cabeçalho do pedido (array)
        $cabecalho['codigo_cliente'] = $idClienteOmie;//int
        $cabecalho['codigo_pedido_integracao'] = $deal->lastOrderId;//string
        $cabecalho['data_previsao'] = DiverseFunctions::convertDate($deal->previsaoFaturamento) ?? "";//string
        $cabecalho['etapa'] = '10';//string
        $cabecalho['numero_pedido'] = $deal->lastOrderId;//string
        //$cabecalho['tipo_desconto_pedido'] = 'P';//string
        //$cabecalho['perc_desconto_pedido'] = 5;//string
        $cabecalho['codigo_parcela'] = DiverseFunctions::getIdParcelamento($parcelamento);//string'qtde_parcela'=>2
        //$cabecalho['qtde_parcelas'] = $qtdeParcelas;//string=>2
        $cabecalho['origem_pedido'] = 'API';//string
        //$cabecalho['quantidade_itens'] = 1;//int
        //$cabecalho['codigo_cenario_impostos'] = 12315456498798;//int
        
        //ide primeiro pois vai dentro de det
        // $ide['codigo_item_integracao'] = $productsOrder['id_item'];//codigo do item da integração específica;//string
        
        //produto antes pois vai dentro de det
        // $produto['codigo_produto'] = 3342938591;
        // $produto['quantidade'] = 1;//int
        // $produto['valor_unitario'] = 200;
        
        //det array com ide e produto
        // $det['ide'] = $ide;
        // $det['produto'] = $produto;
        
        //frete
        $frete = [];//array com infos do frete, por exemplo, modailidade;
        $frete['modalidade'] = '9';//string
        //$frete['previsao_entrega'] = DiverseFunctions::convertDate($deal->previsaoEntrega);//string
        
        //informações adicionais
        $informacoes_adicionais = []; //informações adicionais por exemplo codigo_categoria = 1.01.03, codigo_conta_corrente = 123456789
        $informacoes_adicionais['codigo_categoria'] = '1.01.01';//string
        $informacoes_adicionais['codigo_conta_corrente'] = $omie->ncc;//int
        // $informacoes_adicionais['consumidor_final'] = 'S';//string
        // $informacoes_adicionais['enviar_email'] = 'N';//string
        $informacoes_adicionais['numero_pedido_cliente']=$deal->numPedidoCliente ?? "0";
        $informacoes_adicionais['codVend']=$codVendedorOmie;
        $informacoes_adicionais['codproj']= $omie->codProjeto ?? null;

        //lista parcelas
        //$lista_parcelas = [];//array de parcelas
        //$lista_parcelas['parcela'] = DiverseFunctions::calculaParcelas( date('d-m-Y'),$parcelamento, $arrayRequestOrder['Amount']);
        
        //observbacoes
        $observacoes =[];
        $observacoes['obs_venda'] = $notes;
        
        //exemplo de parcelsa
        //$totalParcelas = "10/15/20";
        $newPedido = [];//array que engloba tudo
        $newPedido['cabecalho'] = $cabecalho;
        $newPedido['det'] = $productsOrder;
        $newPedido['frete'] = $frete;
        $newPedido['informacoes_adicionais'] = $informacoes_adicionais;
        //$newPedido['lista_parcelas'] = $lista_parcelas;
        $newPedido['observacoes'] = $observacoes;
        $top['param'][]= $newPedido;

        // $jsonPedido = json_encode($top, JSON_UNESCAPED_UNICODE);
        // print_r($jsonPedido);
        // exit;

        // $jsonOmie = json_encode($jsonOmie,JSON_UNESCAPED_UNICODE);
        $client = new Client([
            'handler' => new CurlHandler([
                 'handle_factory' => new CurlFactory(0)
            ])
        ]);

        $response = $client->post('https://app.omie.com.br/api/v1/produtos/pedido/',[
            "json" => $top
        ]);
        //$code = $response->getStatusCode();
        $body = json_decode($response->getBody(),true); 
      
        return $body;

        
    }

    // busca o pedido através do Id do OMIE
    public function consultaPedidoOmie(object $omie, int $idPedido)
    {

        $array = [
                    'app_key'=>$omie->appKey,
                    'app_secret'=>$omie->appSecret,
                    'call'=>'ConsultarPedido',
                    'param'=>[
                            [
                                'codigo_pedido'=>$idPedido,
                            ]
                        ]
                ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/produtos/pedido/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        return json_decode($response, true);

    } 

    //consulta nota fiscal no omie
    public function consultaNotaOmie(object $omie, int $idPedido)
    {
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'ConsultarNF',
            'param'=>[
                    [
                        'nIdPedido'=>$idPedido,
                    ]
                ]
            ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/produtos/nfconsultar/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $nfe = json_decode($response, true);
        return $nfe['ide']['nNF'];
    }
    //Cria cliente no Omie ERP
    public function criaClienteOmie(object $omie, object $contact)
    {
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'IncluirCliente',
            'param'=>[]
        ];

        $clienteJson = [];
        $clienteJson['codigo_cliente_integracao'] = $contact->id;
        $clienteJson['razao_social'] = $contact->name;
        $clienteJson['nome_fantasia'] = $contact->legalName ?? null;
        $clienteJson['cnpj_cpf'] = $contact->cnpj ?? $contact->cpf;
        $clienteJson['email'] = $contact->email;
        $clienteJson['telefone1_ddd'] = $contact->ddd1;
        $clienteJson['telefone1_numero'] = $contact->phone1;
        $clienteJson['contato'] = $contact->contato1;
        $clienteJson['endereco'] = $contact->streetAddress;
        $clienteJson['endereco_numero'] = $contact->streetAddressNumber;
        $clienteJson['bairro'] = $contact->neighborhood;
        $clienteJson['complemento'] = $contact->streetAddressLine2 ?? null;
        $clienteJson['estado'] = $contact->stateShort;//usar null para teste precisa pegar o codigo da sigla do estado na api omie
        //$clienteJson['cidade'] = $contact->cityName;
        $clienteJson['cidade_ibge'] = $contact->cityId;
        // $clienteJson['cep'] = $contact->streetAdress ?? null;
        $clienteJson['cep'] = $contact->zipCode ?? null;
        $clienteJson['cnae'] = null;
        $clienteJson['recomendacoes'] =[];
        //$recomendacoes=[];//vendedor padrão
        //$recomendacoes['codigo_vendedor'] = $contact->ownerId;
        //$clienteJson['recomendacoes'][] = $recomendacoes;

        $caracteristicas = [];
        //$caracteristicasCampo=[];
        //$caracteristicasConteudo=[];
        $caracteristicasCampo = 'Regiao';
        $caracteristicasConteudo = $contact->regiao;
        $caracteristicas['campo'] = $caracteristicasCampo;
        $caracteristicas['conteudo']=$caracteristicasConteudo;
        $clienteJson['caracteristicas'] = $caracteristicas;
        //$clienteJson['tags']=[];
        $clienteJson['tags']=$contact->tags;
         
        $array['param'][] = $clienteJson;

        $json = json_encode($array);     

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $cliente = json_decode($response, true);

        return $cliente;

    }
    // busca o pedido através do Id do OMIE
    public function buscaIdProjetoOmie(object $omie, string $projetoName)
    {
        $array = [
                    'app_key'=>$omie->appKey,
                    'app_secret'=>$omie->appSecret,
                    'call'=>'ListarProjetos',
                    'param'=>[
                            [
                                'apenas_importado_api'=> 'N',
                                'nome_projeto'=> $projetoName
                            ]
                        ]
                ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/projetos/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $projeto = json_decode($response, true);

        return ($projeto['registros'] <= 0 || $projeto['cadastro'][0]['inativo'] === 'S') ? false : $projeto['cadastro'][0]['codigo'];



    } 

    public function alteraCliente(object $omie, object $contact){

        
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'AlterarCliente',
            'param'=>[]
        ];

        $clienteJson = [];
        $clienteJson['codigo_cliente_omie'] = $contact->codigoOmie;
        $caracteristicas = [];
        //$caracteristicasCampo=[];
        //$caracteristicasConteudo=[];
        $caracteristicasCampo = 'Região';
        $caracteristicasConteudo = $contact->regiao;
        $caracteristicas['campo'] = $caracteristicasCampo;
        $caracteristicas['conteudo']=$caracteristicasConteudo;
        $clienteJson['caracteristicas'] = $caracteristicas;
        
         
        $array['param'][] = $clienteJson;

        $json = json_encode($array);     

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $cliente = json_decode($response, true);
        $message = [];
        if(!isset($cliente['faultstring'])){
             $message['alterClient']['success'] = $cliente['descricao_status'];
             return $message;
        }else{
            $message['alterClient']['error'] = $cliente['faultstring'];
            return $message;
        }


    }
  
}