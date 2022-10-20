<?php

declare(strict_types=1);

namespace PoP\GuzzleHTTP\Services;

use GuzzleHttp\Client;
use PoP\GuzzleHTTP\Exception\GuzzleInvalidResponseException;
use PoP\GuzzleHTTP\Exception\GuzzleRequestException;
use PoP\GuzzleHTTP\ObjectModels\RequestInput;

interface GuzzleServiceInterface
{
    public function setClient(Client $client): void;

    /**
     * Execute an HTTP request to the passed endpoint URL and form params
     *
     * @return array<string,mixed> The payload if successful as an array
     *
     * @throws GuzzleRequestException
     * @throws GuzzleInvalidResponseException
     */
    public function sendHTTPRequest(RequestInput $requestInput): array;

    /**
     * Execute several JSON requests asynchronously
     *
     * @param RequestInput[] $requestInputs
     * @return array<string,mixed> The payload if successful
     *
     * @throws GuzzleInvalidResponseException
     */
    public function sendAsyncHTTPRequest(array $requestInputs): array;
}
