<?php
namespace src\functions;

use src\exceptions\WebhookReadErrorException;
use src\models\Contact;

use stdClass;


class ProductsFunctions{

    // encontra o processo a ser executado caso haja cadastro, exclusão ou alteração no webhook
    public static function findAction($webhook)
    {
        //decodifica o json de clientes vindos do webhook
        $json = $webhook['json'];
        $decoded = json_decode($json,true);
        $current = date('d/m/Y H:i:s');
        //identifica qual action do webhook
        if(isset($decoded['Action'])){

            $action = match($decoded['Action']){
                'Create' => 'createCRMToERP',
                'Update' => 'updateCRMToERP',
                'Delete' => 'deleteCRMToERP'
            };
        }elseif(isset($decoded['topic'])){
            $action = match($decoded['topic']){
                'Produto.Incluido' => 'createERPToCRM',
                'Produto.Alterado' => 'updateERPToCRM',
                'Produto.Excluido' => 'deleteERPToCRM'
            };
        }else{
            throw new WebhookReadErrorException('Não foi encontrda nenhuma ação no webhook '.$current, 500);
        }

        return $action;

    }

    //cria um objeto do webhook vindo do omie para enviar ao ploomes
    public static function createOmieObj($webhook, $omieServices)
    {
        //decodifica o json de produto vindos do webhook
        $json = $webhook['json'];
        $decoded = json_decode($json,true);
        //achata o array multidimensional decoded em um array simples
        $array = DiverseFunctions::achatarArray($decoded);
        //cria o objeto de produtos
        $product = new stdClass();

        switch($decoded['appKey']){
            case '4194053472609': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_DEMO'];
                $omie->appSecret = $_ENV['SECRETS_DEMO'];
                $product->baseFaturamentoTitle = 'Engeparts';
                // $cOmie = [
                //     'FieldKey'=>'contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3',
                //     'StringValue'=>$product->codigoClienteOmie,
                // ];
                break;
            case '2335095664902': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_MHL'];
                $omie->appSecret = $_ENV['SECRETS_MHL'];
                $product->baseFaturamentoTitle = 'Gamatermic';
                // $cOmie = [
                //     'FieldKey'=>'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
                //     'StringValue'=>$product->codigoClienteOmie,

                // ];
                break;
            case '2597402735928':
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_MSC'];
                $omie->appSecret = $_ENV['SECRETS_MSC']; 
                $product->baseFaturamentoTitle = 'Semin';
                // $cOmie = [
                //     'FieldKey'=>'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
                //     'StringValue'=>$product->codigoClienteOmie,
                // ];
                break;
            case 2337978328686: 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_HML'];
                $omie->appSecret = $_ENV['SECRETS_HML']; 
                $product->baseFaturamentoTitle = 'GSU';
                // $cOmie = [
                //     'FieldKey'=>'contact_07784D81-18E1-42DC-9937-AB37434176FB',
                //     'StringValue'=>$product->codigoClienteOmie,

                // ];
                break;
        }
        
        $product->grupo = 'Produtos';
        $product->idGrupo = '400345485';
        $product->messageId = $array['messageId'];
        $product->altura = $array['event_altura'];
        $product->bloqueado = $array['event_bloqueado'];
        $product->cnpj_fabricante = $array['event_cnpj_fabricante'];
        $product->codigo = $array['event_codigo'];
        $product->codigo_familia = $array['event_codigo_familia'];
        $product->codigo_produto = $array['event_codigo_produto'];
        $product->codigo_produto_integracao = $array['event_codigo_produto_integracao'];
        $product->combustivel_codigo_anp = $array['event_combustivel_codigo_anp'];
        $product->combustivel_descr_anp = $array['event_combustivel_descr_anp'];
        $product->cupom_fiscal = $array['event_cupom_fiscal'];
        $product->descr_detalhada = $array['event_descr_detalhada'];
        $product->descricao = $array['event_descricao'];
        $product->dias_crossdocking = $array['event_dias_crossdocking'];
        $product->dias_garantia = $array['event_dias_garantia'];
        $product->ean = $array['event_ean'];
        $product->estoque_minimo = $array['event_estoque_minimo'];
        $product->id_cest = $array['event_id_cest'];
        $product->id_preco_tabelado = $array['event_id_preco_tabelado'];
        $product->inativo = $array['event_inativo'];
        $product->indicador_escala = $array['event_indicador_escala'];
        $product->largura = $array['event_largura'];
        $product->marca = $array['event_marca'];
        $product->market_place = $array['event_market_place'];
        $product->modelo = $array['event_modelo'];
        $product->ncm = $array['event_ncm'];
        $product->obs_internas = $array['event_obs_internas'];
        $product->origem_mercadoria = $array['event_origem_mercadoria'];
        $product->peso_bruto = $array['event_peso_bruto'];
        $product->peso_liq = $array['event_peso_liq'];
        $product->profundidade = $array['event_profundidade'];
        $product->quantidade_estoque = $array['event_quantidade_estoque'];
        $product->tipoItem = $array['event_tipoItem'];
        $product->unidade = $array['event_unidade'];
        $product->valor_unitario = $array['event_valor_unitario'];
        $product->author_email = $array['author_email'];
        $product->author_name = $array['author_name'];
        $product->author_userId = $array['author_userId'];
        $product->appKey = $array['appKey'];
        $product->appHash = $array['appHash'];
        $product->origin = $array['origin'];      
        //estoque

        $product->stock = self::getStock($product,$omie,$omieServices);      

        
        return $product;
    }

