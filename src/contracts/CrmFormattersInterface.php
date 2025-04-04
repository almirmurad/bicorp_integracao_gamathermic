<?php
namespace src\contracts;

interface CrmFormattersInterface
{
    
    
    public function createPloomesContactFromErpObject(object $contact, object $ploomesServices): string;
    public function createContactObjFromPloomesCrm(string $json, object $ploomesServices):object;
    public function updateContactCRMToERP(object $contact, object $ploomesServices):array;
    public function createContact(object $contact, object $ploomesServices):array;
    public function createContactERP(string $json, object $ploomesServices):array;
    public function updateContactERP(string $json, object $contact, object $ploomesServices):array;
}