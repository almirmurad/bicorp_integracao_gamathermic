<?php

namespace src\services;

use src\exceptions\WebhookReadErrorException;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use stdClass;

class ContactServices
{
    public static function createContact($contact)
    {
        $omieServices = new OmieServices();
        $ploomesServices = new PloomesServices();
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
   
        $current = date('d/m/Y H:i:s');
   
        foreach($contact->basesFaturamento as $k => $bf)
        {

            $omie[$k] = new stdClass();
            
            if($bf['integrar'] > 0){
                $omie[$k]->baseFaturamentoTitle = $bf['title'];
                $omie[$k]->target = $bf['sigla']; 
                $omie[$k]->appSecret = $bf['appSecret'];
                $omie[$k]->appKey = $bf['appKey'];
                $contact->cVendedorOmie = $omieServices->vendedorIdOmie($omie[$k],$contact->ownerEmail); 
                $criaClienteOmie = $omieServices->criaClienteOmie($omie[$k], $contact);

                //verifica se criou o cliente no omie
                if (isset($criaClienteOmie['codigo_status']) && $criaClienteOmie['codigo_status'] == "0") {
                    //monta a mensagem para atualizar o cliente do ploomes
                    $msg=[
                        'ContactId' => $contact->id,
                        'Content' => 'Cliente '.$contact->name.' criada no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
                        'Title' => 'Pedido Criado'
                    ];
                    
                    //cria uma interação no card
                    ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;

                    //inclui o id do pedido no omie na tabela deal
                    // if($criaClienteOmie['codigo_cliente_omie']){
                    //     //salva um deal no banco
                    //     $deal->omieOrderId = $incluiPedidoOmie['codigo_pedido'];
                    //     $dealCreatedId = $this->databaseServices->saveDeal($deal);   
                    //     $message['winDeal']['dealMessage'] ='Id do Deal no Banco de Dados: '.$dealCreatedId;  
                    //     if($dealCreatedId){

                    //         $omie[$k]->idOmie = $deal->omieOrderId;
                    //         $omie[$k]->codCliente = $idClienteOmie;
                    //         $omie[$k]->codPedidoIntegracao = $deal->lastOrderId;
                    //         $omie[$k]->numPedidoOmie = intval($incluiPedidoOmie['numero_pedido']);
                    //         $omie[$k]->codClienteIntegracao = $deal->contactId;
                    //         $omie[$k]->dataPrevisao = $deal->finishDate;
                    //         $omie[$k]->codVendedorOmie = $codVendedorOmie;
                    //         $omie[$k]->idVendedorPloomes = $deal->ownerId;   
                    //         $omie[$k]->appKey = $omie[$k]->appKey;             
                    
                    //         $id = $this->databaseServices->saveOrder($omie[$k]);
                    //         $message['winDeal']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos '.$omie[$k]->baseFaturamentoTitle.' id '.$id.'em: '.$current;
                    //     }
                        
                    // }

                    $messages['success'][]=$message;
                    
                }else{
                    //monta a mensagem para atualizar o card do ploomes
                    $msg=[
                        'ContactId' => $contact->id,
                        'Content' => 'Erro ao gravar cliente no Omie: '. $criaClienteOmie['faultstring'].' na base '.$omie[$k]->baseFaturamentoTitle.' Data = '.$current,
                        'Title' => 'Erro ao Gravar cliente'
                    ];
                    
                    //cria uma interação no card
                    ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao gravar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $criaClienteOmie['faultstring'].' Data = '.$current: $message = 'Erro ao gravar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $criaClienteOmie['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$current;

                    $messages['error'][]=$message;
                }       
            }
        }   
        return $messages;
    }

