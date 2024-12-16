<?php

namespace Digikraaft\VelvPay\ApiOperations;

use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;
use Digikraaft\VelvPay\Util\Util;
use Digikraaft\VelvPay\VelvPay;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Trait for resources that need to make API requests.
 */
trait Request
{

    protected static $client;
    protected static mixed $response;

    protected static string $idempotencyKey;


    public static function validateParams(mixed $params = null, bool $required = false): void
    {
        if ($required) {
            if (empty($params) || ! is_array($params)) {
                $message = 'The parameter passed must be an array and must not be empty';

                throw new InvalidArgumentException($message);
            }
        }
        if ($params && ! is_array($params)) {
            $message = 'The parameter passed must be an array';

            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function staticRequest(string $method, string $url, array $params = [], string $returnType = 'obj'): array|object
    {
        if ($returnType != 'arr' && $returnType != 'obj') {
            throw new InvalidArgumentException('Return type can only be obj or arr');
        }
        static::setHttpResponse($method, $url, $params);

        if ($returnType == 'arr') {
            return static::getResponseData();
        }

        return Util::convertArrayToObject(static::getResponse());
    }

    /**
     * Set options for making the Client request.
     */
    protected static function setRequestOptions(): void
    {
        $encryptedAuthToken = VelvPay::authorizationToken();

        static::$client = new Client(
            [
                'base_uri' => VelvPay::$apiBase,
                'headers' => [
                    'Authorization' => "Bearer $encryptedAuthToken",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'api-key' => $encryptedAuthToken,
                    'idempotencykey' => static::$idempotencyKey ?? Util::generateUniqueReference("IDK"),
                    'reference-id' => VelvPay::getRequestReference(),
                    'public-key' => VelvPay::getPublicKey(),
                ],
            ]
        );
    }


    private static function setHttpResponse(string $method, string $url, array $body = []): \GuzzleHttp\Psr7\Response
    {

        static::setRequestOptions();

        static::$response = static::$client->{strtolower($method)}(
            VelvPay::$apiBase.'/'.$url,
            ['body' => json_encode($body)]
        );

        return static::$response;
    }


    private static function getResponse(): array
    {
        return json_decode(static::$response->getBody(), true);
    }


    private static function getResponseData(): array
    {
        return json_decode(static::$response->getBody(), true)['data'];
    }

    public static function getIdempotencyKey(): ?string
    {
        return static::$idempotencyKey ?? null;
    }
}
