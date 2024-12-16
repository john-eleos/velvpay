<?php

namespace Digikraaft\VelvPay;

use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;

class CashCraft extends ApiResource
{

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#f375fda3-5769-42b6-96d1-f651c804a4d9
     *
     * @param string $transactionId
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function details(string $transactionId): array|object
    {
        $url = static::buildQueryString("service/payment/cash-craft/details", [
            'transaction_id' => $transactionId
        ]);

        return static::staticRequest('GET', $url);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#c76e6711-01b6-435d-b289-10b8578c044b
     *
     * @param string $transactionId
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function manualResolve(string $transactionId): array|object
    {
        $url = static::buildQueryString("service/payment/cash-craft/resolve", [
            'txId' => $transactionId
        ]);

        return static::staticRequest('GET', $url);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#9b4095d7-a4c6-4172-89ef-233b3c69ba82
     *
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function confirmFee(): array|object
    {
        return static::staticRequest('POST', "service/payment/cash-craft/fee");
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#8190fe7b-99ed-4a1a-a64d-19859a7eb781
     *
     * @param string $amount The amount to pay (in Kobo (default) or Naira (isNaira must be set to true))
     * @param string $email
     * @param array $beneficiaries each array item must include bankCode, accountNumber and amount.
     * @param string $description
     * @param int $validityTime
     * @param bool $isNaira
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function initiate(
        string $amount,
        string $email,
        array $beneficiaries,
        string $description,
        int $validityTime = 20,
        bool $isNaira = false,
    ): array|object
    {
        return static::staticRequest('POST', "service/payment/cash-craft/initiate", [
            'amount' => $amount,
            'email' => $email,
            'beneficiaries' => $beneficiaries,
            'description' => $description,
            'validityTime' => $validityTime,
            'isNaira' => $isNaira,
        ]);
    }
}
