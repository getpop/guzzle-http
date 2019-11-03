<?php
namespace PoP\GuzzleHelpers;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use PoP\ComponentModel\Error;
use GuzzleHttp\RequestOptions;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use PoP\Translation\Facades\TranslationAPIFacade;

class GuzzleHelpers
{
    /**
     * Execute a JSON request to the passed endpoint URL and form params
     *
     * @param string $url The Endpoint URL
     * @param array $bodyJSONQuery The form params
     * @param string $method
     * @return mixed The payload if successful as an array, or an Error object containing the error message in case of failure
     */
    public static function requestJSON(string $url, array $bodyJSONQuery = [], string $method = 'POST'): ?array
    {
        $client = new Client();
        try {
            $options = [
                RequestOptions::JSON => $bodyJSONQuery,
            ];
            $response = $client->request($method, $url, $options);
            return self::validateAndDecodeJSONResponse($response);
        } catch (RequestException $exception) {
            return new Error(
                'request-failed',
                $exception->getMessage()
            );
        }
        return [];
    }

    protected static function validateAndDecodeJSONResponse(ResponseInterface $response): ?array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        if ($response->getStatusCode() != 200) {
            // Throw an error
            return new Error(
                'request-failed',
                sprintf(
                    $translationAPI->__('The response status code is \'%s\' instead of the expected \'%s\'', 'guzzle-helpers'),
                    $response->getStatusCode(),
                    200
                )
            );
        }
        $contentType = 'application/json';
        if (substr($response->getHeaderLine('content-type'), 0, strlen($contentType)) != $contentType) {
            // Throw an error
            return new Error(
                'request-failed',
                sprintf(
                    $translationAPI->__('The response content type is \'%s\' instead of the expected \'%s\'', 'guzzle-helpers'),
                    $response->getHeaderLine('content-type'),
                    $contentType
                )
            );
        }
        $body = $response->getBody();
        if (!$body) {
            // Throw an error
            return new Error(
                'request-failed',
                $translationAPI->__('The body of the response is empty', 'guzzle-helpers')
            );
        }
        return json_decode($body, JSON_FORCE_OBJECT);
    }

    /**
     * Execute several JSON requests asynchronously using the same endpoint URL and different queries
     *
     * @param string $url The Endpoint URL
     * @param array $bodyJSONQueries The form params
     * @param string $method
     * @return mixed The payload if successful as an array, or an Error object containing the error message in case of failure
     */
    public static function requestAsyncJSON(string $url, array $bodyJSONQueries = [], string $method = 'POST')
    {
        $client = new Client();

        try {
            // Initiate each request but do not block
            $promises = array_map(
                function($bodyJSONQuery) use($method, $url, $client) {
                    $options = [
                        RequestOptions::JSON => $bodyJSONQuery,
                    ];
                    return $client->requestAsync($method, $url, $options);
                },
                $bodyJSONQueries
            );

            // Wait on all of the requests to complete. Throws a ConnectException
            // if any of the requests fail
            $results = Promise\unwrap($promises);

            // Wait for the requests to complete, even if some of them fail
            $results = Promise\settle($promises)->wait();

            // You can access each result using the key provided to the unwrap function.
            return array_map(
                function($result) {
                    return self::validateAndDecodeJSONResponse($result['value']);
                },
                $results
            );
        } catch (RequestException $exception) {
            return new Error(
                'request-failed',
                $exception->getMessage()
            );
        }
        return [];
    }
}
