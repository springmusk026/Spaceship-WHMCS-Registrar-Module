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
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class SpaceshipAPI
{
    private string $baseUri;
    private string $apiKey;
    private string $apiSecret;
    private Client $client;
    private array $headers;

    public function __construct(string $apiKey, string $apiSecret,string $baseUri)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUri = $baseUri;
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
            'message' => $errorMessage
        ];
    }

    private function validateRequiredParams(array $params, array $required): void
    {
        foreach ($required as $key) {
            if (!isset($params[$key])) {
                throw new \InvalidArgumentException("Missing required parameter: {$key}");
            }
        }
    }

    private function request(string $method, string $uri, array $options = [])
    {
        try {
            $response = $this->client->request($method, "$this->baseUri/$uri", $options);
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            return $this->handleRequestException($e);
        } catch (\JsonException $e) {
            return [
                'error' => true,
                'message' => 'JSON parsing error: ' . $e->getMessage(),
            ];
        }
    }

    public function getAuthCode(string $domain)
    {
        return $this->request('GET', "domains/$domain/transfer/auth-code",[
            'headers' => $this->headers
        ]);
    }

    public function getDomainInfo(string $domain)
    {
        
        return $this->request('GET', "domains/$domain",[
            'headers' => $this->headers
        ]);
    }
    public function registerDomain(string $domain,array $params): array
    {
        return $this->request('POST', "domains/$domain",[
            'headers' => $this->headers,
            'json' => $params
        ]);
    }

    public function transferDomain(array $params): array
    {
        $this->validateRequiredParams($params, ['domain', 'authCode']);
        return $this->request('POST', 'domains/transfer', [
            'headers' => $this->headers,
            'json' => $params,
        ]);
    }

    public function renewDomain(array $params): array
    {
        $this->validateRequiredParams($params, ['domain', 'years']);
        return $this->request('PUT', "domains/{$params['domain']}/renew", [
            'headers' => $this->headers,
            'json' => ['years' => $params['years']],
        ]);
    }

    public function saveContactDetails(array $contactDetails)
    {
        return $this->request('PUT', "contacts",[
            'headers' => $this->headers,
            'json' => $contactDetails
        ]);
    }
    public function saveNameServers(string $domain,array $nsDetails)
    {
        return $this->request('PUT', "domains/$domain/nameservers",[
            'headers' => $this->headers,
            'json' => $nsDetails
        ]);
    }

    public function getContactDetails(string $contactId)
    {
        return $this->request('GET', "contacts/$contactId",[
            'headers' => $this->headers
        ]);
    }

    public function saveContactAttributes(string $contactId, array $attributes): array
    {
        return $this->request('PUT', "/v1/contacts/attributes/{$contactId}", [
            'headers' => $this->headers,
            'json' => $attributes,
        ]);
    }

    public function getDnsRecords(string $domain): array
    {
        return $this->request('GET', "dns/records/{$domain}", [
            'headers' => $this->headers,
        ]);
    }

    public function saveDnsRecords(string $domain, array $records)
    {
        return $this->request('PUT', "dns/records/$domain",[
            'headers' => $this->headers,
            'json' => $records
        ]);
    }

    public function setDomainLock(string $domain, array $records)
    {
        return $this->request('PUT', "domains/$domain/transfer/lock",[
            'headers' => $this->headers,
            'json' => $records
        ]);
    }

    public function deleteDnsRecords(string $domain, array $records): array
    {
        return $this->request('DELETE', "/v1/dns/records/{$domain}", [
            'headers' => $this->headers,
            'json' => $records,
        ]);
    }
}
