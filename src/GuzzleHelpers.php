<?php
namespace PoP\GuzzleHelpers;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\RequestException;
use PoP\ComponentModel\Error;

class GuzzleHelpers
{
    public static function requestJSON(string $url, array $bodyJSONQuery = [], string $method = 'POST')
    {
        $client = new Client();
        try {
            $options = [
                RequestOptions::JSON => $bodyJSONQuery,
            ];
            $response = $client->request($method, $url, $options);
            if ($response->getStatusCode() != 200) {
                // Do nothing
                return [];
            }
            $contentType = 'application/json';
            if (substr($response->getHeaderLine('content-type'), 0, strlen($contentType)) != $contentType) {
                // Do nothing
                return [];
            }
            $body = $response->getBody();
            if (!$body) {
                // Do nothing
                return [];
            }
            return json_decode($body, JSON_FORCE_OBJECT);
        } catch (RequestException $exception) {
            return new Error(
                'request-failed',
                $exception->getMessage()
            );
        }
        return [];
    }
}
