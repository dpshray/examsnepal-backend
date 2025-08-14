<?php

namespace App\Services;

class ConnectIPSService
{
    public function initiateTransaction(array $data){
        $pfxPath = storage_path('certs/CREDITOR.pfx'); // or wherever you store it
        $pfxPassword = '123';

        if (!file_exists($pfxPath)) {
            throw new \Exception("PFX file not found at: $pfxPath");
        }
        $pfxContent = file_get_contents($pfxPath);
        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPassword)) {
            throw new \Exception("Failed to read PFX file");
        }

        $privateKey = $certs['pkey']; // Private key
        // dd($privateKey);
    }
}