    public static function getStock(object $product, object $omie, object $omieServices){

        $stock = $omieServices->getStockById($product,$omie);
        $local = ($stock['codigo_local_estoque'] === 6879399409)? 'Padrão' : $stock['codigo_local_estoque'];
        //$html = file_get_contents('http://localhost/gamatermic/src/views/pages/gerenciador.pages.stockTable.php');
        $html = file_get_contents('https://gamatermic.bicorp.online/src/views/pages/gerenciador.pages.stockTable.php');
        $html = str_replace('{local}', $local, $html);
        $html = str_replace('{saldo}', $stock['saldo'], $html);
        $html = str_replace('{minimo}', $stock['estoque_minimo'], $html);
        $html = str_replace('{pendente}', $stock['pendente'], $html);
        $html = str_replace('{reservado}', $stock['reservado'], $html);
        $html = str_replace('{fisico}', $stock['fisico'], $html);
        $html = str_replace('{data}', date('d/m/Y'), $html);

        return $html;

    }


    // cria o objet e a requisição a ser enviada ao ploomes com o objeto do omie
    public static function createPloomesProductFromOmieObject($product, $ploomesServices, $omieServices)
    {

        switch($product->appKey){
            case '4194053472609': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_DEMO'];
                $omie->appSecret = $_ENV['SECRETS_DEMO'];
                $product->baseFaturamentoTitle = 'Engeparts';
                $cOmie = [
                    'FieldKey'=>'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
                    'StringValue'=>$product->codigo_produto,
                ];
                //tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_4B2C943C-9EC4-4553-8B45-10C0FD2B0810',
                        'BigStringValue'=>$product->stock,
                ];

                break;
            case '2335095664902': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_MHL'];
                $omie->appSecret = $_ENV['SECRETS_MHL'];
                $product->baseFaturamentoTitle = 'Gamatermic';
                $cOmie = [
                    'FieldKey'=>'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
                    'IntegerValue'=>$product->codigo_produto,

                ];
                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3',
                        'BigStringValue'=>$product->stock,
                ];
                break;
            case '2597402735928':
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_MSC'];
                $omie->appSecret = $_ENV['SECRETS_MSC']; 
                $product->baseFaturamentoTitle = 'Semin';
                $cOmie = [
                    'FieldKey'=>'product_429C894A-708E-4125-A434-2A70EDCAFED6',
                    'IntegerValue'=>$product->codigo_produto,
                ];
                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_08A41D8E-F593-4B74-8CF8-20A924209A09',
                        'BigStringValue'=>$product->stock,
                ];
                break;
            case 2337978328686: 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_HML'];
                $omie->appSecret = $_ENV['SECRETS_HML']; 
                $product->baseFaturamentoTitle = 'GSU';
                $cOmie = [
                    'FieldKey'=>'product_816E5031-2843-4E71-8721-E97185A98E77',
                    'IntegerValue'=>123456,

                ];
                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_03A29673-70E4-4887-9FC9-65D36791F2D7',
                        'BigStringValue'=>$product->stock,
                ];
                break;
        }
        //cria o produto formato ploomes 
        $data = [];

        $data['Name'] = $product->descricao;
        $data['GroupId'] = $product->idGrupo;
        // $data['FamilyId'] = $product->codigo_familia;
        $data['Code'] = $product->codigo;
        // $data['Code'] = $product->codigo_produto;
        $data['MeasurementUnit'] = $product->unidade;
        //$data['ImageUrl'] = $product->endereco ?? null;
        //$data['CurrencyId'] = $product->enderecoNumero ?? null;
        $data['UnitPrice'] = $product->valor_unitario ?? null;
        // $data['CreateImportId'] = $city['Id'];//pegar na api do ploomes
        // $data['UpdateImportId'] = $product->segmento ?? null;//Id do Tipo de atividade(não veio no webhook de cadastro do omie)
        // $data['Editable'] = $product->nFuncionarios ?? null;//Id do número de funcionários(não veio no webhook de cadastro do omie)
        // $data['Deletable'] = $product->cVendedorPloomes ?? null;//Id do vendedor padrão(comparar api ploomes)
        // $data['Suspended'] = $product->observacao ?? null;
        // $data['CreatorId'] = $product->email ?? null;
        // $data['UpdaterId'] = $product->homepage ?? null;
        // $data['CreateDate'] = $product->cnae ?? null;
        // $data['LastUpdateDate'] = $product->codigoClienteOmie ?? null;//chave externa do cliente(código Omie)
        //$data['ImportationIdCreate'] = $product->latitude ?? null;(inexistente no omie)
        //$data['ImportationIdUpdate'] = $product->longitude ?? null;(inexistente no omie)
        
        $op = [];
        $ncm = [
            'FieldKey'=> 'product_15405B03-AA47-4921-BC83-E358501C3227',
            'StringValue'=>$product->ncm ?? null,
        ];
        $marca = [
            'FieldKey'=>'product_4C2CCB79-448F-49CF-B27A-822DA762BE5E',
            'StringValue'=>$product->marca ?? null,
        ];

        $modelo = [
            'FieldKey'=>'product_A92259E5-1E19-44AC-B781-CB908F5602EC',
            'StringValue'=>$product->modelo ?? null,
        ];
        $descDetalhada = [
            'FieldKey'=>'product_F48280B4-688C-4346-833C-03E28991564C',
            'BigStringValue'=>$product->descr_detalhada ?? null,
        ];
        $obsInternas = [
            'FieldKey'=>'product_5FB6D80C-CB90-4A46-95BD-1A18141FBC46',
            'BigStringValue'=>$product->obs_internas ?? null,
        ];
        $categoria = [
            'FieldKey'=>'product_44CCBB11-CD81-439A-8304-921C2E39C25D',
            'StringValue'=>$product->codigo_familia ?? null,
        ];

        $op[] = $ncm;
        $op[] = $marca;
        $op[] = $modelo;
        $op[] = $descDetalhada;
        $op[] = $obsInternas;
        $op[] = $categoria;
        $op[] = $cOmie;
        $op[] = $stockTable;
   
        $data['OtherProperties'] = $op;
        $json = json_encode($data);

        return $json;

    }

}