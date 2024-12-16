<?php

namespace Digikraaft\VelvPay;


use Digikraaft\VelvPay\ApiOperations\Request;
use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;
use Digikraaft\VelvPay\Util\Util;

class Wallet extends ApiResource
{

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#bbcee9a1-9cf1-466b-b012-a68a35729265
     *
     * @param string $email
     * @param string $bvn
     * @param string $username
     * @param string $gender
     * @param string $dateOfBirth
     * @param bool $referralCode
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function create(
        string $email,
        string $bvn,
        string $username,
        string $gender,
        string $dateOfBirth,
        bool $referralCode = false,
    ): array|object
    {
        return static::staticRequest('POST', "service/wallet/create", [
            'email' => $email,
            'bvn' => $bvn,
            'username' => $username,
            'gender' => $gender,
            'dob' => $dateOfBirth,
            'referral_code' => $referralCode,
        ]);
    }


    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#08f13e78-d19c-4dfe-b0c1-d60bfb086546
     *
     * @param array|null $parameters
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function transactions(array $parameters = null): array|object
    {
        $url = static::buildQueryString("service/wallet/transactions", $parameters);

        return static::staticRequest('GET', $url);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#afe14ec7-c7af-4887-b61c-7eaad6902cef
     *
     * @param string $accountId
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function accountDetails(string $accountId): array|object
    {
        $url = static::buildQueryString("service/wallet/details", [
            'account_id' => $accountId,
        ]);

        return static::staticRequest('GET', $url);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#f0d342a3-b903-413c-9029-729dbddb2d45
     *
     * @param array|null $parameters
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function customers(array $parameters = null): array|object
    {
        return static::staticRequest('GET', "service/wallet/customers", $parameters);
    }
}
