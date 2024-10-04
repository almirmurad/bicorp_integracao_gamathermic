<?php

namespace src\services;

use src\exceptions\WebhookReadErrorException;
use src\functions\ClientsFunctions;
use src\functions\DiverseFunctions;
use src\models\Contact;
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
                $contact->cVendedorOmie = $omieServices->vendedorIdOmie($omie[$k],$contact->ownerEmail) ?? null; 
                
                $criaClienteOmie = $omieServices->criaClienteOmie($omie[$k], $contact);

                //verifica se criou o cliente no omie
                if (isset($criaClienteOmie['codigo_status']) && $criaClienteOmie['codigo_status'] == "0") {
                    $match = match ($k) {
                         0=> 'contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3',
                         1=> 'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
                         2=> 'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
                         3=> 'contact_07784D81-18E1-42DC-9937-AB37434176FB',
                    };
                    $codigoOmie = $criaClienteOmie['codigo_cliente_omie'];
                    $array = [
                        'TypeId'=>1,
                        'OtherProperties'=>[
                            [
                                'FieldKey'=>$match,
                                'StringValue'=>"$codigoOmie",
                            ]
                        ]
                    ];
                    $json = json_encode($array);
                    //insere o id do omie no campo correspondente do cliente Ploomes
                    $insertIdOmie = $ploomesServices->updatePloomesContact($json, $contact->id);
                    if($insertIdOmie){
                        //monta a mensagem para atualizar o cliente do ploomes
                        $msg=[
                            'ContactId' => $contact->id,
                            'Content' => 'Cliente '.$contact->name.' criada no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
                            'Title' => 'Pedido Criado'
                        ];
                        
                        //cria uma interação no card
                        ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;

                    }

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
        if(!empty($diff)){
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
                        $diff['idOmie'] = $contact->codOmie[$k];
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
        }else{
            $messages['error'][]='Esta alteração já foi feita';
        }
            return $messages;       
    }

    public static function updateContactCRMToERP($contact)
    {
        $omieServices = new OmieServices();
        $ploomesServices = new PloomesServices();
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $total = 0;
        $current = date('d/m/Y H:i:s');
        if(!empty($contact)){
            // print_r($contact);
            // exit;
            foreach($contact->basesFaturamento as $k => $bf)
            {
                $omie[$k] = new stdClass();
                    if($bf['integrar'] > 0){
                        $total ++;
                        $omie[$k]->baseFaturamentoTitle = $bf['title'];
                        $omie[$k]->target = $bf['sigla']; 
                        $omie[$k]->appSecret = $bf['appSecret'];
                        $omie[$k]->appKey = $bf['appKey'];
                       
                        $contact->idIntegracao = $contact->id;
                        $contact->idOmie = $contact->codOmie[$k];
                        $contact->cVendedorOmie = (isset($contact->ownerEmail) && $contact->ownerEmail !== null) ? $omieServices->vendedorIdOmie($omie[$k],$contact->ownerEmail) : null;
                        // $alterar = $omieServices->alteraCliente($omie[$k], $diff);
                        $alterar = $omieServices->alteraClienteCRMToERP($omie[$k], $contact);

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
        }else{
            $messages['error'][]='Esta alteração já foi feita';
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
                $contact->idOmie = $contact->codOmie[$k];
                $excluir = $omieServices->deleteClienteOmie($omie[$k], $contact);

                //verifica se excluiu o cliente no omie
                if (isset($excluir['codigo_status']) && $excluir['codigo_status'] == "0") {
                    //monta a mensagem para atualizar o cliente do ploomes
                    //comentado pq não tem como criar uma interação no usuário se ele for excluído
                    // $msg=[
                    //     'ContactId' => 40058720,
                    //     'Content' => 'Cliente '.$contact->name.' excluido no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
                    //     'Title' => 'Usuário excluído no OMIE ERP'
                    // ];
                    
                    // //cria uma interação no card
                    // ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes: '.$contact->id.' - '.$contact->name.' excluido no Omie ERP ('.$omie[$k]->baseFaturamentoTitle.') e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' excluido no Omie ERP com o numero: '.$excluir['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;
                    $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' excluido no Omie ERP com o numero: '.$excluir['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes pois ele acaba de ser excluído. Data '.$current;
                 
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

    public static function createContactERP($contact)
    {
        $omieServices = new OmieServices();
        $ploomesServices = new PloomesServices();
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
   
        $current = date('d/m/Y H:i:s');
        $json = ClientsFunctions::createPloomesContactFromOmieObject($contact, $ploomesServices, $omieServices);

        $cnpj = DiverseFunctions::limpa_cpf_cnpj($contact->cnpjCpf);
        $pContact = $ploomesServices->consultaClientePloomesCnpj($cnpj);
        if($pContact !== null){
            $messages['error'] = 'Erro ao cadastrar o cliente '.$contact->nomeFantasia .'('.$contact->cnpjCpf.') Cliente já cadastrado no Ploomes com o código: '.$pContact.' Data:' .$current;

        }else{
  
            if(!$ploomesServices->createPloomesContact($json)){
            }else{
                $messages['success'] = 'Cliente '.$contact->nomeFantasia.' Cadastrado no Ploomes CRM com sucesso! Data: '.$current;
            }
            
        }

        return $messages;


    }

    public static function updateContactERP($json, $contact, $ploomesServices)
    {
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $current = date('d/m/Y H:i:s');
        $idContact = $ploomesServices->consultaClientePloomesCnpj(DiverseFunctions::limpa_cpf_cnpj($contact->cnpjCpf));

        if(!$idContact){
            $messages['error'] = 'Erro: cliente '.$contact->nomeFantasia.' não foi encontrado no Ploomes CRM';
        }else{
            // print_r($json);
            // print $idContact;
            // exit;
            $ploomesServices->updatePloomesContact($json, $idContact);
            // $messages['success'] = 'Cliente '.$contact->nomeFantasia.' alterado no Ploomes CRM com sucesso!';
            //monta a mensagem para atualizar o cliente do ploomes
            $msg=[
                'ContactId' => $idContact,
                'Content' => 'Cliente '.$contact->nomeFantasia.' alterado no Omie ERP na base: '.$contact->baseFaturamentoTitle.' via Bicorp Integração',
                'Title' => 'Cliente Alterado'
            ];
            
            //cria uma interação no card
            ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$idContact.' alterado no Ploomes CRM ('.$contact->baseFaturamentoTitle.') e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$idContact.' alterado no PLoomes CRM, porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;
            $messages['success'] = $message;
        }

        return $messages;
    }

    public static function deleteContactERP($contact, $ploomesServices)
    {
        
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
   
        $current = date('d/m/Y H:i:s');

        //verificar se existe um cleinte cadastrado no ploomes com o cnpj e se existir deleta
        $cnpj = DiverseFunctions::limpa_cpf_cnpj($contact->cnpjCpf);
        $pContact = $ploomesServices->consultaClientePloomesCnpj($cnpj);
       
        if($pContact === null){
            $messages['error'] = 'Erro ao exluir o cliente '.$contact->nomeFantasia.' Não foi encontrado no Ploomes. Data: '.$current ;
        }else{
            $ploomesServices->deletePloomesContact($pContact);
            $messages['success'] = 'Cliente '.$contact->nomeFantasia.' excluído do Omie ERP e do Ploomes CRM. Data: '.$current;
        }

        return $messages;
    }
}