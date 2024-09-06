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
}