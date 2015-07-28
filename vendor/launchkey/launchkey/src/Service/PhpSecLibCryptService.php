<?php
/**
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2015 LaunchKey, Inc. See project license for usage.
 */

namespace LaunchKey\SDK\Service;


use Psr\Log\LoggerInterface;

/**
 * CryptService implementation utilizing phpseclib for cryptography
 *
 * @package LaunchKey\SDK\Service
 */
class PhpSecLibCryptService implements CryptService
{
    /**
     * @var \Crypt_RSA
     */
    private $crypt;

    /**
     * @param $privateKey Private key string
     * @param string|null $password Password for private key
     */
    public function __construct($privateKey, $password = null)
    {
        $this->crypt = $this->getRsaCrypt($privateKey, $password);
        $this->privateKeySigner = $this->getSignor($privateKey, $password);
    }

    /**
     * Encrypt the provided data with an RSA public key
     *
     * @param string $data Data to encrypt
     * @param string $publicKey RSA public key
     * @param bool $base64Encode Should the encrypted data be Base64 encoded (defaults to true)
     * @return string Encrypted data
     */
    public function encryptRSA($data, $publicKey, $base64Encode = true)
    {
        $encrypted = $this->getRsaCrypt($publicKey)->encrypt($data);
        $encrypted = $base64Encode ? base64_encode($encrypted) : $encrypted;
        return $encrypted;
    }

    /**
     * Decrypt the provided data with an RSA private key
     *
     * @param string $data Data to decrypt
     * @param bool $base64Encoded Is the provided data Base64 encoded (defaults to true)
     * @return string Unencrypted data
     */
    public function decryptRSA($data, $base64Encoded = true)
    {
        $data = $base64Encoded ? base64_decode($data) : $data;
        $decrypted =  $this->crypt->decrypt($data);
        return $decrypted;
    }

    /**
     * Decrypt the provided data using AES cryptography with the provided key and IV
     *
     * @param string $data Data to decrypt
     * @param string $key Cipher key used to encrypt the data
     * @param string $iv IV used to encrypt the data
     * @param bool $base64Encoded Is the provided data Base64 encoded (defaults to true)
     * @return string Unencrypted data
     */
    public function decryptAES($data, $key, $iv, $base64Encoded = true)
    {
        $data = $base64Encoded ? base64_decode($data) : $data;
        $cipher = new \Crypt_AES();
        $cipher->setKey($key);
        $cipher->setIV($iv);
        $cipher->disablePadding();
        $decrypted = rtrim($cipher->decrypt($data));
        return $decrypted;
    }

    /**
     * Create an RSA signature for the provided data
     *
     * @param string $data
     * @param bool $base64Encode Should the signature be Base64 encoded (defaults to true)
     * @return string
     */
    public function sign($data, $base64Encode = true)
    {
        $signature = $this->privateKeySigner->sign($data);
        $signature = $base64Encode ? base64_encode($signature) : $signature;
        return $signature;
    }

    /**
     * Verify that the provided RSA signature is for the provided data
     *
     * @param string $signature RSA signature to verify
     * @param string $data Data the signature is expected to have signed
     * @param string $publicKey RSA public key
     * @param bool $base64Encoded Is the signature Base64 encoded (defaults to true)
     * @return bool Is the signature valid for the data
     */
    public function verifySignature($signature, $data, $publicKey, $base64Encoded = true)
    {
        $signature =  $base64Encoded ? base64_decode($signature) : $signature;
        return $this->getSignor($publicKey)->verify($data, $signature);
    }

    /**
     * @param $rsaKey
     * @return \Crypt_RSA
     */
    private function getSignor($rsaKey, $password = null)
    {
        $crypt = new \Crypt_RSA();
        $crypt->loadKey($rsaKey);
        $crypt->setPassword($password);
        $crypt->setHash('sha256');
        $crypt->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
        return $crypt;
    }

    /**
     * @param $privateKey
     * @param $password
     * @return \Crypt_RSA
     */
    private function getRsaCrypt($privateKey, $password = null)
    {
        $crypt = new \Crypt_RSA();
        $crypt->loadKey($privateKey);
        $crypt->setPassword($password);
        return $crypt;
    }
}
