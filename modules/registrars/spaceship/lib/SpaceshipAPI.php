<?php
/*
 * Author: Spring Musk
 * GitHub: https://github.com/springmusk026
 * Website: https://basantasapkota026.com.np
 * Telegram: https://t.me/springmusk
 * 
 * This file is part of the Spaceship Registrar Module for WHMCS.
 * All rights reserved © 2024.
 */

declare(strict_types=1);

namespace Spaceship;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SpaceshipAPI
{
    private string $baseUri;
    private string $apiKey;
    private string $apiSecret;
    private Client $client;
    private array $headers;

    public function __construct(string $apiKey, string $apiSecret, string $baseUri = 'https://spaceship.dev/api')
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUri = rtrim($baseUri, '/');
        $this->client = new Client();

        $this->headers = [
            'X-API-Key' => $this->apiKey,
            'X-API-Secret' => $this->apiSecret,
            'Content-Type' => 'application/json',
        ];
    }

    private function handleRequestException(RequestException $e): array
    {
        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
        $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();

        return [
            'statusCode' => $statusCode,
            'message' => $errorMessage,
        ];
    }

    private function request(string $method, string $uri, array $options = [])
    {
        try {
            $response = $this->client->request($method, "$this->baseUri/$uri", $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return $this->handleRequestException($e);
        }
    }

    public function getAuthCode(string $domain): array
    {
        return $this->request('GET', "domains/$domain/transfer/auth-code", ['headers' => $this->headers]);
    }

    public function getDomainInfo(string $domain): array
    {
        return $this->request('GET', "domains/$domain", ['headers' => $this->headers]);
    }

    public function registerDomain(string $domain, array $params): array
    {
        return $this->request('POST', "domains/$domain", ['headers' => $this->headers, 'json' => $params]);
    }

    public function transferDomain(array $params): array
    {
        return $this->request('POST', 'domains/transfer', ['headers' => $this->headers, 'json' => $params]);
    }

    public function renewDomain(array $params): array
    {
        return $this->request('PUT', "domains/{$params['domain']}/renew", ['headers' => $this->headers, 'json' => ['years' => $params['years']]]);
    }

    public function saveContactDetails(array $contactDetails): array
    {
        return $this->request('PUT', "v1/contacts", ['headers' => $this->headers, 'json' => $contactDetails]);
    }

    public function saveNameServers(string $domain, array $nsDetails): array
    {
        return $this->request('PUT', "domains/$domain/nameservers", ['headers' => $this->headers, 'json' => $nsDetails]);
    }

    public function getContactDetails(string $contactId): array
    {
        return $this->request('GET', "v1/contacts/$contactId", ['headers' => $this->headers]);
    }

    public function saveContactAttributes(string $contactId, array $attributes): array
    {
        return $this->request('PUT', "v1/contacts/attributes/$contactId", ['headers' => $this->headers, 'json' => $attributes]);
    }

    public function getDnsRecords(string $domain, int $take = 50, int $skip = 0, ?string $orderBy = null): array
    {
        $queryParams = http_build_query(array_filter(['take' => $take, 'skip' => $skip, 'orderBy' => $orderBy]));
        return $this->request('GET', "v1/dns/records/$domain?$queryParams", ['headers' => $this->headers]);
    }

    public function saveDnsRecords(string $domain, array $records): array
    {
        return $this->request('PUT', "v1/dns/records/$domain", ['headers' => $this->headers, 'json' => $records]);
    }

    public function deleteDnsRecords(string $domain, array $records): array
    {
        return $this->request('DELETE', "v1/dns/records/$domain", ['headers' => $this->headers, 'json' => $records]);
    }
    public function checkDomainAvailability(string $domain): array
    {
        return $this->request('GET', "domains/check/$domain", ['headers' => $this->headers]);
    }

    public function getDomainPricing(string $domain): array
    {
        return $this->request('GET', "domains/pricing/$domain", ['headers' => $this->headers]);
    }

    public function getDomainTransfers(): array
    {
        return $this->request('GET', "domains/transfer", ['headers' => $this->headers]);
    }

    public function getDNSSEC(string $domain): array
    {
        return $this->request('GET', "domains/$domain/dnssec", ['headers' => $this->headers]);
    }

    public function updateDNSSEC(string $domain, array $dnssecData): array
    {
        return $this->request('PUT', "domains/$domain/dnssec", ['headers' => $this->headers, 'json' => $dnssecData]);
    }
    public function getContactAttributes(string $contactId): array
    {
        return $this->request('GET', "v1/contacts/attributes/$contactId", ['headers' => $this->headers]);
    }
}
