<?php

namespace src\services;

use src\contracts\PloomesManagerInterface;

class PloomesServices implements PloomesManagerInterface{

    private $baseApi;
    private $apiKey;
    private $method;
    private $headers;

    public function __construct(){
        $this->apiKey = $_ENV['API_KEY'];
        $this->baseApi = $_ENV['BASE_API'];
        $this->method = array('get','post','patch','update');
        $this->headers = [
            'User-Key:' . $this->apiKey,
            'Content-Type: application/json',
        ];
    }

    //ENCONTRA A PROPOSTA NO PLOOMES
    public function requestQuote(object $deal):array|null
    {
        /**
         * Quotes?$expand=Installments,OtherProperties,Products($select=Id,Discount),Approvals($select=Id),ExternalComments($select=Id),Comments($select=Id),Template,Deal($expand=Pipeline($expand=Icon,Gender,WinButton,WinVerb,LoseButton,LoseVerb),Stage,Contact($expand=Phones;$select=Name,TypeId,Phones),Person($expand=Phones;$select=Name,TypeId,Phones),OtherProperties),Pages&$filter=Id+eq+'.$deal->lastQuoteId.'&preload=true
         */
        $query = 'Quotes?$expand=Installments,OtherProperties,Products($select=Id,ProductId,ProductName,Quantity,Discount,UnitPrice,Total,Ordination),Products($expand=Product($select=Code,MeasurementUnit))&$filter=Id+eq+'.$deal->lastQuoteId.'&preload=true';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $quote = json_decode($response, true);     

        return $quote['value'][0];

    }

    //ENCONTRA O CNPJ DO CLIENTE NO PLOOMES
    public function contactCnpj(object $deal):string
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Contacts?$filter=Id+eq+' . $deal->contactId . '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $responseCnpj = curl_exec($curl);

        curl_close($curl);

        $responseCnpj = json_decode($responseCnpj, true);

        $response = (!empty($responseCnpj['value'][0]['CNPJ'])) ? $responseCnpj['value'][0]['CNPJ'] : false;
       
        return $response;
    }

    //ENCONTRA O EMAIL DO VENDEDOR NO PLOOMES
    public function ownerMail(object $deal):string
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Users?$filter=Id+eq+' . $deal->ownerId . '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $responseMail = curl_exec($curl);

        curl_close($curl);

        $responseMail = json_decode($responseMail, true);

        $response = $responseMail['value'][0]['Email'] ?? false;
        
        return $response;
    }

    //encontra a venda no ploomes
    public function requestOrder(object $deal):array|null
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Orders?$filter=Id+eq+' . $deal->lastOrderId . '&$expand=OtherProperties,Products($select=Product,Discount,Quantity,UnitPrice,Id;$expand=OtherProperties,Parts($expand=Product($select=Code,Id)),Product($expand=OtherProperties($filter=FieldId+eq+40232989)))',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method['0']),
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);
        
        curl_close($curl);
        $order = (empty($response['value'][0])) ? Null : $response['value'][0]; 
        
        return $order;
      
    }

    //CRIA INTERAÇÃO NO PLOOMES
    public function createPloomesIteraction(string $json):bool
    {

        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . '/InteractionRecords',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[1]),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);
        curl_close($curl);

        $idIntegration = $response['value'][0]['Id']??Null;

        return ($idIntegration !== null)?true:false;
       
    }
    //encontra cliente no ploomes pelo CNPJ
    public function consultaClientePloomesCnpj(string $cnpj):string{

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'/Contacts?$filter=CNPJ+eq+'."'$cnpj'",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);

        return $response['value'][0]['Id'];

    }
    //ALTERA O ESTÁGIO DA VENDA NO PLOOMES
    public function alterStageOrder($stage, $orderId)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Orders('.$orderId.')',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[2]),
            CURLOPT_POSTFIELDS =>$stage,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);
        $stage = json_decode($stage,true);
        curl_close($curl);

       return ($response['value'][0]['StageId'] === $stage['StageId']) ? true :  false;
    }


 //encontra cliente no ploomes pelo CNPJ
    public function getClientById(string $id):array|null{

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'/Contacts?$filter=Id+eq+'.$id.'&$expand=OtherProperties,City,State,Country,Owner($select=Id,Name,Email,Phone),Tags($expand=Tag),Phones($expand=Type),LineOfBusiness',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);

        return $response['value'][0];

    }

}