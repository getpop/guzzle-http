<?php
namespace PoP\GuzzleHelpers;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\RequestException;
use PoP\ComponentModel\Error;

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
