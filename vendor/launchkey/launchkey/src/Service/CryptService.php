<?php
/**
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2015 LaunchKey, Inc. See project license for usage.
 */

namespace  LaunchKey\SDK\Service;;

/**
 * Interface for providing cryptography services
 *
 * @package LaunchKey\SDK\Service
 */
interface CryptService
{
    /**
     * Encrypt the provided data with an RSA public key
     *
     * @param string $data Data to encrypt
     * @param string $publicKey RSA public key
     * @param bool $base64Encoded Should the encrypted data be Base64 encoded (defaults to true)
     * @return string Encrypted data
     */
    public function encryptRSA($data, $publicKey, $base64Encoded = true);

    /**
     * Decrypt the provided data with an RSA private key
     *
     * @param string $data Data to decrypt
     * @param bool $base64Encoded Is the provided data Base64 encoded (defaults to true)
     * @return string Unencrypted data
     */
    public function decryptRSA($data, $base64Encoded = true);

    /**
     * Decrypt the provided data using AES cryptography with the provided key and IV
     *
     * @param string $data Data to decrypt
     * @param string $key Cipher key used to encrypt the data
     * @param string $iv IV used to encrypt the data
     * @param bool $base64Encoded Is the provided data Base64 encoded (defaults to true)
     * @return string Unencrypted data
     */
    public function decryptAES($data, $key, $iv, $base64Encoded = true);

    /**
     * Create an RSA signature for the provided data
     *
     * @param string $data
     * @param bool $base64Encode Should the signature be Base64 encoded (defaults to true)
     * @return string
     */
    public function sign($data, $base64Encode = true);

    /**
     * Verify that the provided RSA signature is for the provided data
     *
     * @param string $signature RSA signature to verify
     * @param string $data Data the signature is expected to have signed
     * @param string $publicKey RSA public key
     * @param bool $base64Encoded Is the signature Base64 encoded (defaults to true)
     * @return bool Is the signature valid for the data
     */
    public function verifySignature($signature, $data, $publicKey, $base64Encoded = true);
}
