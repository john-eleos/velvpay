<?php

namespace Digikraaft\VelvPay;


use Digikraaft\VelvPay\ApiOperations\Request;
use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;
use Digikraaft\VelvPay\Util\Util;

class Payout extends ApiResource
{

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#b1cf7e84-8c70-4e2c-b694-debb5a2232a3
     *
     * @param int|float $amount
     * @param string $bankCode
     * @param string $bankName
     * @param string $accountNumber
     * @param string $accountName
     * @param string $description
     * @param int $transactionPin
     * @param string|null $idempotencykey
     * @param bool $isNaira
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function transfer(
        int|float $amount,
        string $bankCode,
        string $bankName,
        string $accountNumber,
        string $accountName,
        string $description,
        int $transactionPin,
        string $idempotencykey = null,
        bool $isNaira = false,
    ): array|object
    {
        Request::$idempotencyKey = $idempotencykey ?? Util::generateUniqueReference();

        return static::staticRequest('POST', "service/payment/payout", [
            'amount' => $amount,
            'bankCode' => $bankCode,
            'bankName' => $bankName,
            'accountNumber' => $accountNumber,
            'accountName' => $accountName,
            'description' => $description,
            'pin' => $transactionPin,
            'isNaira' => $isNaira,
        ]);
    }

    /**
     * @return array|object
     * @throws InvalidArgumentException
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#ec93a4f4-6567-4557-b773-8938627afac5
     */
    public static function balance(): object|array
    {
        return static::staticRequest('GET', "service/payment/payout-balance");
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#d0d9d593-69f4-41ad-9c9b-6227fb8257de
     *
     * @param string $transactionId
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function resolveStatus(string $transactionId): array|object
    {
        $url = static::buildQueryString("service/payment/collection/transaction/resolve", [
            'transaction_id' => $transactionId,
        ]);

        return static::staticRequest('GET', $url);
    }
}
