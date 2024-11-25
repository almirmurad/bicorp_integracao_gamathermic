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
            'app_key' => $order->appKey ,
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
        if(!isset($vendedor['cadastro'])){
            return null;
        }
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

    //PEGA O ID DO vendedor DO OMIE
    public function getMailVendedorById($omie, $contact)
    {
        // print_r($omie);
        // print $mailVendedor;
        // exit;
        $jsonOmieVendedor = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ConsultarVendedor',
            'param' => [
                [
                    'codigo'=>$contact->codigoVendedor,

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
        
        
        return $vendedor['email'] ?? null;
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

    public function insertProject($omie, $projectName){
        $array = [
            'app_key' =>   $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'IncluirProjeto',
            'param'=>[
                [
                    'codint'=> $projectName,
                    'nome'=>$projectName,
                    'inativo'=> "N"
                ]
            ],
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
       
        $response = json_decode($response,true);
        
        return $response;


    }

    public function deleteProject($omie){
        $array = [
            'app_key' =>   $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ExcluirProjeto',
            'param'=>[
                [
                    'codigo'=> $omie->codProjeto
                ]
            ],
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
       
        $response = json_decode($response,true);
        
        return $response;


    }

    //CRIA PEDIDO NO OMIE
    public function criaPedidoOmie(object $omie, object $order, array $productsOrder)
    {   
        
        $top = [
            'app_key' =>   $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'IncluirPedido',
            'param'=>[],
        ];

        
        // cabecalho
        $cabecalho = [];//cabeçalho do pedido (array)
        $cabecalho['codigo_cliente'] = $order->idClienteOmie;//int
        $cabecalho['codigo_pedido_integracao'] = 'VEN_PRD/'.$order->id;//string
        $cabecalho['data_previsao'] = DiverseFunctions::convertDate($order->previsaoFaturamento);//string
        $cabecalho['etapa'] = '10';//string
        $cabecalho['numero_pedido'] = $order->id;//string
        //$cabecalho['tipo_desconto_pedido'] = 'P';//string
        //$cabecalho['perc_desconto_pedido'] = 5;//string
        $cabecalho['codigo_parcela'] = $order->idParcelamento ?? '000';//string'qtde_parcela'=>2
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
        $frete['modalidade'] = $order->modalidadeFrete ?? null;//string
        //$frete['previsao_entrega'] = DiverseFunctions::convertDate($order->previsaoEntrega);//string
        
        //informações adicionais
        $informacoes_adicionais = []; //informações adicionais por exemplo codigo_categoria = 1.01.03, codigo_conta_corrente = 123456789
        $informacoes_adicionais['codigo_categoria'] = '1.01.01';//string
        $informacoes_adicionais['codigo_conta_corrente'] = $omie->ncc;//int
        // $informacoes_adicionais['consumidor_final'] = 'S';//string
        // $informacoes_adicionais['enviar_email'] = 'N';//string
        $informacoes_adicionais['numero_pedido_cliente']=$order->numPedidoCliente ?? "0";
        $informacoes_adicionais['codVend']=$order->codVendedorOmie ?? null;
        $informacoes_adicionais['codproj']= $omie->codProjeto ?? null;
        $informacoes_adicionais['dados_adicionais_nf'] = $order->notes;

        //lista parcelas
        //$lista_parcelas = [];//array de parcelas
        //$lista_parcelas['parcela'] = DiverseFunctions::calculaParcelas( date('d-m-Y'),$parcelamento, $arrayRequestOrder['Amount']);
        
        //observbacoes
        $observacoes =[];
        $observacoes['obs_venda'] = $order->notes;
        
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

    public function criaOS(object $omie, object $os, array $serviceOrder, array $produtosUtilizados)
    {
        $top = [
            'app_key' =>   $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'IncluirOS',
            'param'=>[],
        ];

        $cabecalho = [];//cabeçalho do pedido (array)
        $cabecalho['nCodCli'] = $os->idClienteOmie;//int
        $cabecalho['cCodIntOS'] = 'VEN_SRV/'.$os->id;//string
        $cabecalho['dDtPrevisao'] = DiverseFunctions::convertDate($os->previsaoFaturamento);//string
        $cabecalho['cEtapa'] = '10';//string
        $cabecalho['cCodParc'] =  $os->idParcelamento ?? '000';//string'qtde_parcela'=>2
        $cabecalho['nQtdeParc'] = 3;//string'qtde_parcela'=>2
        $cabecalho['nCodVend'] = $os->codVendedorOmie;//string'qtde_parcela'=>2

        $InformacoesAdicionais = []; //informações adicionais por exemplo codigo_categoria = 1.01.02 p/ serviços
        $InformacoesAdicionais['cCodCateg'] = '1.01.02';//string
        $InformacoesAdicionais['nCodCC'] = $omie->ncc;//int
        $InformacoesAdicionais['cDadosAdicNF'] = $os->notes;//string
        $InformacoesAdicionais['cNumPedido']=$os->numPedidoCliente ?? "0";
        $InformacoesAdicionais['nCodProj']= $omie->codProjeto;

        $pu = [];

        $pu['cAcaoProdUtilizados'] = 'EST';
        $pu['produtoUtilizado'] = $produtosUtilizados;
    
        $newOS = [];//array que engloba tudo
        $newOS['cabecalho'] = $cabecalho;
        $newOS['InformacoesAdicionais'] = $InformacoesAdicionais;
        $newOS['servicosPrestados'] = $serviceOrder;
        $newOS['produtosUtilizados'] = $pu;
 
        $top['param'][]= $newOS;
        // print_r($top);
        // exit;      
        $client = new Client([
            'handler' => new CurlHandler([
                 'handle_factory' => new CurlFactory(0)
            ])
        ]);

        $response = $client->post('https://app.omie.com.br/api/v1/servicos/os/',[
            "json" => $top
        ]);

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

        //consulta nota fiscal no omie
    public function consultaNotaServico(object $omie, int $idPedido)
    {
       
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'ListarNFSEs',
            'param'=>[
                    [
                        'nNumeroOS'=>$idPedido,
                    ]
                ]
            ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/servicos/nfse/',
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
        return $nfe['nfseEncontradasArray']['nNumeroNFSe'] ?? 0;
    }
    //busca cliente pelo ID
    public function getClientById($omie, $contact)
    {
        
        
        $jsonOmieIdCliente = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ConsultarCliente',
            'param' => [
                [
                    'codigo_cliente_omie'=>$contact->codigoClienteOmie
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

        return json_decode($response, true);
        
        
    }

    //busca cliente pelo ID
    public function getShipping($omie, $contact)
    {
        if($contact->cTransportadoraPadrao){
            $param = array(

                [
                    'codigo_cliente_omie'=>$contact->cTransportadoraPadrao
                ]
            );
               
        }else{
            $param = array(
                [
                    'codigo_cliente_integracao'=>"$contact->idTranspPadrao"
                ]
            );
        }

        
        $jsonOmieIdCliente = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ConsultarCliente',
            'param'=> $param
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

        $shipping = json_decode($response, true);
        
        return $shipping['codigo_cliente_omie'] ?? null;
        
    }
    //Cria cliente no Omie ERP
    public function criaClienteOmie(object $omie, object $contact)
    {
        // print_r($omie);
        // print 'estamos em omie services';
        // print_r($contact);
        // exit;
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'IncluirCliente',
            'param'=>[]
        ];
        
        $clienteJson = [];
        $clienteJson['codigo_cliente_integracao'] = $contact->id;
        $clienteJson['razao_social'] = htmlspecialchars_decode($contact->legalName) ?? null; 
        $clienteJson['nome_fantasia'] = htmlspecialchars_decode($contact->name) ?? null;
        $clienteJson['cnpj_cpf'] = $contact->cnpj ?? $contact->cpf;
        $clienteJson['email'] = $contact->email ?? null;
        $clienteJson['homepage'] = $contact->website ?? null;
        $clienteJson['telefone1_ddd'] = $contact->ddd1 ?? null;
        $clienteJson['telefone1_numero'] = $contact->phone1 ?? null;
        $clienteJson['telefone2_ddd'] = $contact->ddd2 ?? null;
        $clienteJson['telefone2_numero'] = $contact->phone2 ?? null;
        $clienteJson['contato'] = $contact->contato1 ?? null;
        $clienteJson['endereco'] = $contact->streetAddress;
        $clienteJson['endereco_numero'] = $contact->streetAddressNumber;
        $clienteJson['bairro'] = $contact->neighborhood;
        $clienteJson['complemento'] = $contact->streetAddressLine2 ?? null;
        $clienteJson['estado'] = $contact->stateShort;//usar null para teste precisa pegar o codigo da sigla do estado na api omie
        //$clienteJson['cidade'] = $contact->cityName;
        $clienteJson['cidade_ibge'] = $contact->cityId;
        // $clienteJson['cep'] = $contact->streetAdress ?? null;
        $clienteJson['cep'] = $contact->zipCode;
        $clienteJson['documento_exterior'] = $contact->documentoExterior ?? null;
        $clienteJson['inativo'] = $contact->inativo ?? null;
        $clienteJson['bloquear_exclusao'] = $contact->bloquearExclusao ?? null;
        //inicio aba CNAE e Outros
        $clienteJson['cnae'] = $contact->cnaeCode ?? null;
        $clienteJson['inscricao_estadual'] = $contact->inscricaoEstadual ?? null;
        $clienteJson['inscricao_municipal'] = $contact->inscricaoMunicipal ?? null;
        $clienteJson['inscricao_suframa'] = $contact->inscricaoSuframa ?? null;
        $clienteJson['optante_simples_nacional'] = $contact->simplesNacional ?? null;
        $clienteJson['produtor_rural'] = $contact->produtorRural ?? null;
        $clienteJson['contribuinte'] = $contact->contribuinte ?? null;
        $clienteJson['tipo_atividade'] = $contact->ramoAtividade ?? null;
       
        $clienteJson['valor_limite_credito'] = $contact->limiteCredito ?? null;
        $clienteJson['observacao'] = $contact->observacao ?? null;
        //fim aba CNAE e Outros
        //inicio array dados bancários
        $clienteJson['dadosBancarios'] =[];
        $dadosBancarios =[];
        $dadosBancarios['codigo_banco'] = $contact->cBanco ?? null;
        $dadosBancarios['agencia'] = $contact->agencia ?? null;
        $dadosBancarios['conta_corrente'] = $contact->nContaCorrente ?? null;
        $dadosBancarios['doc_titular'] = $contact->docTitular ?? null;
        $dadosBancarios['nome_titular'] = $contact->nomeTitular ?? null;
        $dadosBancarios['transf_padrao'] = $contact->transferenciaPadrao ?? null;
        $dadosBancarios['cChavePix'] = $contact->chavePix ?? null;
        $clienteJson['dadosBancarios'][]=$dadosBancarios;
        //fim array dados bancários
        //inicio array recoja mendações
        $clienteJson['recomendacoes'] =[];
        $recomendacoes=[];//vendedor padrão
        $recomendacoes['codigo_vendedor'] = $contact->cVendedorOmie ?? null;
        $recomendacoes['codigo_transportadora'] = $contact->idTransportadora ?? null;
        $clienteJson['recomendacoes'][] = $recomendacoes;
        //fim array recomendações

        // $caracteristicas = [];
        // //$caracteristicasCampo=[];
        // //$caracteristicasConteudo=[];
        // $caracteristicasCampo = 'Regiao';
        // $caracteristicasConteudo = $contact->regiao;
        // $caracteristicas['campo'] = $caracteristicasCampo;
        // $caracteristicas['conteudo']=$caracteristicasConteudo;
        //$clienteJson['caracteristicas'] = $caracteristicas;
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
    //altera um cliente no OMIE com base nas difereças dos arrays Old e News do Ploomes
    public function alteraCliente(object $omie, array $diff)
    {   
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'AlterarCliente',
            'param'=>[]
        ];
    
        $clienteJson = [];

        ($diff['idOmie'] === null)?$clienteJson['codigo_cliente_integracao'] = $diff['idIntegracao']:$clienteJson['codigo_cliente_omie'] = $diff['idOmie'];   
        
        $clienteJson['razao_social'] = htmlspecialchars_decode($diff['legalName']['new']) ?? null; 
        $clienteJson['nome_fantasia'] = htmlspecialchars_decode($diff['name']['new']) ?? null;
        $clienteJson['cnpj_cpf'] = $diff['cnpj']['new'] ?? $diff['cpf']['new'] ?? null;
        $clienteJson['email'] = $diff['email']['new'] ?? null;
        $clienteJson['homepage'] = $diff['website']['new'] ?? null;
        $clienteJson['telefone1_ddd'] = $diff['ddd1']['new'] ?? null;
        $clienteJson['telefone1_numero'] = $diff['phone1']['new'] ?? null;
        $clienteJson['telefone2_ddd'] = $diff['ddd2']['new'] ?? null;
        $clienteJson['telefone2_numero'] = $diff['phone2']['new'] ?? null;
        $clienteJson['contato'] = $diff['contato1']['new'] ?? null;
        $clienteJson['endereco'] = $diff['streetAddress']['new'] ?? null;
        $clienteJson['endereco_numero'] = $diff['streetAddressNumber']['new'] ?? null;
        $clienteJson['bairro'] = $diff['neighborhood']['new'] ?? null;
        $clienteJson['complemento'] = $diff['streetAddressLine2']['new'] ?? null;
        $clienteJson['estado'] = $diff['stateShort']['new'] ?? null;//usar null para teste precisa pegar o codigo da sigla do estado na api omie
        //$clienteJson['cidade'] = $diff['cityName']['new'];
        $clienteJson['cidade_ibge'] = $diff['cityId']['new'] ?? null;
        // $clienteJson['cep'] = $diff['streetAdress']['new'] ?? null;
        $clienteJson['cep'] = $diff['zipCode']['new'] ?? null;
        $clienteJson['documento_exterior'] = $diff['documentoExterior']['new'] ?? null;
        $clienteJson['inativo'] = $diff['inativo']['new'] ?? null;
        $clienteJson['bloquear_exclusao'] = $diff['bloquearExclusao']['new'] ?? null;
        //inicio aba CNAE e Outros
        $clienteJson['cnae'] = $diff['cnaeCode']['new'] ?? null;//3091102 ?? null;
        $clienteJson['inscricao_estadual'] = $diff['inscricaoEstadual']['new'] ?? null;
        $clienteJson['inscricao_municipal'] = $diff['inscricaoMunicipal']['new'] ?? null;
        $clienteJson['inscricao_suframa'] = $diff['inscricaoSuframa']['new'] ?? null;
        $clienteJson['optante_simples_nacional'] = $diff['simplesNacional']['new'] ?? null;
        $clienteJson['produtor_rural'] = $diff['produtorRural']['new'] ?? null;
        $clienteJson['contribuinte'] = $diff['contribuinte']['new'] ?? null;
        $clienteJson['tipo_atividade'] = $diff['ramoAtividade']['new'] ?? null;
        $clienteJson['valor_limite_credito'] = $diff['limiteCredito']['new'] ?? null;
        $clienteJson['observacao'] = $diff['observacao']['new'] ?? null;
        //fim aba CNAE e Outros
        //inicio array dados bancários
        $clienteJson['dadosBancarios'] =[];
        $dadosBancarios =[];
        $dadosBancarios['codigo_banco'] = $diff['cBanco']['new'] ?? null;
        $dadosBancarios['agencia'] = $diff['agencia']['new'] ?? null;
        $dadosBancarios['conta_corrente'] = $diff['nContaCorrente']['new'] ?? null;
        $dadosBancarios['doc_titular'] = $diff['docTitular']['new'] ?? null;
        $dadosBancarios['nome_titular'] = $diff['nomeTitular']['new'] ?? null;
        $dadosBancarios['transf_padrao'] = $diff['transferenciaPadrao']['new'] ?? null;
        $dadosBancarios['cChavePix'] = $diff['chavePix']['new'] ?? null;
        $clienteJson['dadosBancarios'][] =array_filter($dadosBancarios); 
        //fim array dados bancários
        //inicio array recoja mendações
        $clienteJson['recomendacoes'] = [];
        $recomendacoes = [];//vendedor padrão

        $recomendacoes['codigo_vendedor'] = $diff['cVendedorOmie'] ?? null;
        $recomendacoes['codigo_transportadora']= null;//6967396742;// $diff['ownerId']['new'] ?? null;
        $clienteJson['recomendacoes'][] = array_filter($recomendacoes);
        
        //fim array recomendações
        $tags =[];
        $tags[]['tag'] = $diff['tags.0']['new'] ?? null;
        $clienteJson['tags']=$tags;
   
         
        $array['param'][] = array_filter($clienteJson);

        
        $json = json_encode($array);
        print_r($json);
        print_r($array);
        print_r($diff);

        exit;

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
    //altera um cliente no OMIE com base nas difereças dos arrays Old e News do Ploomes
    public function alteraClienteCRMToERP(object $omie, object $contact)
    {   
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'AlterarCliente',
            'param'=>[]
        ];
    
        $clienteJson = [];

        // print_r($contact);
        // exit;

        if(empty($contact->idOmie) || $contact->idOmie === null)
        {
                $clienteJson['codigo_cliente_integracao'] = $contact->idIntegracao;
            }
        else{
            $clienteJson['codigo_cliente_omie'] = $contact->idOmie;        
        } 
       // $clienteJson['cVendedorOmie'] = $contact->cVendedorOmie;


        $clienteJson['razao_social'] = htmlspecialchars_decode($contact->legalName) ?? null; 
        $clienteJson['nome_fantasia'] = htmlspecialchars_decode($contact->name) ?? null;
        $clienteJson['cnpj_cpf'] = $contact->cnpj ?? $contact->cpf ?? null;
        $clienteJson['email'] = $contact->email ?? null;
        $clienteJson['homepage'] = $contact->website ?? null;
        $clienteJson['telefone1_ddd'] = $contact->ddd1 ?? null;
        $clienteJson['telefone1_numero'] = $contact->phone1 ?? null;
        $clienteJson['telefone2_ddd'] = $contact->ddd2 ?? null;
        $clienteJson['telefone2_numero'] = $contact->phone2 ?? null;
        $clienteJson['contato'] = $contact->contato1 ?? null;
        $clienteJson['endereco'] = $contact->streetAddress ?? null;
        $clienteJson['endereco_numero'] = $contact->streetAddressNumber ?? null;
        $clienteJson['bairro'] = $contact->neighborhood ?? null;
        $clienteJson['complemento'] = $contact->streetAddressLine2 ?? null;
        $clienteJson['estado'] = $contact->stateShort ?? null;//usar null para teste precisa pegar o codigo da sigla do estado na api omie
        //$clienteJson['cidade'] = $contact->cityName;
        $clienteJson['cidade_ibge'] = $contact->cityId ?? null;
        // $clienteJson['cep'] = $contact->streetAdress ?? null;
        $clienteJson['cep'] = $contact->zipCode ?? null;
        $clienteJson['documento_exterior'] = $contact->documentoExterior ?? null;
        $clienteJson['inativo'] = $contact->inativo ?? null;
        $clienteJson['bloquear_exclusao'] = $contact->bloquearExclusao ?? null;
        //inicio aba CNAE e Outros
        $clienteJson['cnae'] = $contact->cnaeCode ?? null;//3091102 ?? null;
        $clienteJson['inscricao_estadual'] = $contact->inscricaoEstadual ?? null;
        $clienteJson['inscricao_municipal'] = $contact->inscricaoMunicipal ?? null;
        $clienteJson['inscricao_suframa'] = $contact->inscricaoSuframa ?? null;
        $clienteJson['optante_simples_nacional'] = $contact->simplesNacional ?? null;
        $clienteJson['produtor_rural'] = $contact->produtorRural ?? null;
        $clienteJson['contribuinte'] = $contact->contribuinte ?? null;
        $clienteJson['tipo_atividade'] = $contact->ramoAtividade ?? null;
        $clienteJson['valor_limite_credito'] = $contact->limiteCredito ?? null;
        $clienteJson['observacao'] = $contact->observacao ?? null;
        //fim aba CNAE e Outros
        //inicio array dados bancários
        $clienteJson['dadosBancarios'] =[];
        $dadosBancarios =[];
        $dadosBancarios['codigo_banco'] = $contact->cBanco ?? null;
        $dadosBancarios['agencia'] = $contact->agencia ?? null;
        $dadosBancarios['conta_corrente'] = $contact->nContaCorrente ?? null;
        $dadosBancarios['doc_titular'] = $contact->docTitular ?? null;
        $dadosBancarios['nome_titular'] = $contact->nomeTitular ?? null;
        $dadosBancarios['transf_padrao'] = $contact->transferenciaPadrao ?? null;
        $dadosBancarios['cChavePix'] = $contact->chavePix ?? null;
        $clienteJson['dadosBancarios'][] =array_filter($dadosBancarios); 
        //fim array dados bancários
        //inicio array recoja mendações
        $clienteJson['recomendacoes'] = [];
        $recomendacoes = [];//vendedor padrão

        $recomendacoes['codigo_vendedor'] = $contact->cVendedorOmie ?? null;
        $recomendacoes['codigo_transportadora']=$contact->cTransportadora ?? null;//6967396742;// $contact->ownerId ?? null;
        $clienteJson['recomendacoes'][] = array_filter($recomendacoes);
        
        //fim array recomendações
        
        $clienteJson['tags']=$contact->tags ?? null;
   
         
        $array['param'][] = array_filter($clienteJson);

        
        $json = json_encode($array);
        // print_r($omie);
        // print_r($contact);
        // print_r($clienteJson);
        // print_r($json);
        //  exit;

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
    //Exclui um cliente no OMIE
    public function deleteClienteOmie(object $omie, object $contact)
    {  
        if(!isset($contact->idOmie)){

            $json = [
                'app_key' => $omie->appKey,
                'app_secret' => $omie->appSecret,
                'call' => 'ExcluirCliente',
                'param' => [
                    [
                        'codigo_cliente_integracao'=>$contact->id
                    ]
                ]
                    ];

        } else{

            $json = [
                'app_key' => $omie->appKey,
                'app_secret' => $omie->appSecret,
                'call' => 'ExcluirCliente',
                'param' => [
                    [
                        'codigo_cliente_omie'=>$contact->idOmie
                    ]
                ]
                    ];
        }

        $jsonDelete = json_encode($json);

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
            CURLOPT_POSTFIELDS => $jsonDelete,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $cliente = json_decode($response, true);
    
        return $cliente;

    }

    //consulta estoque
    public function getStockById(object $product, object $omie){
        $current = date('d/m/Y');
        $array = [
                    'app_key' => $omie->appKey,
                    'app_secret' => $omie->appSecret,
                    'call' => 'PosicaoEstoque',
                    'param' => [
                        [
                            'id_prod'=>$product->codigo_produto,
                            'data'=>$current,
                        ]
                    ]
                ];

            $json = json_encode($array);

            $curl = curl_init();
    
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://app.omie.com.br/api/v1/estoque/consulta/',
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
    
            $estoque = json_decode($response, true);
        
            return $estoque;

    }
    //seta o id de integração como Id Ploomes
    public function setProductIntegrationCodeAction($product)
    {
        $array = [
            'app_key' => $product->baseFaturamento['appKey'],
            'app_secret' => $product->baseFaturamento['appSecret'],
            'call' => 'AssociarCodIntProduto',
            'param' => [
                [
                    'codigo_produto' => $product->baseFaturamento['idOmie'],
                    'codigo_produto_integracao' => $product->idPloomes
                ]
            ]
        ];

        $json = json_encode($array);

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
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $infoIntegracao = json_decode($response, true);

        return $infoIntegracao;

        }
}