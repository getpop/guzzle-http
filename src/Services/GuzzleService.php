<?php

declare(strict_types=1);

namespace PoP\GuzzleHTTP\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use PoP\GuzzleHTTP\Exception\GuzzleInvalidResponseException;
use PoP\GuzzleHTTP\Exception\GuzzleRequestException;
use PoP\GuzzleHTTP\ObjectModels\RequestInput;
use PoP\Root\Facades\Translation\TranslationAPIFacade;
use Psr\Http\Message\ResponseInterface;

class GuzzleService implements GuzzleServiceInterface
{
    protected ?Client $client = null;

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Execute an HTTP request to the passed endpoint URL and form params
     *
     * @return array<string,mixed> The payload if successful as an array
     *
     * @throws GuzzleRequestException
     * @throws GuzzleInvalidResponseException
     */
    public function sendHTTPRequest(RequestInput $requestInput): array
    {
        $client = $this->getClient();
        try {
            $response = $client->request($requestInput->method, $requestInput->url, $requestInput->options);
        } catch (Exception $exception) {
            throw new GuzzleRequestException(
                $exception->getMessage(),
                0,
                $exception
            );
        }
        return $this->validateAndDecodeJSONResponse($response);
    }

    protected function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = $this->createClient();
        }
        return $this->client;
    }

    protected function createClient(): Client
    {
        return new Client();
    }

    /**
     * @return array<string,mixed>
     * @throws GuzzleInvalidResponseException
     */
    protected function validateAndDecodeJSONResponse(ResponseInterface $response): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        if ($response->getStatusCode() !== 200) {
            throw new GuzzleInvalidResponseException(
                sprintf(
                    $translationAPI->__('The response status code is \'%s\' instead of the expected \'%s\'', 'guzzle-http'),
                    $response->getStatusCode(),
                    200
                )
            );
        }
        $contentType = $response->getHeaderLine('content-type');
        // It must be a JSON content type, for which it's either
        // application/json or one of its opinionated variants,
        // which all contain +json, such as
        // application/ld+json or application/geo+json
        $isJSONContentType =
            substr($contentType, 0, strlen('application/json')) === 'application/json'
            || (
                substr($contentType, 0, strlen('application/')) === 'application/'
                && str_contains($contentType, '+json')
            );
        if (!$isJSONContentType) {
            throw new GuzzleInvalidResponseException(
                sprintf(
                    $translationAPI->__('The response content type \'%s\' is unsupported', 'guzzle-http'),
                    $contentType
                )
            );
        }
        $bodyResponse = $response->getBody()->__toString();
        if (!$bodyResponse) {
            throw new GuzzleInvalidResponseException(
                $translationAPI->__('The body of the response is empty', 'guzzle-http')
            );
        }
        return json_decode($bodyResponse, true);
    }

    /**
     * Execute several JSON requests asynchronously
     *
     * @param RequestInput[] $requestInputs
     * @return array<string,mixed> The payload if successful
     *
     * @throws GuzzleInvalidResponseException
     */
    public function sendAsyncHTTPRequest(array $requestInputs): array
    {
        $client = $this->getClient();
        try {
            // Build the list of promises from the URLs and the body JSON queries
            $promises = [];
            foreach ($requestInputs as $requestInput) {
                $promises[] = $client->requestAsync(
                    $requestInput->method,
                    $requestInput->url,
                    $requestInput->options,
                );
            }

            // Wait on all of the requests to complete. Throws a ConnectException
            // if any of the requests fail
            $results = Utils::unwrap($promises);

            // Wait for the requests to complete, even if some of them fail
            $results = Utils::settle($promises)->wait();
        } catch (Exception $exception) {
            throw new GuzzleRequestException(
                $exception->getMessage(),
                0,
                $exception
            );
        }

        // You can access each result using the key provided to the unwrap function.
        return array_map(
            fn (array $result) => $this->validateAndDecodeJSONResponse($result['value']),
            $results
        );
    }
}
