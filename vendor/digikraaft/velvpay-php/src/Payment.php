<?php

namespace Digikraaft\VelvPay;


use Digikraaft\VelvPay\ApiOperations\Request;
use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;
use Digikraaft\VelvPay\Util\Util;

class Payment extends ApiResource
{
    const USER_TO_BUSINESS_FUND_REQUEST = 'user-to-business';
    const USER_TO_USER_FUND_REQUEST = 'user-to-user';

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#f0fac5e0-feac-487f-a61e-2530c804c9fa
     *
     * @param int|float $amount
     * @param string $customerEmail
     * @param int $validityTime
     * @param bool $isNaira
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function createVirtualAccount(
        int|float $amount,
        string $customerEmail,
        int $validityTime = 20,
        bool $isNaira = false,
    ): array|object
    {
        return static::staticRequest('POST', "service/payment/collection/transaction", [
            'amount' => $amount,
            'email' => $customerEmail,
            'isNaira' => $isNaira,
            'validityTime' => $validityTime,
        ]);
    }



    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#b367895b-9365-42aa-b3fb-7e1323c6c0a6
     *
     * @param int|float $amount
     * @param bool $isNaira
     * @param string $title
     * @param string $description
     * @param bool $chargeCustomer
     * @param string $postPaymentInstructions
     * @param string $redirectUrl
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function initiatePayment(
        int|float $amount,
        bool $isNaira = false,
        string $title,
        string $description,
        string $redirectUrl,
        bool $chargeCustomer = false,
        string $postPaymentInstructions
    ): array|object
    {
        return static::staticRequest('POST', "service/payment/initiate", [
            'amount' => $amount,
            'isNaira' => $isNaira,
            'title' => $title,
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'chargeCustomer' => $chargeCustomer,
            'postPaymentInstructions' => $postPaymentInstructions,
        ]);
    }





    /**
     * @param array $parameters
     * @return array|object
     * @throws InvalidArgumentException
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#7cba57ca-17f8-404f-816c-80ea68f50679
     */
    public static function details(array $parameters): object|array
    {
        $url = static::buildQueryString("service/payment/collection/transaction/details", $parameters);
        return static::staticRequest('GET', $url);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#874ad716-554b-4d67-b1fa-005d1fbb77c7
     *
     * @param int|float $amount
     * @param string $type
     * @param string $senderEmail
     * @param string $receiverEmail
     * @param string $paymentName
     * @param string $description
     * @param array|null $metadata
     * @param array|null $idempotencykey
     * @param bool $isNaira
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function requestFund(
        int|float $amount,
        string $type,
        string $senderEmail,
        string $receiverEmail,
        string $paymentName,
        string $description,
        array $metadata = null,
        string $idempotencykey = null,
        bool $isNaira = false,
    ): array|object
    {

        Request::$idempotencyKey = $idempotencykey ?? Util::generateUniqueReference();

        return static::staticRequest('POST', "service/request/fund", [
            'amount' => $amount,
            'type' => $type,
            'senderEmail' => $senderEmail,
            'receiverEmail' => $receiverEmail,
            'paymentName' => $paymentName,
            'description' => $description,
            'metadata' => $metadata,
            'isNaira' => $isNaira,
        ]);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#39e08ea0-392c-47e5-9b7a-e9a9f3e9b3ce
     *
     * @param string $reference
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function requestFundStatus(string $reference): array|object
    {
        return static::staticRequest('POST', "service/request/fund/status", [
            'reference' => $reference,
        ]);
    }
}
