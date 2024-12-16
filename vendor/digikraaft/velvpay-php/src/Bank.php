<?php

namespace Digikraaft\VelvPay;

use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;

class Bank extends ApiResource
{
    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#db479a27-49fa-4df1-818d-14d2fc6e9f51
     *
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function list(): array|object
    {
        return static::staticRequest('GET', "service/general/bank-list");
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#9e0aa89d-c334-4f88-a79a-8cabd4a0a758
     *
     * @param string $bankAccountNumber
     * @param string $bankCode
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function accountDetails(string $bankAccountNumber, string $bankCode): array|object
    {
        $url = static::buildQueryString("service/general/bank-account-detail", [
            'account_number' => $bankAccountNumber,
            'bank_code' => $bankCode,
        ]);

        return static::staticRequest('GET', $url);
    }
}
