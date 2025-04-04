<?php
namespace src\factories;

use Exception;
use src\contracts\ErpFormattersInterface;
use src\formatters\NasajonFormatter;

class ErpFormatterFactory
{
    public static function create($erp): ErpFormattersInterface
    {
        return match (strtolower($erp)) {
            // 'omie' => new OmieFormatter($appk, $omieBases),
            //'senior' => new SeniorFormatter(),
            'nasajon'=>new NasajonFormatter(),
            default => throw new Exception("ERP {$erp} não suportado")
        };
    }
}