    public static function updateContact($diff, $contact)
    {

        $omieServices = new OmieServices();
        $ploomesServices = new PloomesServices();
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $total = 0;
   
        $current = date('d/m/Y H:i:s');

        foreach($contact->basesFaturamento as $k => $bf)
        {
            $omie[$k] = new stdClass();
            
            if($bf['integrar'] > 0){
                $total ++;
                $omie[$k]->baseFaturamentoTitle = $bf['title'];
                $omie[$k]->target = $bf['sigla']; 
                $omie[$k]->appSecret = $bf['appSecret'];
                $omie[$k]->appKey = $bf['appKey'];
                $diff['idIntegracao'] = $contact->id;
                $diff['cVendedorOmie'] = (isset($diff['ownerEmail']['new']) && $diff['ownerEmail']['new'] !== null) ? $omieServices->vendedorIdOmie($omie[$k],$diff['ownerEmail']['new']) : null;
                $alterar = $omieServices->alteraCliente($omie[$k], $diff);

                //verifica se criou o cliente no omie
                if (isset($alterar['codigo_status']) && $alterar['codigo_status'] == "0") {
                    //monta a mensagem para atualizar o cliente do ploomes
                    $msg=[
                        'ContactId' => $contact->id,
                        'Content' => 'Cliente '.$contact->name.' alterado no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
                        'Title' => 'Pedido Criado'
                    ];
                    
                    //cria uma interação no card
                    ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' alterado no Omie ERP ('.$omie[$k]->baseFaturamentoTitle.') com o numero: '.$alterar['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' alterado no Omie ERP com o numero: '.$alterar['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;

                    //aqui atualizaria a base de dados com sql de update
                 
                    $messages['success'][] = $message;
                    
                }else{
                    //monta a mensagem para atualizar o card do ploomes
                    $msg=[
                        'ContactId' => $contact->id,
                        'Content' => 'Erro ao alterar cliente no Omie: '. $alterar['faultstring'].' na base '.$omie[$k]->baseFaturamentoTitle.' Data = '.$current,
                        'Title' => 'Erro ao alterar cliente'
                    ];
                    
                    //cria uma interação no card
                    ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao alterar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $alterar['faultstring'].' Data = '.$current: $message = 'Erro ao alterar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $alterar['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$current;
                    $messages['error'][]=$message;
                }       
            }
        }   
   
        return $messages;       
    }

    public static function deleteContact($contact)
    {
        $omieServices = new OmieServices();
        $ploomesServices = new PloomesServices();
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $total = 0;
   
        $current = date('d/m/Y H:i:s');

        foreach($contact->basesFaturamento as $k => $bf)
        {
            $omie[$k] = new stdClass();
            
            if($bf['integrar'] > 0){
                $total ++;
                $omie[$k]->baseFaturamentoTitle = $bf['title'];
                $omie[$k]->target = $bf['sigla']; 
                $omie[$k]->appSecret = $bf['appSecret'];
                $omie[$k]->appKey = $bf['appKey'];
                
                $excluir = $omieServices->deleteClienteOmie($omie[$k], $contact);

                //verifica se excluiu o cliente no omie
                if (isset($excluir['codigo_status']) && $excluir['codigo_status'] == "0") {
                    //monta a mensagem para atualizar o cliente do ploomes
                    $msg=[
                        'ContactId' => 40058720,
                        'Content' => 'Cliente '.$contact->name.' excluido no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
                        'Title' => 'Usuário excluído no OMIE ERP'
                    ];
                    
                    //cria uma interação no card
                    ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes: '.$contact->id.' - '.$contact->name.' excluido no Omie ERP ('.$omie[$k]->baseFaturamentoTitle.') e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' excluido no Omie ERP com o numero: '.$excluir['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;

                    //aqui atualizaria a base de dados com sql de update
                 
                    $messages['success'][] = $message;
                    
                }else{
                    //monta a mensagem para atualizar o card do ploomes
                    $msg=[
                        'ContactId' => $contact->id,
                        'Content' => 'Erro ao excluir cliente no Omie: '. $excluir['faultstring'].' na base '.$omie[$k]->baseFaturamentoTitle.' Data = '.$current,
                        'Title' => 'Erro ao excluir cliente'
                    ];
                    
                    //cria uma interação no card
                    ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao excluir cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $excluir['faultstring'].' Data = '.$current: $message = 'Erro ao excluir cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $excluir['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$current;
                    $messages['error'][]=$message;
                }       
            }
        }   
   
        return $messages;       

    }

    public static function createContactERP($contact){

        $omieServices = new OmieServices();
        $ploomesServices = new PloomesServices();
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $total = 0;
   
        $current = date('d/m/Y H:i:s');
        print_r($contact);




        switch($contact->appKey){
            case '4194053472609': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_DEMO'];
                $omie->appSecret = $_ENV['SECRETS_DEMO'];
                $cOmie = [
                    'FieldKey'=>'contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3',
                    'StringValue'=>$contact->codigoClienteOmie,
                ];
                break;
            case '2335095664902': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_MHL'];
                $omie->appSecret = $_ENV['SECRETS_MHL'];
                $cOmie = [
                    'FieldKey'=>'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
                    'StringValue'=>$contact->codigoClienteOmie,

                ];
                break;
            case '2597402735928':
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_MSC'];
                $omie->appSecret = $_ENV['SECRETS_MSC']; 
                $cOmie = [
                    'FieldKey'=>'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
                    'StringValue'=>$contact->codigoClienteOmie,
                ];
                break;
            // case 2337978328686: 
            //     $cOmie = [
            //         'FieldKey'=>'contact_07784D81-18E1-42DC-9937-AB37434176FB',
            //         'StringValue'=>$contact->codigoClienteOmie,

            //     ];
            //     break;
        }
         

        $data = [];
        $data['TypeId'] = 1;
        $data['Name'] = $contact->nomeFantasia;
        $data['LegalName'] = $contact->razaoSocial;
        $data['Register'] = $contact->cnpjCpf;
        $data['StatusId'] = 40059036;
        $data['Neighborhood'] = $contact->bairro ?? null;
        $data['ZipCode'] = $contact->cep ?? null;
        $data['StreetAddress'] = $contact->endereco ?? null;
        $data['StreetAddressNumber'] = $contact->enderecoNumero ?? null;
        $data['StreetAddressLine2'] = $contact->complemento ?? null;
        $city = $ploomesServices->getCitiesByIBGECode($contact->cidadeIbge);
        $data['CityId'] = $city['Id'];//pegar na api do ploomes
        $data['LineOfBusiness'] = $contact->segmento ?? null;//Id do Tipo de atividade(não veio no webhook de cadastro do omie)
        $data['NumbersOfEmployeesId'] = $contact->nFuncionarios ?? null;//Id do número de funcionários(não veio no webhook de cadastro do omie)
        $mailVendedor = $omieServices->getMailVendedorById($omie,$contact);
        $contact->mailVendedor = $mailVendedor; 
        $idVendedorPloomes = $ploomesServices->ownerId($contact);
        $contact->cVendedorPloomes = $idVendedorPloomes;
        $data['OwnerId'] = $contact->cVendedorPloomes ?? null;//Id do vendedor padrão(comparar api ploomes)
  
        $data['Note'] = $contact->observacao ?? null;
        $data['Email'] = $contact->email ?? null;
        $data['Website'] = $contact->homepage ?? null;
        //$data['RoleId'] = $contact->cargo ?? null;//Id do cargo do cliente(inexistente no omie)
        //$data['DepartmentId'] = $contact->departamento ?? null;//Id do departamento do cliente(inexistente no omie)
        //$data['Skype'] = $contact->skype ?? null;//Skype do cliente(inexistente no omie)
        //$data['Facebook'] = $contact->facebook ?? null;//Facebook do cliente(inexistente no omie)
        //$data['ForeignZipCode'] = $contact->cepInternacional ?? null;//(inexistente no omie)
        //$data['CurrencyId'] = $contact->moeda ?? null;//(inexistente no omie)
        //$data['EmailMarketing'] = $contact->marketing ?? null;//(inexistente no omie)
        $data['CNAECode'] = $contact->cnae ?? null;
        //$data['Latitude'] = $contact->latitude ?? null;(inexistente no omie)
        //$data['Longitude'] = $contact->longitude ?? null;(inexistente no omie)
        $data['Key'] = $contact->codigoClienteOmie ?? null;//chave externa do cliente(código Omie)
        //$data['AvatarUrl'] = $contact->avatar ?? null;(inexistente no omie)
        //$data['IdentityDocument'] = $contact->exterior ?? null;//(documento internacional exterior)
        //$data['CNAEName'] = $contact->cnaeName ?? null;(inexistente no omie)
        $data['Phones'] = [];
        $phone1 = [
            'PhoneNumber'=>"($contact->telefoneDdd1) $contact->telefoneNumero1",
            'TypeId'=>1,
            'CountryId'=>76,
        ];

        $phone2 = [
            'PhoneNumber'=>"($contact->telefoneDdd2) $contact->telefoneNumero2",
            'TypeId' => 2,
            'CountryId' => 76,
        ];
        $data['Phones'][] = $phone1;
        $data['Phones'][] = $phone2;
        $op = [];
        $ramo = [
            'FieldKey'=> 'contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227',
            'IntegerValue'=>409150923,
        ];
        $tipo = [
            'FieldKey'=>'contact_879A3AA2-57B1-49DC-AEC2-21FE89617665',
            'IntegerValue'=>409150910,
        ];
        // $porte = [
        //     'FieldKey'=>'contact_FA99392B-CED8-4668-B003-DFC1111DACB0',
        //     'IntegerValue'=>'',//pequeno, medio, grande
        // ];
        $importancia = [
            'FieldKey'=>'contact_20B72360-82CF-4806-BB05-21E89D5C61FD',
            'IntegerValue'=>409150919,//alta
        ];
        $situacao = [
            'FieldKey'=>'contact_5F52472B-E311-4574-96E2-3181EADFAFBE',
            'IntegerValue'=>409150897,
        ];
        // $cicloCompra = [
        //     'FieldKey'=>'contact_9E595E72-E50C-4E95-9A05-D3B024C177AD',
        //     'StringValue'=>'',
        // ];
        $inscEstadual = [
            'FieldKey'=>'contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB',
            'StringValue'=>$contact->inscricaoEstadual,
        ];
        $inscMunicipal = [
            'FieldKey'=>'contact_D21FAEED-75B2-40E4-B169-503131EB3609',
            'StringValue'=>$contact->inscricaoMunicipal,
        ];
        $inscSuframa = [
            'FieldKey'=>'contact_3094AFFE-4263-43B6-A14B-8B0708CA1160',
            'StringValue'=>$contact->inscricaoSuframa,
        ];
        $simplesNacional = [
            'FieldKey'=>'contact_9BB527FD-8277-4D1F-AF99-DD88D5064719',
            'BoolValue'=>(isset($contact->simplesNacional) && $contact->simplesNacional === 'S') ? $contact->simplesNacional = true : $contact->simplesNacional = false,
        ];
        $contato1 = [
            'FieldKey'=>'contact_3C521209-46BD-4EA5-9F41-34756621CCB4',
            'StringValue'=>$contact->contato,
        ];
        $prodRural = [
            'FieldKey'=>'contact_F9B60153-6BDF-4040-9C3A-E23B1469894A',
            'BoolValue'=>(isset($contact->produtorRural) && $contact->produtorRural === 'S') ? $contact->produtorRural = true : $contact->produtorRural = false,
        ];
        $contribuinte = [
            'FieldKey'=>'contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453',
            'BoolValue'=>(isset($contact->contribuinte) && $contact->contribuinte === 'S') ? $contact->contribuinte = true : $contact->contribuinte = false,
        ];
        $limiteCredito = [
            'FieldKey'=>'contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD',
            'DecimalValue'=>$contact->limiteCredito,
        ];
        $inativo = [
            'FieldKey'=>'contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4',
            'BoolValue'=>(isset($contact->inativo) && $contact->inativo === 'S') ? $contact->inativo = true : $contact->inativo = false,
        ];
        $bloqExclusao = [
            'FieldKey'=>'contact_C613A391-155B-42F5-9C92-20C3371CC3DE',
            'BoolValue'=>(isset($contact->bloquearExclusao) && $contact->bloquearExclusao === 'S') ? $contact->bloquearExclusao = true : $contact->bloquearExclusao = false,
        ];
        $transpPadrao = [
            'FieldKey'=>'contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33',
            //'IntegerValue'=>'',
            'StringValue'=>$contact->codigoVendedor
        ];
        $cBanco = [
            'FieldKey'=>'contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9',
            'StringValue'=>$contact->cBanco,
        ];
        $agencia = [
            'FieldKey'=>'contact_1F1E1F00-34CB-4356-B852-496D62A90E10',
            'StringValue'=>$contact->agencia,
        ];
        $nContaCorrente = [
            'FieldKey'=>'contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80',
            'StringValue'=>$contact->nContaCorrente,
        ];
        $docTitular = [
            'FieldKey'=>'contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50',
            'StringValue'=>$contact->docTitular,
        ];
        $nomeTitular = [
            'FieldKey'=>'contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066',
            'StringValue'=>$contact->nomeTitular,
        ];
        $cli = $omieServices->getClientById($omie,$contact);
        $chavePix = [
            'FieldKey'=>'contact_847FE760-74D0-462D-B464-9E89C7E1C28E',
            'StringValue'=>$cli['dadosBancarios']['cChavePix'],
        ];
        $transferenciaPadrao = [
            'FieldKey'=>'contact_33015EDD-B3A7-464E-81D0-5F38D31F604A',
            'BoolValue'=>(isset($contact->transferenciaPadrao) && $contact->transferenciaPadrao === 'S') ? true : false,
        ];
        $integrarBase1 = [
            'FieldKey'=>'contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C',
            'BoolValue'=>(isset($contact->appKey) && $contact->appKey === '4194053472609') ?  true :  false,
        ];
        $integrarBase2 = [
            'FieldKey'=>'contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C',
            'BoolValue'=>(isset($contact->appKey) && $contact->appKey === '2335095664902') ?  true :  false,
        ];
        $integrarBase3 = [
            'FieldKey'=>'contact_02AA406F-F955-4AE0-B380-B14301D1188B',
            'BoolValue'=>(isset($contact->appKey) && $contact->appKey === '2597402735928') ?  true :  false,
        ];
        // $integrarBase1 = [
        //     'FieldKey'=>'contact_879A3AA2-57B1-49DC-AEC2-21FE89617665',
        //     'BoolValue'=>(isset($contact->appKey) && $contact->appKey === '2337978328686') ?  true :  false,
        // ];

        
        
        $op[] = $ramo;
        $op[] = $tipo;
        $op[] = $importancia;
        $op[] = $situacao;
        $op[] = $inscEstadual;
        $op[] = $inscMunicipal;
        $op[] = $inscSuframa;
        $op[] = $simplesNacional;
        $op[] = $contato1;
        $op[] = $prodRural;
        $op[] = $contribuinte;
        $op[] = $limiteCredito;
        $op[] = $inativo;
        $op[] = $bloqExclusao;
        $op[] = $transpPadrao;
        $op[] = $cBanco;
        $op[] = $agencia;
        $op[] = $nContaCorrente;
        $op[] = $docTitular;
        $op[] = $nomeTitular;
        $op[] = $cOmie;
        $op[] = $chavePix;
        $op[] = $transferenciaPadrao;
        $op[] = $integrarBase1;
        $op[] = $integrarBase2;
        $op[] = $integrarBase3;
        //$op[] = $integrarBase4;
   
        $data['OtherProperties'] = $op;

        print_r($data);

        $json = json_encode($data,JSON_UNESCAPED_UNICODE);
        
        $createPloomesContact = $ploomesServices->createPloomesContact($json);

        print_r($json);
        print_r($createPloomesContact);

        exit;





        // {
        //     "Name": "Pessoa Nova",
        //     "Neighborhood": "Pinheiros",
        //     "ZipCode": 0,
        //     "Register": "69.971.558/0001-10",
        //     "OriginId": 0,
        //     "CompanyId": null,
        //     "StreetAddressNumber": "XXX",
        //     "TypeId": 0,
        //     "Phones": [
        //         {
        //             "PhoneNumber": "(XX) XXXX-XXXX",
        //             "TypeId": 0,
        //             "CountryId": 0
        //         }
        //     ],
        //     "OtherProperties": [
        //         {
        //             "FieldKey": "{fieldKey}",
        //             "StringValue": "texto exemplo"
        //         },
        //         {
        //             "FieldKey": "{fieldKey}",
        //             "IntegerValue": 2
        //         }
        //     ]
        // }

    }
}