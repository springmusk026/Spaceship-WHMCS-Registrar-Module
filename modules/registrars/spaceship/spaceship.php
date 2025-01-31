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

if (!defined("WHMCS")) die("This file cannot be accessed directly");

use WHMCS\Database\Capsule;
use Spaceship\SpaceshipAPI;
use Spaceship\Utils;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Results\ResultsList;
use WHMCS\Domain\Registrar\Domain;
use WHMCS\Carbon;

require_once __DIR__ . '/lib/SpaceshipAPI.php';
require_once __DIR__ . '/lib/Utils.php';

// Helper functions
function validateApiCredentials($params) {
    if (empty($params['APIKey']) || empty($params['APISecret']) || empty($params['APIEndPoint'])) {
        throw new Exception("API credentials are missing.");
    }
}

function validateDomainParams($params) {
    if (empty($params['sld']) || empty($params['tld'])) {
        throw new Exception("Domain parameters are missing.");
    }
}

function getDomainName($params) {
    return $params['domainname'] ?? $params['sld'] . '.' . $params['tld'];
}

function handleApiResponse($response) {
    
    if (!empty($params['DebugMode'])) {
        Utils::log("Debug: API Response - " . json_encode($response));
    }
    
    if (isset($response['statusCode']) && $response['statusCode'] !== 200) {
        $message = json_decode($response['message'] ?? '{}', true);
        return ['error' => $message['detail'] ?? 'Unknown error'];
    }
    return null;
}

function getNameserversArray($params) {
    return array_filter([
        $params['ns1'] ?? null,
        $params['ns2'] ?? null,
        $params['ns3'] ?? null,
        $params['ns4'] ?? null,
        $params['ns5'] ?? null
    ]);
}

function initApi($params) {
    validateApiCredentials($params);
    
    if (!empty($params['DebugMode'])) {
        Utils::log("Debug: Initializing SpaceshipAPI with endpoint: {$params['APIEndPoint']}");
    }

    return new SpaceshipAPI($params['APIKey'], $params['APISecret'], $params['APIEndPoint']);
}

// Core module functions
function spaceship_MetaData() {
    return [
        'DisplayName' => 'Spaceship',
        'APIVersion' => '0.0.2',
    ];
}

function spaceship_getConfigArray() {
    return [
        'APIKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Enter your Spaceship API Key here',
        ],
        'APISecret' => [
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'Enter your Spaceship API Secret here',
        ],
        'APIEndPoint' => [
            'FriendlyName' => 'API Endpoint',
            'Type' => 'text',
            'Description' => 'Enter API endpoint',
            'Default' => 'https://spaceship.dev/api/v1',
        ],
        'contactId' => [
            'FriendlyName' => 'Contact Id',
            'Type' => 'text',
            'Description' => 'Enter Contact Id',
        ],
        'Description' => [
            'Type' => 'System',
            'Value' => "<br>Don't have a Spaceship account yet? Get one here: <a href='https://www.spaceship.com/' target='_blank'>Spaceship</a>"
        ],
        'DebugMode' => [
            'FriendlyName' => 'Debug Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable Debug mode. (this enables logging of API requests and responses in the module log)',
        ],
    ];
}

