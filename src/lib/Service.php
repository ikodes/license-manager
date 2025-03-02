<?php 
/**
 * ==============================================================
 * WARNING: DO NOT MODIFY THIS FILE WITHOUT PROPER UNDERSTANDING!
 * ==============================================================
 * 
 * This file is a core part of the system, and any modifications 
 * may cause unexpected behavior, security vulnerabilities, or 
 * complete system failure.
 * 
 * If you need to make changes, please consult the development 
 * team or refer to the official documentation.
 * 
 * Changes made without proper understanding may lead to:
 * - System instability or crashes.
 * - Security vulnerabilities.
 * - Compatibility issues with future updates.
 * - Loss of critical functionality.
 * 
 * Proceed with caution and ensure you have a backup before making 
 * any modifications.
 * 
 * ==============================================================
 * LAST MODIFIED: 27 02 2025
 * AUTHOR: ikodes team
 * ==============================================================
 */
class Service
{   

    private $aesKey;

    public function __construct(string $aesKey)
    {
        $this->aesKey = $aesKey;
    }

    public function encryptData(string $plainText): string
    {
        try {
            $iv = random_bytes(openssl_cipher_iv_length('aes-256-gcm'));
            $tag = '';
            $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $this->aesKey, 0, $iv, $tag);
            if ($cipherText === false) {
                throw new Exception('Encryption failed');
            }
            return base64_encode(json_encode([
                'cipher' => $cipherText,
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag)
            ]));
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}
?>