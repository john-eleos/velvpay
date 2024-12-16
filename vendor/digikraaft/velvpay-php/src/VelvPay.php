<?php

namespace Digikraaft\VelvPay;

use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;
use Digikraaft\VelvPay\Util\Util;

class VelvPay
{
    /** @var string The VelvPay Secret key. */
    public static string $secretKey;

    /** @var string The VelvPay Public key. */
    public static string $publicKey;

    /** @var string The VelvPay Encryption key. */
    public static string $encryptionKey;

    /** @var string The VelvPay Request reference. */
    public static string $requestReference;


    /** @var string The base URL for the VelvPay API. */
    public static string $apiBase = 'https://api.velvpay.com/api/v1';

    /**
     * @return string|null
     */
    public static function getSecretKey(): ?string
    {
        return static::$secretKey;
    }

    /**
     * @return string|null
     */
    public static function getPublicKey(): ?string
    {
        return static::$publicKey;
    }

    /**
     * @return string|null
     */
    public static function getEncryptionKey(): ?string
    {
        return static::$encryptionKey;
    }

    /**
     * @return string|null
     */
    public static function getRequestReference(): ?string
    {
        static::$requestReference = static::$requestReference ?? Util::generateUniqueReference("REQ");
        return static::$requestReference;
    }

    /**
     * Sets the Secret key.
     *
     * @throws InvalidArgumentException
     */
    public static function setSecretKey(string $secretKey): void
    {
        static::validateKey($secretKey);
        static::$secretKey = $secretKey;
    }

    /**
     * Sets the Public key.
     *
     * @throws InvalidArgumentException
     */
    public static function setPublicKey(string $publicKey): void
    {
        static::validateKey($publicKey);
        static::$publicKey = $publicKey;
    }

    /**
     * Sets the Encryption key.
     *
     * @throws InvalidArgumentException
     */
    public static function setEncryptionKey(string $encryptionKey): void
    {
        static::validateKey($encryptionKey);
        static::$encryptionKey = $encryptionKey;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function setKeys(string $secretKey, string $publicKey, string $encryptionKey): void
    {
        static::setSecretKey($secretKey);
        static::setPublicKey($publicKey);
        static::setEncryptionKey($encryptionKey);
    }

    /**
     * Sets the Encryption key.
     */
    public static function setRequestReference(string $requestReference): void
    {
        static::$requestReference = $requestReference;
    }


    /**
     * @throws InvalidArgumentException
     */
    private static function validateKey($apiKey): void
    {
        if ($apiKey == '' || ! is_string($apiKey)) {
            throw new InvalidArgumentException('Api key must be a string and cannot be empty');
        }
    }

    private static function authorization(): string
    {
        return static::getSecretKey().static::getPublicKey().static::getRequestReference();
    }

    public static function authorizationToken(): string
    {
        $token = static::authorization();
        return Util::cryptoJsAesEncrypt($token, static::getEncryptionKey());
    }
}
