<?php

namespace Digikraaft\VelvPay;


use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;

class ApiResource
{

    use ApiOperations\Request;


    public static function baseUrl(): string
    {
        return VelvPay::$apiBase;
    }

    public static function endPointUrl(string $slug): string
    {
        return Util\Util::utf8($slug);
    }


    public static function buildQueryString(string $slug, array $params = null): string
    {
        $url = self::endPointUrl($slug);

        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $url;
    }
}
