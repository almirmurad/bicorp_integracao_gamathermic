<?php

namespace src\functions;

class DiverseFunctions{

    //CONVERTE PARA DATA EM PORTUGUÊS
    public static function convertDate($date)
    {

        $dateIni = explode('T', $date);
        $datePt = explode('-', $dateIni[0]);
        $datePtFn = implode("/", array_reverse($datePt)); // . " às " . $dateIni[1];

        return $datePtFn;
    }

    public static function convertDateHora($date)
    {

        $dateIni = explode('T', $date);
        $datePt = explode('-', $dateIni[0]);
        $datePtFn = implode("/", array_reverse($datePt)) . " às " . $dateIni[1];

        return $datePtFn;
    }

    public static function limpa_cpf_cnpj($valor){
        $valor = trim($valor);
        $valor = str_replace(array('.','-','/'), "", $valor);
        return $valor;
       }

    public static function calculaParcelas($dataInicio,$totalParcelas,$totalPedido)
    {
        
        $intervalo = explode('/',$totalParcelas);
        $nParcelas = count($intervalo);
        $valorParcela = round($totalPedido / $nParcelas,2);
        $percentual = round(($valorParcela / $totalPedido)*100,2);
        $somaParcelas = 0;
        $somaPercentuais = 0;
       
        for ($i = 0; $i < $nParcelas; $i++) {
            
            $somaPercentuais += $percentual;
            $somaParcelas += $valorParcela;
            $dataVencimento = date('d/m/Y',strtotime("+ $intervalo[$i] day", strtotime($dataInicio)));
            
            $parcela[] = [
                "data_vencimento" => $dataVencimento,
                "numero_parcela" => $i + 1,
                "percentual" => $percentual,
                "valor" => $valorParcela,
            ];
            
        }
        // Ajustar o primeiro percentual para garantir que a soma seja 100%
        $diferenca = 100 - $somaPercentuais;
        $parcela[0]['percentual'] += $diferenca;
        // Ajustar o primeiro valor parcela para garantir que a soma das parcelas seja o total do pedido
        $diferencaParcela = $totalPedido - $somaParcelas;
        $parcela[0]['valor'] += $diferencaParcela;

        return $parcela;
    
    }

    public static function getIdParcelamento ($parcelamento)
    {
        if($parcelamento == "0"){
            $parcelamento = 'a vista';
        }
        $intervalo = [
            'a vista'=>'000',
            '14'=>'A14',
            '14/21/28/35/42'=>'S34',
            '14/21/28/35/42/49'=>'Z61',
            '14/21/28/35/42/49/56'=>'U33',
            '21'=>'A21',
            '21/28'=>'S26',
            '21/28/35'=>'S03',
            '21/28/35/42'=>'S04',       
            '21/28/35/42/49'=>'T02',  
            '28'=>'A28',      
            '28/35'=>'S13',       
            '28/35/42'=>'S05',      
            '28/42/49'=>'U53',       
            '7'=>'A07',
            '7/14'=>'S20',
        ];
        return $intervalo[$parcelamento];       
    }

}