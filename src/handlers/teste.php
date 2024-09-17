<?php
$idOmie = 6972596007;
$k = 0;
$match = match ($k) {
    0 => 'contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3',
    1 => 'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
    2 => 'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
    3 => 'contact_07784D81-18E1-42DC-9937-AB37434176FB',
};
$codigoOmie = $criaClienteOmie['codigo_cliente_omie'];
$array = [
   
   'TypeId'=>1,
   'OtherProperties'=>[
       [
           'FieldKey'=>$match,
           'StringValue'=>"$idOmie",
       ]
       
   ]

];
                    $json = json_encode($array);
                    print_r($json);
                    exit;