<?php

namespace Digikraaft\VelvPay\Util;


use stdClass;

abstract class Util
{
    private static ?bool $isMbstringAvailable = null;


    public static function utf8($value): string
    {
        if (null === self::$isMbstringAvailable) {
            self::$isMbstringAvailable = function_exists('mb_detect_encoding');

            if (! self::$isMbstringAvailable) {
                trigger_error('It looks like the mbstring extension is not enabled. '.
                    'UTF-8 strings will not properly be encoded. Ask your system '.
                    'administrator to enable the mbstring extension.', E_USER_WARNING);
            }
        }

        if (is_string($value) && self::$isMbstringAvailable && 'UTF-8' !== mb_detect_encoding($value, 'UTF-8', true)) {
            return utf8_encode($value);
        }

        return $value;
    }

    /**
     * Converts a response from the VelvPay API to the corresponding PHP object.
     */
    public static function convertArrayToObject(array $resp): array|object
    {
        $object = new stdClass();

        return self::arrayToObject($resp, $object);
    }

    private static function arrayToObject($array, &$obj): object
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $obj->$key = new stdClass();
                self::arrayToObject($value, $obj->$key);
            } else {
                $obj->$key = $value;
            }
        }

        return $obj;
    }

    /**
     * @throws \Exception
     */
    public static function generateUniqueReference(string $prefix = null): string
    {
        $randomString = static::randomString(12);
        return $prefix ? $prefix . "_" . $randomString : $randomString;
    }

    /**
     * @throws \Exception
     */
    private static function randomString($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;

        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }

        return $str;
    }

    public static function cryptoJsAesDecrypt($data, $key): bool|string
    {
        $data = base64_decode($data);

        if (substr($data, 0, 8) != "Salted__") {
            return false;
        }

        $salt = substr($data, 8, 8);

        $keyAndIV = static::aesEvpKDF($key, $salt);

        return openssl_decrypt(
            substr($data, 16),
            "aes-256-cbc",
            $keyAndIV["key"],
            OPENSSL_RAW_DATA,
            $keyAndIV["iv"]
        );
    }

    static function cryptoJsAesEncrypt($data, $key): string
    {
        $salted = "Salted__";
        $salt = openssl_random_pseudo_bytes(8);

        $keyAndIV = static::aesEvpKDF($key, $salt);
        $encrypt  = openssl_encrypt(
            $data,
            "aes-256-cbc",
            $keyAndIV["key"],
            OPENSSL_RAW_DATA,
            $keyAndIV["iv"]
        );

        return  base64_encode($salted . $salt . $encrypt);
    }

    private static function aesEvpKDF($password, $salt, $keySize = 8, $ivSize = 4, $iterations = 1, $hashAlgorithm = "md5"): array
    {
        $targetKeySize = $keySize + $ivSize;
        $derivedBytes = "";
        $numberOfDerivedWords = 0;
        $block = NULL;
        $hasher = hash_init($hashAlgorithm);

        while ($numberOfDerivedWords < $targetKeySize) {
            if ($block != NULL) {
                hash_update($hasher, $block);
            }
            hash_update($hasher, $password);
            hash_update($hasher, $salt);
            $block = hash_final($hasher, TRUE);
            $hasher = hash_init($hashAlgorithm);

            // Iterations
            for ($i = 1; $i < $iterations; $i++) {
                hash_update($hasher, $block);
                $block = hash_final($hasher, TRUE);
                $hasher = hash_init($hashAlgorithm);
            }

            $derivedBytes .= substr($block, 0, min(strlen($block), ($targetKeySize - $numberOfDerivedWords) * 4));

            $numberOfDerivedWords += strlen($block) / 4;
        }

        return [
            "key" => substr($derivedBytes, 0, $keySize * 4),
            "iv"  => substr($derivedBytes, $keySize * 4, $ivSize * 4)
        ];
    }
}