function spaceship_RegisterDomain($params) {
    try {
        $api = initApi($params);
        $domainName = getDomainName($params);
        
        $payload = [
            'name' => $domainName,
            'autoRenew' => false,
            'privacyProtection' => [
                'contactForm' => true,
                'level' => 'high'
            ],
            'nameservers' => [
                'provider' => 'custom',
                'hosts' => getNameserversArray($params)
            ],
            'contacts' => array_fill_keys(['registrant', 'admin', 'tech', 'billing'], $params['contactId'])
        ];
        
        if (!empty($params['DebugMode'])) {
            Utils::log("Debug: Registering domain - Payload: " . json_encode($payload));
        }
        
        $response = $api->registerDomain($domainName, $payload);
        if ($error = handleApiResponse($response)) return $error;

        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_TransferDomain($params) {
    try {
        $api = initApi($params);
        $response = $api->transferDomain([
            'domain' => getDomainName($params),
            'authCode' => $params['eppcode'],
        ]);
        
        if ($error = handleApiResponse($response)) return $error;
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_RenewDomain($params) {
    try {
        $api = initApi($params);
        $response = $api->renewDomain([
            'domain' => getDomainName($params),
            'years' => $params['regperiod'],
        ]);
        
        if ($error = handleApiResponse($response)) return $error;
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_GetDomainInformation($params) {
    try {
        $api = initApi($params);
        $response = $api->getDomainInfo(getDomainName($params));
        
        if ($error = handleApiResponse($response)) {
            throw new Exception($error['error']);
        }

        $nameservers = [];
        if (!empty($response['nameservers']['hosts'])) {
            foreach ($response['nameservers']['hosts'] as $index => $nameserver) {
                $nameservers["ns" . ($index + 1)] = $nameserver;
            }
        }

        $isLocked = isset($response['eppStatuses']) && 
                   in_array('clientTransferProhibited', $response['eppStatuses']);

        $expirationDate = null;
        if (!empty($response['expirationDate'])) {
            try {
                $expirationDate = Carbon::parse($response['expirationDate']);
            } catch (Exception $e) {
                Utils::log("error", "Invalid expiration date format: {$response['expirationDate']}");
            }
        }


        return (new Domain())
            ->setDomain($response['name'] ?? '')
            ->setNameservers($nameservers)
            ->setRegistrationStatus($response['verificationStatus'] ?? 'unknown')
            ->setTransferLock($isLocked)
            ->setExpiryDate($expirationDate)
            ->setRestorable($response['verificationStatus'] === 'success')
            ->setIdProtectionStatus(($response['privacyProtection']['level'] ?? '') === 'high')
            ->setDnsManagementStatus(($response['nameservers']['provider'] ?? '') === 'basic')
            ->setEmailForwardingStatus($response['privacyProtection']['contactForm'] ?? false)
            ->setIsIrtpEnabled(strtolower($params['tld'] ?? '') === '.com')
            ->setIrtpOptOutStatus(false)
            ->setIrtpTransferLock(false)
            ->setDomainContactChangePending(!empty($response['pendingChanges']))
            ->setPendingSuspension(!empty($response['suspensions']));
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_GetNameservers($params) {
    try {
        $api = initApi($params);
        $response = $api->getDomainInfo(getDomainName($params));
        
        if ($error = handleApiResponse($response)) return $error;
        
        $nameservers = [];
        foreach ($response['nameservers']['hosts'] as $index => $nameserver) {
            $nameservers["ns" . ($index + 1)] = $nameserver;
        }
        return $nameservers;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_SaveNameservers($params) {
    try {
        $api = initApi($params);
        $domain = getDomainName($params);
        
        $values = [
            "provider" => "custom",
            "hosts" => getNameserversArray($params)
        ];

        $response = $api->saveNameServers($domain, $values);
        if ($error = handleApiResponse($response)) return $error;
        
        $response = json_decode($response, true);
        $nameservers = [];
        foreach ($response['hosts'] as $index => $nameserver) {
            $nameservers["ns" . ($index + 1)] = $nameserver;
        }
        return $nameservers;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_GetRegistrarLock($params) {
    try {
        $api = initApi($params);
        $response = $api->getDomainInfo(getDomainName($params));
        
        if ($error = handleApiResponse($response)) return $error;
        
        $isLocked = isset($response['eppStatuses']) && 
                   in_array('clientTransferProhibited', $response['eppStatuses']);
        
        return $isLocked ? 'locked' : 'unlocked';
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_SaveRegistrarLock($params) {
    try {
        $api = initApi($params);
        $domain = getDomainName($params);
        
        $response = $api->getDomainInfo($domain);
        if ($error = handleApiResponse($response)) return $error;
        
        $isCurrentlyLocked = isset($response['eppStatuses']) && 
                            in_array('clientTransferProhibited', $response['eppStatuses']);
        
        $response = $api->setDomainLock($domain, ["isLocked" => !$isCurrentlyLocked]);
        if ($error = handleApiResponse($response)) return $error;
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_GetEPPCode($params) {
    try {
        $api = initApi($params);
        $response = $api->getAuthCode(getDomainName($params));
        
        if ($error = handleApiResponse($response)) return $error;
        
        $response = json_decode($response, true);
        return ["eppcode" => $response['authCode']];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_GetDNS($params) {
    try {
        $api = initApi($params);
        $response = $api->getDnsRecords(getDomainName($params));
        
        if ($error = handleApiResponse($response)) return $error;
        
        $dnsRecords = [];
        foreach ($response['items'] as $record) {
            $dnsRecords[] = [
                'hostname' => $record['name'],
                'type' => $record['type'],
                'address' => $record['value'],
                'priority' => $record['priority'] ?? '',
            ];
        }
        return $dnsRecords;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_SaveDNS($params) {
    return ["error" => "DNS Management not yet supported"];
}

function spaceship_GetContactDetails($params) {
    return ["error" => "Contact Details Management not yet supported"];
}

function spaceship_SaveContactDetails($params) {
    return ["error" => "Contact Details Management not yet supported"];
}

function spaceship_Sync($params) {
    try {
        $api = initApi($params);
        $response = $api->getDomainInfo(getDomainName($params));
        
        if ($error = handleApiResponse($response)) return $error;

        $nameservers = [];
        if (!empty($response['nameservers']['hosts'])) {
            foreach ($response['nameservers']['hosts'] as $index => $nameserver) {
                $nameservers["ns" . ($index + 1)] = $nameserver;
            }
        }

        $isLocked = isset($response['eppStatuses']) && 
                   in_array('clientTransferProhibited', $response['eppStatuses']);

        $expirationDate = strtotime($response['expirationDate']);
        if (!$expirationDate) {
            throw new Exception("Invalid expiration date format.");
        }

        return [
            "active" => time() < $expirationDate,
            "expired" => time() >= $expirationDate,
            "expirydate" => date('Y-m-d', $expirationDate),
            "nameservers" => $nameservers,
            "isLocked" => $isLocked,
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function spaceship_parse_clientinfo($params) {
    $fields = [
        'firstName' => ['First Name', 'firstname'],
        'lastName' => ['Last Name', 'lastname'],
        'organization' => ['Company Name', 'companyname'],
        'email' => ['Email', 'email'],
        'address1' => ['Address 1', 'address1'],
        'address2' => ['Address 2', 'address2'],
        'city' => ['City', 'city'],
        'country' => ['Country', 'countrycode'],
        'stateProvince' => ['State', 'state'],
        'postalCode' => ['ZIP Code', 'postcode'],
        'phone' => ['Phone', 'phonenumber'],
        'phoneExt' => ['Phone Ext', 'phoneExt'],
        'fax' => ['Fax', 'fax'],
        'faxExt' => ['Fax Ext', 'faxExt'],
        'taxNumber' => ['Tax Number', 'taxNumber']
    ];

    $result = [];
    foreach ($fields as $key => $alternatives) {
        $value = null;
        foreach ($alternatives as $alt) {
            if (isset($params[$alt])) {
                $value = $params[$alt];
                break;
            }
        }
        $result[$key] = mb_convert_encoding($value ?? '', "UTF-8", "auto");
    }
    
    return $result;
}