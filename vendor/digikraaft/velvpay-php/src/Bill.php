<?php

namespace Digikraaft\VelvPay;

use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;

class Bill extends ApiResource
{
    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#eabb9752-39c5-42a9-b697-c4995f037225
     *
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function categoryList(): array|object
    {
        return static::staticRequest('GET', "service/bills/category/list");
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#ec3a8c79-f29f-4687-93b4-d675cc36fccd
     *
     * @param array|null $parameters
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function list(string $category = null): array|object
    {
        $url = static::buildQueryString("service/bills/list", [
            'category' => $category
        ]);

        return static::staticRequest('GET', $url);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#b7c446d9-7cef-42d6-b80a-4262f37b100f
     *
     * @param string $billerId
     * @param string $divisionId
     * @param string $productId
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function details(
        string $billerId,
        string $divisionId,
        string $productId,
    ): array|object
    {
        return static::staticRequest('POST', "service/bills/item/details", [
            'billerId' => $billerId,
            'divisionId' => $divisionId,
            'productId' => $productId,
        ]);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#8190fe7b-99ed-4a1a-a64d-19859a7eb781
     *
     * @param string $billerId This is returned from the bill List as `billerid`
     * @param string $amount The amount to pay (in Kobo)
     * @param string $productId This is returned from the bill List as `productId`
     * @param string $paymentItem This is returned from the bill List as `paymentCode`
     * @param string $category
     * @param string $billId electricity meter number, phone number, TV cable id
     * @param string $division This is returned from the bill list as `division`
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function pay(
        string $billerId,
        string $amount,
        string $productId,
        string $paymentItem,
        string $category,
        string $billId,
        string $division,
    ): array|object
    {
        return static::staticRequest('POST', "service/bills/item/pay", [
            'billerId' => $billerId,
            'amount' => $amount,
            'productId' => $productId,
            'paymentItem' => $paymentItem,
            'category' => $category,
            'billId' => $billId,
            'division' => $division,
        ]);
    }

    /**
     *
     * @link https://documenter.getpostman.com/view/36526309/2sA3XY7J6K#e23a0917-6037-4c18-87a2-bf9e3e54826f
     *
     * @param string $reference
     * @return array|object
     * @throws InvalidArgumentException
     */
    public static function status(string $reference): array|object
    {
        $url = static::buildQueryString("service/bills/status", [
            'reference' => $reference
        ]);

        return static::staticRequest('GET', $url);
    }
}
