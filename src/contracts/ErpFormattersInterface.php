<?php
namespace src\contracts;

interface ErpFormattersInterface
{
    public function createOrder(object $orderData, object $credentials): string;
    public function createObjectErpClientFromCrmData(string $json, object $ploomesServices):object;

    public function createClientErpToCrmObj(array $clientData): object;
    public function createPloomesContactFromErpObject(object $contact, object $ploomesServices): string;
    public function updateContactCRMToERP(object $contact):array;
    public function createContactCrmToERP(object $contact):array;
    public function createContactERP(string $json, object $ploomesServices):array;
    public function updateContactERP(string $json, object $contact, object $ploomesServices):array;

}