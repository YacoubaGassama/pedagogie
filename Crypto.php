<?php

class Crypto {
    private string $key;
    private string $cipher = 'AES-128-CTR';

    public function __construct() {
        $this->key = 'criat@2025'; // Clé de chiffrement (doit être de 16, 24 ou 32 bytes pour AES)
        if (!$this->key) {
            throw new RuntimeException('Clé de chiffrement manquante.');
        }
    }

    public function encrypt(string $data): string {
        $ivlen          = openssl_cipher_iv_length($this->cipher);
        $iv             = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        $hmac           = hash_hmac('sha256', $ciphertext_raw, $this->key, true);

        return $this->base64url_encode(base64_encode($iv . $hmac . $ciphertext_raw));
    }

    public function decrypt(string $encrypted): string|false {
        $decoded        = base64_decode($this->base64url_decode($encrypted));
        $ivlen          = openssl_cipher_iv_length($this->cipher);
        $hmaclen        = 32; // SHA256 = 32 bytes

        $iv             = substr($decoded, 0, $ivlen);
        $hmac           = substr($decoded, $ivlen, $hmaclen);
        $ciphertext_raw = substr($decoded, $ivlen + $hmaclen);

        // Vérification de l'intégrité
        $expected_hmac = hash_hmac('sha256', $ciphertext_raw, $this->key, true);
        if (!hash_equals($hmac, $expected_hmac)) {
            return false; // Données altérées ou clé incorrecte
        }

        return openssl_decrypt($ciphertext_raw, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
    }

    private function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode(string $data): string {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}