<?php
namespace src\factories;

use Exception;
use src\contracts\CrmFormattersInterface;
use src\formatters\PloomesFormatter;

class CrmFormatterFactory
{
    public static function create($crm): CrmFormattersInterface
    {
        return match (strtolower($crm)) {
      
            'ploomes'=>new PloomesFormatter(),
            default => throw new Exception("CRM {$crm} não suportado")
        };
    }
}
