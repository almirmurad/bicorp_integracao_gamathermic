<?php
namespace src\functions;

use src\exceptions\WebhookReadErrorException;
use src\models\Contact;
use stdClass;


class ClientsFunctions{

    // encontra o processo a ser executado caso haja cadastro, exclusão ou alteração no webhook
    public static function findAction($webhook)
    {
        //decodifica o json de clientes vindos do webhook
        $json = $webhook['json'];
        $decoded = json_decode($json,true);
        //identifica qual action do webhook
        if(isset($decoded['Action'])){

            $action = match($decoded['Action']){
                'Create' => 'createCRMToERP',
                'Update' => 'updateCRMToERP',
                'Delete' => 'deleteCRMToERP'
            };
        }elseif(isset($decoded['topic'])){
            $action = match($decoded['topic']){
                'ClienteFornecedor.Incluido' => 'createERPToCRM',
                'ClienteFornecedor.Alterado' => 'updateERPToCRM',
                'ClienteFornecedor.Excluido' => 'deleteERPToCRM'
            };
        }else{
            throw new WebhookReadErrorException('Não foi encontrda nenhuma ação no webhook '.$current, 500);
        }

        return $action;

    }

    //cria obj cliente
    public static function createObj($webhook, $ploomesServices)
    {
    
        //decodifica o json de clientes vindos do webhook
        $json = $webhook['json'];
        $decoded = json_decode($json,true);

        $cliente = $ploomesServices->getClientById($decoded['New']['Id']);
        
        $contact = new Contact();        
    
        /************************************************************
         *                   Other Properties                        *
         *                                                           *
         * No webhook do Contact pegamos os campos de Other Properies*
         * para encontrar a chave da base de faturamento do Omie     *
         *                                                           *
         *************************************************************/
        $prop = [];
        foreach ($decoded['New']['OtherProperties'] as $key => $op) {
            $prop[$key] = $op;
            // print '['.$key.']=>['.$op.']';
        }
        //contact_879A3AA2-57B1-49DC-AEC2-21FE89617665 = tipo de cliente
        $contact->tipoCliente = $prop['contact_879A3AA2-57B1-49DC-AEC2-21FE89617665'];
        //contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227 = ramo de atividade
        $contact->ramoAtividade = $prop['contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227'];
        //contact_FA99392B-CED8-4668-B003-DFC1111DACB0 = Porte
        $contact->porte = $prop['contact_FA99392B-CED8-4668-B003-DFC1111DACB0'];
        //contact_20B72360-82CF-4806-BB05-21E89D5C61FD = importância
        $contact->importancia = $prop['contact_20B72360-82CF-4806-BB05-21E89D5C61FD'];
        //contact_5F52472B-E311-4574-96E2-3181EADFAFBE = situação
        $contact->situacao = $prop['contact_5F52472B-E311-4574-96E2-3181EADFAFBE'];
        //contact_9E595E72-E50C-4E95-9A05-D3B024C177AD = ciclo de compra
        $contact->cicloCompra = $prop['contact_9E595E72-E50C-4E95-9A05-D3B024C177AD'];
        //contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB = inscrição estadual
        $contact->inscricaoEstadual = $prop['contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB'] ?? null;
        //contact_D21FAEED-75B2-40E4-B169-503131EB3609 = inscrição municipal
        $contact->inscricaoMunicipal = $prop['contact_D21FAEED-75B2-40E4-B169-503131EB3609'] ?? null;
        //contact_3094AFFE-4263-43B6-A14B-8B0708CA1160 = inscrição suframa
        $contact->inscricaoSuframa = $prop['contact_3094AFFE-4263-43B6-A14B-8B0708CA1160'] ?? null;
        //contact_9BB527FD-8277-4D1F-AF99-DD88D5064719 = Simples nacional?(s/n)  
        $contact->simplesNacional = $prop['contact_9BB527FD-8277-4D1F-AF99-DD88D5064719'];
        ($contact->simplesNacional) ? $contact->simplesNacional = 'S' : $contact->simplesNacional = 'N';
        //contact_3C521209-46BD-4EA5-9F41-34756621CCB4 = contato1
        $contact->contato1 = $prop['contact_3C521209-46BD-4EA5-9F41-34756621CCB4'] ?? null;
        //contact_F9B60153-6BDF-4040-9C3A-E23B1469894A = Produtor Rural
        $contact->produtorRural = $prop['contact_F9B60153-6BDF-4040-9C3A-E23B1469894A'];
        ($contact->produtorRural) ? $contact->produtorRural = 'S' : $contact->produtorRural = 'N';
        //contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453 = Contribuinte(s/n)
        $contact->contribuinte = $prop['contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453'];
        ($contact->contribuinte) ? $contact->contribuinte = 'S' : $contact->contribuinte = 'N';
        //contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD = limite de crédito
        $contact->limiteCredito = $prop['contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD'] ?? null;
        //contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4 = inativo (s/n)
        $contact->inativo = $prop['contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4'] ?? null;
        ($contact->inativo) ? $contact->inativo = 'S' : $contact->inativo = 'N';
        //contact_C613A391-155B-42F5-9C92-20C3371CC3DE = bloqueia excusão (s/n)
        $contact->bloquearExclusao = $prop['contact_C613A391-155B-42F5-9C92-20C3371CC3DE'] ?? null;
        ($contact->bloquearExclusao) ? $contact->bloquearExclusao = 'S' : $contact->bloquearExclusao = 'N';
        //contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33 = transportadora padrão
        $contact->cTransportadoraPadrao = $prop['contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33'] ?? null;
        //contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9 = codigo do banco
        $contact->cBanco = $prop['contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9'] ?? null;
        //contact_1F1E1F00-34CB-4356-B852-496D62A90E10 = Agência
        $contact->agencia = $prop['contact_1F1E1F00-34CB-4356-B852-496D62A90E10'] ?? null;
        //contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80 = Num conta corrente
        $contact->nContaCorrente = $prop['contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80'] ?? null;
        //contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50 = CNPJ/CPF titular
        $contact->docTitular = $prop['contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50'] ?? null;
        //contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066 = Nome di titular
        $contact->nomeTitular = $prop['contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066'] ?? null;
        //contact_847FE760-74D0-462D-B464-9E89C7E1C28E = chave pix
        $contact->chavePix = $prop['contact_847FE760-74D0-462D-B464-9E89C7E1C28E'] ?? null;
        //contact_33015EDD-B3A7-464E-81D0-5F38D31F604A = Transferência Padrão
        $contact->transferenciaPadrao = $prop['contact_33015EDD-B3A7-464E-81D0-5F38D31F604A'];
        ($contact->transferenciaPadrao) ? $contact->transferenciaPadrao = 'S' : $contact->transferenciaPadrao = 'N';
        //contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C = Integrar com base omie 1? (s/n)
        ($prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C']) ? $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 1 : $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 0;
        //contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C = Integrar com base omie 2? (s/n)
        ($prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C']) ? $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 1 : $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 0;
        //contact_02AA406F-F955-4AE0-B380-B14301D1188B = Integrar com base omie 3? (s/n)
        ($prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B']) ? $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 1 : $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 0;
        //contact_E497C521-4275-48E7-B44E-7A057844B045 = Integrar com base omie 4? (s/n)
        // ($prop['contact_E497C521-4275-48E7-B44E-7A057844B045']) ? $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 1 : $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 0;
        
        $phones = [];
        foreach($cliente['Phones'] as $phone){
            
            $partes = explode(' ',$phone['PhoneNumber']);
            $ddd = $partes[0];
            $nPhone = $partes[1];
            $phones[] = [
                'ddd'=>$ddd,
                'nPhone' => $nPhone
            ];        
        }

        $contact->id = $cliente['Id']; //Id do Contact
        $contact->name = $cliente['Name'] ?? null; // Nome ou nome fantasia do contact
        $contact->legalName = $cliente['LegalName'] ?? null; // Razão social do contact
        $contact->cnpj = $cliente['CNPJ'] ?? null; // Contatos CNPJ
        $contact->cpf = $cliente['CPF'] ?? null; // Contatos CPF
        $contact->documentoExterior = $cliente['IdentityDocument']; // Contatos CPF
        $contact->segmento = $cliente['LineOfBusiness']['Id'] ?? null; // Contatos CPF
        $contact->email = $cliente['Email']; // Contatos Email obrigatório
        $contact->website = $cliente['Website'] ?? null; // Contatos Email obrigatório
        $contact->ddd1 = $phones[0]['ddd']; //"telefone1_ddd": "011",
        $contact->phone1 = $phones[0]['nPhone']; //"telefone1_numero": "2737-2737",
        $contact->ddd2 = $phones[1]['ddd'] ?? null; //"telefone1_ddd": "011",
        $contact->phone2 = $phones[1]['nPhone'] ?? null; //"telefone1_numero": "2737-2737",
        //$contact->contato1 = $prop['contact_E6008BF6-A43D-4D1C-813E-C6BD8C077F77'] ?? null;
        $contact->streetAddress = $cliente['StreetAddress']; // Endereço Obrigatório
        $contact->streetAddressNumber = $cliente['StreetAddressNumber']; // Número Endereço Obrigatório
        $contact->streetAddressLine2 = $cliente['StreetAddressLine2'] ?? null; // complemento do Endereço 
        $contact->neighborhood = $cliente['Neighborhood']; // bairro do Endereço é obrigatório
        $contact->zipCode = $cliente['ZipCode']; // CEP do Endereço é obrigatório
        $contact->cityId = $cliente['City']['IBGECode']; // Id da cidade é obrigatório
        $contact->cityName = $cliente['City']['Name']; // estamos pegando o IBGE code
        $contact->cityLagitude = $cliente['City']['Latitude']; // Latitude da cidade é obrigatório
        $contact->cityLongitude = $cliente['City']['Longitude']; // Longitude da cidade é obrigatório
        $contact->stateShort = $cliente['State']['Short']; // Sigla do estado é obrigatório
        $contact->stateName = $cliente['State']['Name']; //estamos pegando a sigla do estado
        $contact->countryId = $cliente['CountryId'] ?? null; // Omie preenche o país automaticamente
        $contact->cnaeCode = $cliente['CnaeCode'] ?? null; // Id do cnae 
        $contact->cnaeName = $cliente['CnaeName'] ?? null; // Name do cnae 
        $contact->ownerId = $cliente['Owner']['Id']; // Responsável (Vendedor)
        $contact->ownerEmail = 'tecnologia@bicorp.com.br';// Responsável (Vendedor) 
        //$contact->ownerEmail = $cliente['Owner']['Email']; // Responsável (Vendedor) 
        $contact->observacao = $cliente['Note']; // Observação 
        
        // Base de Faturamento para fiel não precisa pois integra e depois a automação distribui em todas as bases, em gamathermic precisa
        $bases = [];
        
        $bases[0]['fieldKey'] = 'contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C';
        $bases[0]['title'] = 'ENGEPARTS';
        $bases[0]['sigla'] = 'EPT';
        $bases[0]['integrar'] = $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'];
        $bases[0]['appKey'] = $_ENV['APPK_DEMO']??null;
        $bases[0]['appSecret'] = $_ENV['SECRETS_DEMO']??null;
        // $bases[0]['appKey'] = $_ENV['APPK_EPT']??null;
        // $bases[0]['appSecret'] = $_ENV['SECRETS_EPT']??null;

        $bases[1]['fieldKey'] = 'contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C';
        $bases[1]['title'] = 'GAMATERMIC';
        $bases[1]['sigla'] = 'GTC';
        $bases[1]['integrar'] = $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'];
        // $bases[1]['appKey'] = $_ENV['APPK_GTC']??null;
        // $bases[1]['appSecret'] = $_ENV['SECRETS_GTC']??null;
        $bases[1]['appKey'] = $_ENV['APPK_MPR']??null;
        $bases[1]['appSecret'] = $_ENV['SECRETS_MPR']??null;

        $bases[2]['fieldKey'] = 'contact_02AA406F-F955-4AE0-B380-B14301D1188B';
        $bases[2]['title'] = 'SEMIN';
        $bases[2]['sigla'] = 'SMN';
        $bases[2]['integrar'] = $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'];
        // $bases[2]['appKey'] = $_ENV['APPK_SMN']??null;
        // $bases[2]['appSecret'] = $_ENV['SECRETS_SMN']??null;
        $bases[2]['appKey'] = $_ENV['APPK_MHL']??null;
        $bases[2]['appSecret'] = $_ENV['SECRETS_MHL']??null;
        
        $bases[3]['fieldKey'] = 'contact_E497C521-4275-48E7-B44E-7A057844B045';
        $bases[3]['title'] = 'GSU';
        $bases[3]['sigla'] = 'GSU';
        $bases[3]['integrar'] = $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] ?? null;
        // $bases[3]['appKey'] = $_ENV['APPK_GSU']??null;
        // $bases[3]['appSecret'] = $_ENV['SECRETS_GSU']??null;
        $bases[2]['appKey'] = $_ENV['APPK_MSC']??null;
        $bases[2]['appSecret'] = $_ENV['SECRETS_MSC']??null;
        
        
        //switch para uma base serve mas para as 4 base não pois ele vai verificar se existe base de faturamento em apenas uma das opções
        // switch($prop){
            
        //     case $base1:
            //         $contact->baseFaturamentoTitle = 'ENGEPARTS';
            //         $contact->baseFaturamentoSigla = 'EPT';
            //         break;
            //     case $base2:
                //         $contact->baseFaturamentoTitle = 'GAMATERMIC';
                //         $contact->baseFaturamentoSigla = 'GTC';
                //         break;
        //     case $base3:
        //         $contact->baseFaturamentoTitle = 'SEMIN';
        //         $contact->baseFaturamentoSigla = 'SMN';
        //         break;
        //     case $base4:
        //         $contact->baseFaturamentoTitle = 'GSU';
        //         $contact->baseFaturamentoSigla = 'GSU';
        //         break;
        
        // }
        
        // (!empty($contact->baseFaturamento))? $contact->baseFaturamento : $m[] = 'Base de faturamento inexistente';
        $contact->basesFaturamento = $bases;        
        
        $tags= [];
        $tag=[];

        if($cliente['Tags']){

            foreach($cliente['Tags'] as $iTag){

                $tag['tag']=$iTag['Tag']['Name'];
                
                $tags[]=$tag;
            }
        }
        $contact->tags = $tags;

        return $contact;
    }

    //cria Old obj cliente
    public static function createOldObj($webhook, $ploomesServices)
    {
        
        //decodifica o json de clientes vindos do webhook
        $json = $webhook['json'];
        $decoded = json_decode($json,true);
        
        $cliente = $decoded['Old'];

        $contact = new Contact();     
    
        /************************************************************
         *                   Other Properties                        *
         *                                                           *
         * No webhook do Contact pegamos os campos de Other Properies*
         * para encontrar a chave da base de faturamento do Omie     *
         *                                                           *
         *************************************************************/
        $prop = [];
        foreach ($decoded['Old']['OtherProperties'] as $key => $op) {
            $prop[$key] = $op;
            // print '['.$key.']=>['.$op.']';
        }
        //contact_879A3AA2-57B1-49DC-AEC2-21FE89617665 = tipo de cliente
        $contact->tipoCliente = $prop['contact_879A3AA2-57B1-49DC-AEC2-21FE89617665'];
        //contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227 = ramo de atividade
        $contact->ramoAtividade = $prop['contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227'];
        //contact_FA99392B-CED8-4668-B003-DFC1111DACB0 = Porte
        $contact->porte = $prop['contact_FA99392B-CED8-4668-B003-DFC1111DACB0'];
        //contact_20B72360-82CF-4806-BB05-21E89D5C61FD = importância
        $contact->importancia = $prop['contact_20B72360-82CF-4806-BB05-21E89D5C61FD'];
        //contact_5F52472B-E311-4574-96E2-3181EADFAFBE = situação
        $contact->situacao = $prop['contact_5F52472B-E311-4574-96E2-3181EADFAFBE'];
        //contact_9E595E72-E50C-4E95-9A05-D3B024C177AD = ciclo de compra
        $contact->cicloCompra = $prop['contact_9E595E72-E50C-4E95-9A05-D3B024C177AD'];
        //contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB = inscrição estadual
        $contact->inscricaoEstadual = $prop['contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB'] ?? null;
        //contact_D21FAEED-75B2-40E4-B169-503131EB3609 = inscrição municipal
        $contact->inscricaoMunicipal = $prop['contact_D21FAEED-75B2-40E4-B169-503131EB3609'] ?? null;
        //contact_3094AFFE-4263-43B6-A14B-8B0708CA1160 = inscrição suframa
        $contact->inscricaoSuframa = $prop['contact_3094AFFE-4263-43B6-A14B-8B0708CA1160'] ?? null;
        //contact_9BB527FD-8277-4D1F-AF99-DD88D5064719 = Simples nacional?(s/n)  
        $contact->simplesNacional = $prop['contact_9BB527FD-8277-4D1F-AF99-DD88D5064719'];
        ($contact->simplesNacional) ? $contact->simplesNacional = 'S' : $contact->simplesNacional = 'N';
        //contact_3C521209-46BD-4EA5-9F41-34756621CCB4 = contato1
        $contact->contato1 = $prop['contact_3C521209-46BD-4EA5-9F41-34756621CCB4'] ?? null;
        //contact_F9B60153-6BDF-4040-9C3A-E23B1469894A = Produtor Rural
        $contact->produtorRural = $prop['contact_F9B60153-6BDF-4040-9C3A-E23B1469894A'];
        ($contact->produtorRural) ? $contact->produtorRural = 'S' : $contact->produtorRural = 'N';
        //contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453 = Contribuinte(s/n)
        $contact->contribuinte = $prop['contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453'];
        ($contact->contribuinte) ? $contact->contribuinte = 'S' : $contact->contribuinte = 'N';
        //contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD = limite de crédito
        $contact->limiteCredito = $prop['contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD'] ?? null;
        //contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4 = inativo (s/n)
        $contact->inativo = $prop['contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4'];
        ($contact->inativo) ? $contact->inativo = 'S' : $contact->inativo = 'N';
        //contact_C613A391-155B-42F5-9C92-20C3371CC3DE = bloqueia excusão (s/n)
        $contact->bloquearExclusao = $prop['contact_C613A391-155B-42F5-9C92-20C3371CC3DE'] ?? null;
        ($contact->bloquearExclusao) ? $contact->bloquearExclusao = 'S' : $contact->bloquearExclusao = 'N';
        //contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33 = transportadora padrão
        $contact->cTransportadoraPadrao = $prop['contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33'] ?? null;
        //contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9 = codigo do banco
        $contact->cBanco = $prop['contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9'] ?? null;
        //contact_1F1E1F00-34CB-4356-B852-496D62A90E10 = Agência
        $contact->agencia = $prop['contact_1F1E1F00-34CB-4356-B852-496D62A90E10'] ?? null;
        //contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80 = Num conta corrente
        $contact->nContaCorrente = $prop['contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80'] ?? null;
        //contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50 = CNPJ/CPF titular
        $contact->docTitular = $prop['contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50'] ?? null;
        //contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066 = Nome di titular
        $contact->nomeTitular = $prop['contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066'] ?? null;
        //contact_847FE760-74D0-462D-B464-9E89C7E1C28E = chave pix
        $contact->chavePix = $prop['contact_847FE760-74D0-462D-B464-9E89C7E1C28E'] ?? null;
        //contact_33015EDD-B3A7-464E-81D0-5F38D31F604A = Transferência Padrão
        $contact->transferenciaPadrao = $prop['contact_33015EDD-B3A7-464E-81D0-5F38D31F604A'];
        ($contact->transferenciaPadrao) ? $contact->transferenciaPadrao = 'S' : $contact->transferenciaPadrao = 'N';
        //contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C = Integrar com base omie 1? (s/n)
        ($prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C']) ? $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 1 : $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 0;
        //contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C = Integrar com base omie 2? (s/n)
        ($prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C']) ? $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 1 : $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 0;
        //contact_02AA406F-F955-4AE0-B380-B14301D1188B = Integrar com base omie 3? (s/n)
        ($prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B']) ? $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 1 : $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 0;
        //contact_E497C521-4275-48E7-B44E-7A057844B045 = Integrar com base omie 4? (s/n)
        // ($prop['contact_E497C521-4275-48E7-B44E-7A057844B045']) ? $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 1 : $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 0;
        
        $phones = [];
        foreach($cliente['Phones'] as $phone){
            
            $partes = explode(' ',$phone['PhoneNumber']);
            $ddd = $partes[0];
            $nPhone = $partes[1];
            $phones[] = [
                'ddd'=>$ddd,
                'nPhone' => $nPhone
            ];        
        }

        $contact->id = $cliente['Id']; //Id do Contact
        $contact->name = $cliente['Name'] ?? null; // Nome ou nome fantasia do contact
        $contact->legalName = $cliente['LegalName'] ?? null; // Razão social do contact
        $contact->cnpj = $cliente['CNPJ'] ?? null; // Contatos CNPJ
        $contact->cpf = $cliente['CPF'] ?? null; // Contatos CPF
        $contact->documentoExterior = $cliente['IdentityDocument']; // Contatos CPF
        $contact->segmento = $cliente['LineOfBusinessId'] ?? null; // Contatos CPF
        $contact->email = $cliente['Email']; // Contatos Email obrigatório
        $contact->website = $cliente['Website'] ?? null; // Contatos Email obrigatório
        $contact->ddd1 = $phones[0]['ddd']; //"telefone1_ddd": "011",
        $contact->phone1 = $phones[0]['nPhone']; //"telefone1_numero": "2737-2737",
        $contact->ddd2 = $phones[1]['ddd'] ?? null; //"telefone1_ddd": "011",
        $contact->phone2 = $phones[1]['nPhone'] ?? null; //"telefone1_numero": "2737-2737",
        //$contact->contato1 = $prop['contact_E6008BF6-A43D-4D1C-813E-C6BD8C077F77'] ?? null;
        $contact->streetAddress = $cliente['StreetAddress']; // Endereço Obrigatório
        $contact->streetAddressNumber = $cliente['StreetAddressNumber']; // Número Endereço Obrigatório
        $contact->streetAddressLine2 = $cliente['StreetAddressLine2'] ?? null; // complemento do Endereço 
        $contact->neighborhood = $cliente['Neighborhood']; // bairro do Endereço é obrigatório
        $contact->zipCode = $cliente['ZipCode']; // CEP do Endereço é obrigatório
        //$contact->cityId = $cliente['City']['IBGECode']; // Id da cidade é obrigatório
        $cities = $ploomesServices->getCitiesById($cliente['CityId']);
        $contact->cityId = $cities['IBGECode'];
        $contact->cityName = $cities['Name']; // estamos pegando o IBGE code
        $contact->cityLagitude = $cities['Latitude']; // Latitude da cidade é obrigatório
        $contact->cityLongitude = $cities['Longitude']; // Longitude da cidade é obrigatório
        $state = $ploomesServices->getStateById($cities['StateId']);
        $contact->stateShort = $state['Short']; // Sigla do estado é obrigatório
        $contact->stateName = $state['Name']; //estamos pegando a sigla do estado
        //$contact->countryId = $cliente['CountryId'] ?? null; // Omie preenche o país automaticamente
        $contact->cnaeCode = $cliente['CnaeCode'] ?? null; // Id do cnae 
        $contact->cnaeName = $cliente['CnaeName'] ?? null; // Name do cnae 
        $contact->ownerId = $cliente['OwnerId']; // Responsável (Vendedor)

        $contact->ownerEmail = $ploomesServices->ownerMail($contact);// Responsável (Vendedor) 
        //$contact->ownerEmail = $cliente['Owner']['Email']; // Responsável (Vendedor) 
        $contact->observacao = $cliente['Note']; // Observação 
        
        // Base de Faturamento para fiel não precisa pois integra e depois a automação distribui em todas as bases, em gamathermic precisa
        $bases = [];
        
        $bases[0]['fieldKey'] = 'contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C';
        $bases[0]['title'] = 'ENGEPARTS';
        $bases[0]['sigla'] = 'EPT';
        $bases[0]['integrar'] = $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'];
        $bases[0]['appKey'] = $_ENV['APPK_DEMO']??null;
        $bases[0]['appSecret'] = $_ENV['SECRETS_DEMO']??null;
        // $bases[0]['appKey'] = $_ENV['APPK_EPT']??null;
        // $bases[0]['appSecret'] = $_ENV['SECRETS_EPT']??null;

        $bases[1]['fieldKey'] = 'contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C';
        $bases[1]['title'] = 'GAMATERMIC';
        $bases[1]['sigla'] = 'GTC';
        $bases[1]['integrar'] = $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'];
        // $bases[1]['appKey'] = $_ENV['APPK_GTC']??null;
        // $bases[1]['appSecret'] = $_ENV['SECRETS_GTC']??null;
        $bases[1]['appKey'] = $_ENV['APPK_MPR']??null;
        $bases[1]['appSecret'] = $_ENV['SECRETS_MPR']??null;

        $bases[2]['fieldKey'] = 'contact_02AA406F-F955-4AE0-B380-B14301D1188B';
        $bases[2]['title'] = 'SEMIN';
        $bases[2]['sigla'] = 'SMN';
        $bases[2]['integrar'] = $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'];
        // $bases[2]['appKey'] = $_ENV['APPK_SMN']??null;
        // $bases[2]['appSecret'] = $_ENV['SECRETS_SMN']??null;
        $bases[2]['appKey'] = $_ENV['APPK_MHL']??null;
        $bases[2]['appSecret'] = $_ENV['SECRETS_MHL']??null;
        
        $bases[3]['fieldKey'] = 'contact_E497C521-4275-48E7-B44E-7A057844B045';
        $bases[3]['title'] = 'GSU';
        $bases[3]['sigla'] = 'GSU';
        $bases[3]['integrar'] = $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] ?? null;
        // $bases[3]['appKey'] = $_ENV['APPK_GSU']??null;
        // $bases[3]['appSecret'] = $_ENV['SECRETS_GSU']??null;
        $bases[2]['appKey'] = $_ENV['APPK_MSC']??null;
        $bases[2]['appSecret'] = $_ENV['SECRETS_MSC']??null;
        
        
        //switch para uma base serve mas para as 4 base não pois ele vai verificar se existe base de faturamento em apenas uma das opções
        // switch($prop){
            
        //     case $base1:
            //         $contact->baseFaturamentoTitle = 'ENGEPARTS';
            //         $contact->baseFaturamentoSigla = 'EPT';
            //         break;
            //     case $base2:
                //         $contact->baseFaturamentoTitle = 'GAMATERMIC';
                //         $contact->baseFaturamentoSigla = 'GTC';
                //         break;
        //     case $base3:
        //         $contact->baseFaturamentoTitle = 'SEMIN';
        //         $contact->baseFaturamentoSigla = 'SMN';
        //         break;
        //     case $base4:
        //         $contact->baseFaturamentoTitle = 'GSU';
        //         $contact->baseFaturamentoSigla = 'GSU';
        //         break;
        
        // }
        
        // (!empty($contact->baseFaturamento))? $contact->baseFaturamento : $m[] = 'Base de faturamento inexistente';
        $contact->basesFaturamento = $bases;        
        
        
        $tags= [];
        $tag=[];

        if($cliente['Tags']){

            foreach($cliente['Tags'] as $iTag){

                $tag['tag']=$iTag['Tag']['Name'];
                
                $tags[]=$tag;
            }
        }
        $contact->tags = $tags;

        return $contact;
    }

    //cria New obj cliente
    public static function createNewObj($webhook, $ploomesServices)
    {
        //decodifica o json de clientes vindos do webhook
        $json = $webhook['json'];
        $decoded = json_decode($json,true);
        
        $cliente = $decoded['New'];

        $contact = new Contact();        
    
        /************************************************************
         *                   Other Properties                        *
         *                                                           *
         * No webhook do Contact pegamos os campos de Other Properies*
         * para encontrar a chave da base de faturamento do Omie     *
         *                                                           *
         *************************************************************/
        $prop = [];
        foreach ($decoded['New']['OtherProperties'] as $key => $op) {
            $prop[$key] = $op;
            // print '['.$key.']=>['.$op.']';
        }
        //contact_879A3AA2-57B1-49DC-AEC2-21FE89617665 = tipo de cliente
        $contact->tipoCliente = $prop['contact_879A3AA2-57B1-49DC-AEC2-21FE89617665'];
        //contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227 = ramo de atividade
        $contact->ramoAtividade = $prop['contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227'];
        //contact_FA99392B-CED8-4668-B003-DFC1111DACB0 = Porte
        $contact->porte = $prop['contact_FA99392B-CED8-4668-B003-DFC1111DACB0'];
        //contact_20B72360-82CF-4806-BB05-21E89D5C61FD = importância
        $contact->importancia = $prop['contact_20B72360-82CF-4806-BB05-21E89D5C61FD'];
        //contact_5F52472B-E311-4574-96E2-3181EADFAFBE = situação
        $contact->situacao = $prop['contact_5F52472B-E311-4574-96E2-3181EADFAFBE'];
        //contact_9E595E72-E50C-4E95-9A05-D3B024C177AD = ciclo de compra
        $contact->cicloCompra = $prop['contact_9E595E72-E50C-4E95-9A05-D3B024C177AD'];
        //contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB = inscrição estadual
        $contact->inscricaoEstadual = $prop['contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB'] ?? null;
        //contact_D21FAEED-75B2-40E4-B169-503131EB3609 = inscrição municipal
        $contact->inscricaoMunicipal = $prop['contact_D21FAEED-75B2-40E4-B169-503131EB3609'] ?? null;
        //contact_3094AFFE-4263-43B6-A14B-8B0708CA1160 = inscrição suframa
        $contact->inscricaoSuframa = $prop['contact_3094AFFE-4263-43B6-A14B-8B0708CA1160'] ?? null;
        //contact_9BB527FD-8277-4D1F-AF99-DD88D5064719 = Simples nacional?(s/n)  
        $contact->simplesNacional = $prop['contact_9BB527FD-8277-4D1F-AF99-DD88D5064719'];
        ($contact->simplesNacional) ? $contact->simplesNacional = 'S' : $contact->simplesNacional = 'N';
        //contact_3C521209-46BD-4EA5-9F41-34756621CCB4 = contato1
        $contact->contato1 = $prop['contact_3C521209-46BD-4EA5-9F41-34756621CCB4'] ?? null;
        //contact_F9B60153-6BDF-4040-9C3A-E23B1469894A = Produtor Rural
        $contact->produtorRural = $prop['contact_F9B60153-6BDF-4040-9C3A-E23B1469894A'];
        ($contact->produtorRural) ? $contact->produtorRural = 'S' : $contact->produtorRural = 'N';
        //contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453 = Contribuinte(s/n)
        $contact->contribuinte = $prop['contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453'];
        ($contact->contribuinte) ? $contact->contribuinte = 'S' : $contact->contribuinte = 'N';
        //contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD = limite de crédito
        $contact->limiteCredito = $prop['contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD'] ?? null;
        //contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4 = inativo (s/n)
        $contact->inativo = $prop['contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4'];
        ($contact->inativo) ? $contact->inativo = 'S' : $contact->inativo = 'N';
        //contact_C613A391-155B-42F5-9C92-20C3371CC3DE = bloqueia excusão (s/n)
        $contact->bloquearExclusao = $prop['contact_C613A391-155B-42F5-9C92-20C3371CC3DE'] ?? null;
        ($contact->bloquearExclusao) ? $contact->bloquearExclusao = 'S' : $contact->bloquearExclusao = 'N';
        //contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33 = transportadora padrão
        $contact->cTransportadoraPadrao = $prop['contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33'] ?? null;
        //contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9 = codigo do banco
        $contact->cBanco = $prop['contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9'] ?? null;
        //contact_1F1E1F00-34CB-4356-B852-496D62A90E10 = Agência
        $contact->agencia = $prop['contact_1F1E1F00-34CB-4356-B852-496D62A90E10'] ?? null;
        //contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80 = Num conta corrente
        $contact->nContaCorrente = $prop['contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80'] ?? null;
        //contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50 = CNPJ/CPF titular
        $contact->docTitular = $prop['contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50'] ?? null;
        //contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066 = Nome di titular
        $contact->nomeTitular = $prop['contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066'] ?? null;
        //contact_847FE760-74D0-462D-B464-9E89C7E1C28E = chave pix
        $contact->chavePix = $prop['contact_847FE760-74D0-462D-B464-9E89C7E1C28E'] ?? null;
        //contact_33015EDD-B3A7-464E-81D0-5F38D31F604A = Transferência Padrão
        $contact->transferenciaPadrao = $prop['contact_33015EDD-B3A7-464E-81D0-5F38D31F604A'];
        ($contact->transferenciaPadrao) ? $contact->transferenciaPadrao = 'S' : $contact->transferenciaPadrao = 'N';
        //contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C = Integrar com base omie 1? (s/n)
        ($prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C']) ? $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 1 : $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 0;
        //contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C = Integrar com base omie 2? (s/n)
        ($prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C']) ? $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 1 : $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 0;
        //contact_02AA406F-F955-4AE0-B380-B14301D1188B = Integrar com base omie 3? (s/n)
        ($prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B']) ? $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 1 : $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 0;
        //contact_E497C521-4275-48E7-B44E-7A057844B045 = Integrar com base omie 4? (s/n)
        // ($prop['contact_E497C521-4275-48E7-B44E-7A057844B045']) ? $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 1 : $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 0;
        
        $phones = [];
        foreach($cliente['Phones'] as $phone){
            
            $partes = explode(' ',$phone['PhoneNumber']);
            $ddd = $partes[0];
            $nPhone = $partes[1];
            $phones[] = [
                'ddd'=>$ddd,
                'nPhone' => $nPhone
            ];        
        }

        $contact->id = $cliente['Id']; //Id do Contact
        $contact->name = $cliente['Name'] ?? null; // Nome ou nome fantasia do contact
        $contact->legalName = $cliente['LegalName'] ?? null; // Razão social do contact
        $contact->cnpj = $cliente['CNPJ'] ?? null; // Contatos CNPJ
        $contact->cpf = $cliente['CPF'] ?? null; // Contatos CPF
        $contact->documentoExterior = $cliente['IdentityDocument']; // Contatos CPF
        $contact->segmento = $cliente['LineOfBusinessId'] ?? null; // Contatos CPF
        $contact->email = $cliente['Email']; // Contatos Email obrigatório
        $contact->website = $cliente['Website'] ?? null; // Contatos Email obrigatório
        $contact->ddd1 = $phones[0]['ddd']; //"telefone1_ddd": "011",
        $contact->phone1 = $phones[0]['nPhone']; //"telefone1_numero": "2737-2737",
        $contact->ddd2 = $phones[1]['ddd'] ?? null; //"telefone1_ddd": "011",
        $contact->phone2 = $phones[1]['nPhone'] ?? null; //"telefone1_numero": "2737-2737",
        //$contact->contato1 = $prop['contact_E6008BF6-A43D-4D1C-813E-C6BD8C077F77'] ?? null;
        $contact->streetAddress = $cliente['StreetAddress']; // Endereço Obrigatório
        $contact->streetAddressNumber = $cliente['StreetAddressNumber']; // Número Endereço Obrigatório
        $contact->streetAddressLine2 = $cliente['StreetAddressLine2'] ?? null; // complemento do Endereço 
        $contact->neighborhood = $cliente['Neighborhood']; // bairro do Endereço é obrigatório
        $contact->zipCode = $cliente['ZipCode']; // CEP do Endereço é obrigatório
        //$contact->cityId = $cliente['City']['IBGECode']; // Id da cidade é obrigatório
        $cities = $ploomesServices->getCitiesById($cliente['CityId']);
        $contact->cityId = $cities['IBGECode'];
        $contact->cityName = $cities['Name']; // estamos pegando o IBGE code
        $contact->cityLagitude = $cities['Latitude']; // Latitude da cidade é obrigatório
        $contact->cityLongitude = $cities['Longitude']; // Longitude da cidade é obrigatório
        $state = $ploomesServices->getStateById($cities['StateId']);
        $contact->stateShort = $state['Short']; // Sigla do estado é obrigatório
        $contact->stateName = $state['Name']; //estamos pegando a sigla do estado
        //$contact->countryId = $cliente['CountryId'] ?? null; // Omie preenche o país automaticamente
        $contact->cnaeCode = $cliente['CnaeCode'] ?? null; // Id do cnae 
        $contact->cnaeName = $cliente['CnaeName'] ?? null; // Name do cnae 
        $contact->ownerId = $cliente['OwnerId']; // Responsável (Vendedor)

        $contact->ownerEmail = $ploomesServices->ownerMail($contact);// Responsável (Vendedor) 
        //$contact->ownerEmail = $cliente['Owner']['Email']; // Responsável (Vendedor) 
        $contact->observacao = $cliente['Note']; // Observação 
        
        // Base de Faturamento para fiel não precisa pois integra e depois a automação distribui em todas as bases, em gamathermic precisa
        $bases = [];
        
        $bases[0]['fieldKey'] = 'contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C';
        $bases[0]['title'] = 'ENGEPARTS';
        $bases[0]['sigla'] = 'EPT';
        $bases[0]['integrar'] = $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'];
        $bases[0]['appKey'] = $_ENV['APPK_DEMO']??null;
        $bases[0]['appSecret'] = $_ENV['SECRETS_DEMO']??null;
        // $bases[0]['appKey'] = $_ENV['APPK_EPT']??null;
        // $bases[0]['appSecret'] = $_ENV['SECRETS_EPT']??null;

        $bases[1]['fieldKey'] = 'contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C';
        $bases[1]['title'] = 'GAMATERMIC';
        $bases[1]['sigla'] = 'GTC';
        $bases[1]['integrar'] = $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'];
        // $bases[1]['appKey'] = $_ENV['APPK_GTC']??null;
        // $bases[1]['appSecret'] = $_ENV['SECRETS_GTC']??null;
        $bases[1]['appKey'] = $_ENV['APPK_MPR']??null;
        $bases[1]['appSecret'] = $_ENV['SECRETS_MPR']??null;

        $bases[2]['fieldKey'] = 'contact_02AA406F-F955-4AE0-B380-B14301D1188B';
        $bases[2]['title'] = 'SEMIN';
        $bases[2]['sigla'] = 'SMN';
        $bases[2]['integrar'] = $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'];
        // $bases[2]['appKey'] = $_ENV['APPK_SMN']??null;
        // $bases[2]['appSecret'] = $_ENV['SECRETS_SMN']??null;
        $bases[2]['appKey'] = $_ENV['APPK_MHL']??null;
        $bases[2]['appSecret'] = $_ENV['SECRETS_MHL']??null;
        
        $bases[3]['fieldKey'] = 'contact_E497C521-4275-48E7-B44E-7A057844B045';
        $bases[3]['title'] = 'GSU';
        $bases[3]['sigla'] = 'GSU';
        $bases[3]['integrar'] = $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] ?? null;
        // $bases[3]['appKey'] = $_ENV['APPK_GSU']??null;
        // $bases[3]['appSecret'] = $_ENV['SECRETS_GSU']??null;
        $bases[2]['appKey'] = $_ENV['APPK_MSC']??null;
        $bases[2]['appSecret'] = $_ENV['SECRETS_MSC']??null;
        
        
        //switch para uma base serve mas para as 4 base não pois ele vai verificar se existe base de faturamento em apenas uma das opções
        // switch($prop){
            
        //     case $base1:
            //         $contact->baseFaturamentoTitle = 'ENGEPARTS';
            //         $contact->baseFaturamentoSigla = 'EPT';
            //         break;
            //     case $base2:
                //         $contact->baseFaturamentoTitle = 'GAMATERMIC';
                //         $contact->baseFaturamentoSigla = 'GTC';
                //         break;
        //     case $base3:
        //         $contact->baseFaturamentoTitle = 'SEMIN';
        //         $contact->baseFaturamentoSigla = 'SMN';
        //         break;
        //     case $base4:
        //         $contact->baseFaturamentoTitle = 'GSU';
        //         $contact->baseFaturamentoSigla = 'GSU';
        //         break;
        
        // }
        
        // (!empty($contact->baseFaturamento))? $contact->baseFaturamento : $m[] = 'Base de faturamento inexistente';
        $contact->basesFaturamento = $bases;        
        
        
        $tags= [];
        $tag=[];

        if($cliente['Tags']){

            foreach($cliente['Tags'] as $iTag){

                $tag['tag']=$iTag['Tag']['Name'];
                
                $tags[]=$tag;
            }
        }
        $contact->tags = $tags;

        return $contact;
    }

    //compara os arrays e retorna o objeto a ser alterado
    public static function compare($webhook, $ploomesServices)
    {
        //separa old e news do webhook principal, cria o objeto de cada um através do getCLientbyId do ploomes para terem as mesmas propriedades
        $old = self::createOldObj($webhook, $ploomesServices);
        $new = self::createNewObj($webhook, $ploomesServices);
        //converte os objetos em arrays
        $o = (array)$old;
        $n = (array)$new;
        //compara os arrays e devolvea diferença entre eles
        $diferencas = DiverseFunctions::compararArrays($o, $n);

        return $diferencas;
    }

    public static function createOmieObj($webhook)
    {
        //decodifica o json de clientes vindos do webhook
        $json = $webhook['json'];
        $decoded = json_decode($json,true);

        $array = DiverseFunctions::achatarArray($decoded);

        $cliente = new stdClass();
        $cliente->messageId = $array['messageId'];
        $cliente->topic = $array['topic'];
        $cliente->bairro = $array['event_bairro'];
        $cliente->bloqueado = $array['event_bloqueado'];
        $cliente->bloquearFaturamento = $array['event_bloquear_faturamento'];
        $cep = (int)str_replace('-','',$array['event_cep']);
        $cliente->cep = $cep;
        $cliente->cidade = $array['event_cidade'];
        $cliente->cidadeIbge = $array['event_cidade_ibge'];
        $cliente->cnae = $array['event_cnae'];
        $cliente->cnpjCpf = $array['event_cnpj_cpf'];
        $cliente->codigoClienteIntegracao = $array['event_codigo_cliente_integracao'];
        $cliente->codigoClienteOmie = $array['event_codigo_cliente_omie'];
        $cliente->codigoPais = $array['event_codigo_pais'];
        $cliente->complemento = $array['event_complemento'];
        $cliente->contato = $array['event_contato'];
        $cliente->contribuinte = $array['event_contribuinte'];
        $cliente->agencia = $array['event_dadosBancarios_agencia'];
        $cliente->cBanco = $array['event_dadosBancarios_codigo_banco'];
        $cliente->nContaCorrente = $array['event_dadosBancarios_conta_corrente'];
        $cliente->docTitular = $array['event_dadosBancarios_doc_titular'];
        $cliente->nomeTitular = $array['event_dadosBancarios_nome_titular'];
        $cliente->email = $array['event_email'];
        $cliente->endereco = $array['event_endereco'];
        $cliente->enderecoNumero = $array['event_endereco_numero'];
        $cliente->estado = $array['event_estado'];
        $cliente->exterior = $array['event_exterior'];
        $cliente->faxDdd = $array['event_fax_ddd'];
        $cliente->faxNumero = $array['event_fax_numero'];
        $cliente->homepage = $array['event_homepage'];
        $cliente->inativo = $array['event_inativo'];
        $cliente->inscricaoEstadual = $array['event_inscricao_estadual'];
        $cliente->inscricaoMunicipal = $array['event_inscricao_municipal'];
        $cliente->inscricaoSuframa = $array['event_inscricao_suframa'];
        $cliente->logradouro = $array['event_logradouro'];
        $cliente->nif = $array['event_nif'];
        $cliente->nomeFantasia = $array['event_nome_fantasia'];
        $cliente->obsDetalhadas = $array['event_obs_detalhadas'];
        $cliente->observacao = $array['event_observacao'];
        $cliente->simplesNacional = $array['event_optante_simples_nacional'];
        $cliente->pessoaFisica = $array['event_pessoa_fisica'];
        $cliente->produtorRural = $array['event_produtor_rural'];
        $cliente->razaoSocial = $array['event_razao_social'];
        $cliente->recomendacaoAtraso = $array['event_recomendacao_atraso'];
        $cliente->codigoVendedor = $array['event_recomendacoes_codigo_vendedor'];
        $cliente->emailFatura = $array['event_recomendacoes_email_fatura'];
        $cliente->gerarBoletos = $array['event_recomendacoes_gerar_boletos'];
        $cliente->numeroParcelas = $array['event_recomendacoes_numero_parcelas'];
        $cliente->telefoneDdd1 = $array['event_telefone1_ddd'];
        $cliente->telefoneNumero1 = $array['event_telefone1_numero'];
        $cliente->telefoneDdd2 = $array['event_telefone2_ddd'];
        $cliente->telefoneNumero2 = $array['event_telefone2_numero'];
        $cliente->tipoAtividade = $array['event_tipo_atividade'];
        $cliente->limiteCredito = $array['event_valor_limite_credito'];
        $cliente->authorEmail = $array['author_email'];
        $cliente->authorName = $array['author_name'];
        $cliente->authorUserId = $array['author_userId'];
        $cliente->appKey = $array['appKey'];
        $cliente->appHash = $array['appHash'];
        $cliente->origin = $array['origin'];
        
        return $cliente;
    }

}