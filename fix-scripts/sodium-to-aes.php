#!/usr/bin/env php
<?php

// Only run from the command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

// Require necessary dependencies and configure your application
require_once(__DIR__ . "/../../bootstrap.php");

use MysqliDb;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

function decrypt($encrypted, $key): string|null
{
    try {
        $decoded = sodium_base642bin((string) $encrypted, SODIUM_BASE64_VARIANT_URLSAFE);
        if (empty($decoded)) {
            throw new SystemException('The message encoding failed');
        }
        if (mb_strlen($decoded, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
            throw new SystemException('The message was truncated');
        }
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $plain = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $key
        );
        if ($plain === false) {
            throw new SystemException('The message was tampered with in transit');
        }
        sodium_memzero($ciphertext);
        sodium_memzero($key);
        return $plain;
    } catch (SodiumException | SystemException $e) {
        return $encrypted;
    }
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

// Define your encryption key
$encryptionKey = 'your_aes_encryption_key';

$oldSodiumKey = "";

// Define the name of the table and columns you want to migrate
$tableName = 'form_vl';
$columnsToMigrate = ['is_encrypted', 'patient_art_no', 'patient_first_name', 'patient_middle_name', 'patient_last_name'];

// Query the database to retrieve the data
$data = $db->get($tableName, null, $columnsToMigrate);



// Loop through the data and migrate encrypted entries
foreach ($data as $row) {
    $isEncrypted = $row['is_encrypted'];
    $patientArtNo = $row['patient_art_no'];
    $patientFirstName = $row['patient_first_name'];
    $patientMiddleName = $row['patient_middle_name'];
    $patientLastName = $row['patient_last_name'];

    if ($isEncrypted === 'yes') {
        // Decrypt the data using the old sodium decryption method
        $decryptedArtNo = decrypt($patientArtNo, $oldSodiumKey);
        $decryptedFirstName = decrypt($patientFirstName, $oldSodiumKey);
        $decryptedMiddleName = decrypt($patientMiddleName, $oldSodiumKey);
        $decryptedLastName = decrypt($patientLastName, $oldSodiumKey);

        // Encrypt the data using the new AES encryption method
        $encryptedArtNo = CommonService::encrypt($decryptedArtNo, $encryptionKey);
        $encryptedFirstName = CommonService::encrypt($decryptedFirstName, $encryptionKey);
        $encryptedMiddleName = CommonService::encrypt($decryptedMiddleName, $encryptionKey);
        $encryptedLastName = CommonService::encrypt($decryptedLastName, $encryptionKey);

        // Update the database with the new encrypted values
        $db->where('id', $row['id']); // Assuming there is an 'id' column as the primary key
        $db->update($tableName, [
            'patient_art_no' => $encryptedArtNo,
            'patient_first_name' => $encryptedFirstName,
            'patient_middle_name' => $encryptedMiddleName,
            'patient_last_name' => $encryptedLastName,
        ]);
    }
}

// Output a message indicating the migration is complete
echo "Data migration from Sodium to AES encryption completed.\n";
