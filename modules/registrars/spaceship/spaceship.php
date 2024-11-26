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

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use Spaceship\SpaceshipAPI;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Results\ResultsList;
use WHMCS\Domain\Registrar\Domain;
use \WHMCS\Carbon;

require_once __DIR__ . '/lib/SpaceshipAPI.php';

/**
 * Define module metadata.
 */
function spaceship_MetaData()
{
    return [
        'DisplayName' => 'Spaceship',
        'APIVersion' => '0.0.1',
    ];
}

function spaceship_getConfigArray()
{
    return [
        'APIKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Enter your Spaceship API Key here.',
            'Default' => '',
        ],
        'APISecret' => [
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'Enter your Spaceship API Secret here.',
            'Default' => '',
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
        "Description"  => [
            "Type"  => "System",
            "Value" => "<br>Don't have an Spaceship account yet? Get one here: <a href='https://www.spaceship.com/' target='_blank'>Spaceship</a>"
        ],
    ];
}

function spaceship_RegisterDomain($params)
{
    if (empty($params['APIKey']) || empty($params['APISecret'])|| empty($params['APIEndPoint'])) {
        throw new Exception("API key or secret is missing.");
    }
    if (empty($params['sld']) || empty($params['tld'])) {
        throw new Exception("SLD or TLD is missing.");
    }
    if (empty($params['domainname'])) {
        throw new Exception("Domain name is missing.");
    }

    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    
    $contactId = $params['contactId'];
    $payload = [
        'name' => $params['domainname'],
        'autoRenew' => false,
        'privacyProtection' => [
            'contactForm' => true,
            'level' => 'high'
        ],
        'nameservers' => [
            'provider' => 'custom',
            'hosts' => array_filter([
                $params['ns1'] ?? null,
                $params['ns2'] ?? null,
                $params['ns3'] ?? null,
                $params['ns4'] ?? null,
                $params['ns5'] ?? null
            ])
        ],
        'contacts' => [
            'registrant' => $contactId,
            'admin' => $contactId,
            'tech' => $contactId,
            'billing' => $contactId
        ]
    ];

    try {
        $response = $api->registerDomain($params['domainname'], $payload);

        if (isset($response['statusCode']) && $response['statusCode'] !== 200) {
            $message = json_decode($response['message'], true);
            $errorDetail = $message['detail'] ?? 'Unknown error';
            return [
                'error' =>$errorDetail
            ];
        }


        $responseData = json_decode($response, true);
        if (!is_array($responseData)) {
            return [
                'error' =>"Invalid API response format. Raw response: " . $response
            ];
        }
        
        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return "Exception occurred: " . $e->getMessage();
    }
}


function spaceship_TransferDomain($params)
{
    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    $response = $api->transferDomain([
        'domain' => $params['sld'] . '.' . $params['tld'],
        'authCode' => $params['eppcode'],
    ]);

    if (isset($response['error'])) {
        return ['error' => $response['message']];
    }

    return [];
}

function spaceship_RenewDomain($params)
{
    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    $response = $api->renewDomain([
        'domain' => $params['sld'] . '.' . $params['tld'],
        'years' => $params['regperiod'],
    ]);

    if (isset($response['error'])) {
        return ['error' => $response['message']];
    }

    return [];
}

function spaceship_GetDomainInformation($params)
{
    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    
    $response = $api->getDomainInfo($params['sld'] . '.' . $params['tld']);
    $response = json_decode($response,true);
    
    if(isset($response['statusCode'])){
        $message = json_decode($response['message'],true);
        throw new Exception($message['detail']);
    }
    
    $nameservers = [];
    foreach ($response['nameservers']['hosts'] as $index => $nameserver) {
        $nameservers["ns" . ($index + 1)] = $nameserver;
    }
    
    $isLocked = false;
    if (isset($response['eppStatuses'])) {
        $isLocked = in_array('clientTransferProhibited', $response['eppStatuses']);
    }
    
    $expirationDate = $response['expirationDate'];
    
    return (new Domain())
        ->setDomain($response['name'])
        ->setNameservers($nameservers)
        ->setRegistrationStatus($response['verificationStatus'] ?? 'unknown')
        ->setTransferLock($isLocked)
        ->setTransferLockExpiryDate(null)
        ->setExpiryDate(Carbon::createFromFormat('Y-m-d\TH:i:s.u', $expirationDate))
        ->setRestorable($response['verificationStatus'] === 'success')
        ->setIdProtectionStatus($response['privacyProtection']['level'] === 'high')
        ->setDnsManagementStatus($response['nameservers']['provider'] === 'basic')
        ->setEmailForwardingStatus($response['privacyProtection']['contactForm'] ?? false)
        ->setIsIrtpEnabled(in_array(strtolower($params['tld']), ['.com']))
        ->setIrtpOptOutStatus(false)
        ->setIrtpTransferLock(false) 
        ->setDomainContactChangePending(!empty($response['pendingChanges'] ?? []))
        ->setPendingSuspension(!empty($response['suspensions'] ?? []));
}

function spaceship_GetNameservers($params)
{
    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    $response = $api->getDomainInfo($params['sld'] . '.' . $params['tld']);
   
    $response = json_decode($response,true);
    
    if(isset($response['statusCode'])){
        $message = json_decode($response['message'],true);
        return [
            'error' => $message['detail']
        ];
    }
    
    $nameservers = [];
    foreach ($response['nameservers']['hosts'] as $index => $nameserver) {
        $nameservers["ns" . ($index + 1)] = $nameserver;
    }
    return $nameservers;
}
function spaceship_GetRegistrarLock($params)
{
    try {
        $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
        
        $res = $api->getDomainInfo($params['sld'] . '.' . $params['tld']);
        
        if ($res instanceof \GuzzleHttp\Psr7\Response) {
            $res = json_decode($res->getBody()->getContents(), true);
        } else {
            $res = json_decode($res, true);
        }
        
        if (isset($res['statusCode']) && $res['statusCode'] != 200) {
            $message = is_array($res['message']) ? $res['message'] : json_decode($res['message'], true);
            return [
                'error' => $message['detail'] ?? 'Unknown error'
            ];
        }
        
        $isLocked = false;
        if (isset($res['eppStatuses'])) {
            $isLocked = in_array('clientTransferProhibited', $res['eppStatuses']);
        }
        
        return $isLocked ? 'locked' : 'unlocked';
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function spaceship_SaveRegistrarLock($params)
{
    try {
        $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
        $domain = $params['sld'] . '.' . $params['tld'];
        
        $res = $api->getDomainInfo($domain);
        
        if ($res instanceof \GuzzleHttp\Psr7\Response) {
            $res = json_decode($res->getBody()->getContents(), true);
        } else {
            $res = json_decode($res, true);
        }
        
        if (isset($res['statusCode']) && $res['statusCode'] != 200) {
            $message = is_array($res['message']) ? $res['message'] : json_decode($res['message'], true);
            return [
                'error' => $message['detail'] ?? 'Unknown error'
            ];
        }
        
        $isCurrentlyLocked = false;
        if (isset($res['eppStatuses'])) {
            $isCurrentlyLocked = in_array('clientTransferProhibited', $res['eppStatuses']);
        }
        
        $data = [
            "isLocked" => $isCurrentlyLocked ? false : true
        ];
        
        
        $response = $api->setDomainLock($domain, $data);
        
        if ($response instanceof \GuzzleHttp\Psr7\Response) {
            $response = json_decode($response->getBody()->getContents(), true);
        } else {
            $response = json_decode($response, true);
        }
        
        if (isset($response['statusCode']) && $response['statusCode'] != 200) {
            $message = is_array($response['message']) ? $response['message'] : json_decode($response['message'], true);
            return [
                'error' => $message['detail'] ?? 'Unknown error'
            ];
        }
        
        return [
            'success' => true,
        ];
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}
function spaceship_SaveNameservers($params)
{
    if (empty($params['APIKey']) || empty($params['APISecret'])|| empty($params['APIEndPoint'])) {
        throw new Exception("API key or secret is missing.");
    }
    if (empty($params['sld']) || empty($params['tld'])) {
        throw new Exception("SLD or TLD is missing.");
    }

    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    $domain = $params['sld'] . '.' . $params['tld'];
    
    $values = [
        "provider" => "custom",
        "hosts" => array_filter([
            isset($params["ns1"]) ? $params["ns1"] : null,
            isset($params["ns2"]) ? $params["ns2"] : null,
            isset($params["ns3"]) ? $params["ns3"] : null,
            isset($params["ns4"]) ? $params["ns4"] : null,
            isset($params["ns5"]) ? $params["ns5"] : null
        ])
    ];


    $response = $api->saveNameServers($domain, $values);
    
    if(isset($response['statusCode'])){
        $message = json_decode($response['message'],true);
        return [
            'error' => $message['detail']
        ];
    }
    
    $response = json_decode($response,true);
    
    $nameservers = [];
    foreach ($response['hosts'] as $index => $nameserver) {
        $nameservers["ns" . ($index + 1)] = $nameserver;
    }
    return $nameservers;
}

function spaceship_GetDNS($params)
{
    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    $response = $api->getDnsRecords($params['sld'] . '.' . $params['tld']);
    $response = json_decode($response,true);
    
    if(isset($response['statusCode'])){
        $message = json_decode($response['message'],true);
        return [
            'error' => $message['detail']
        ];
    }
    
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
}

function spaceship_SaveDNS($params)
{
    
    $values["error"] = "DNS Management yet not supported";
    return $values;
    
    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    $records = [];
    foreach ($params['dnsrecords'] as $record) {
        $records[] = [
            'type' => $record['type'],
            'name' => $record['hostname'],
            'value' => $record['address'],
            'priority' => $record['priority'] ?? '',
        ];
    }

    $response = $api->saveDnsRecords($params['sld'] . '.' . $params['tld'], $records);
    
    $response = json_decode($response,true);
    
    
    if (isset($response['error'])) {
        return ['error' => $response['message']];
    }

    return [];
}

function spaceship_GetEPPCode($params) {
    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    $response = $api->getAuthCode($params['sld'] . '.' . $params['tld']);
   
    if(isset($response['statusCode'])){
        $message = json_decode($response['message'],true);
        return [
            'error' => $message['detail']
        ];
    }
    $response = json_decode($response,true);
    
    $values["eppcode"] = $response['authCode'];
    return $values;
}

function spaceship_GetContactDetails($params) {
    try {
        
        $values["error"] = "Contact Details Management yet not supported";
        return $values;
        
        if (empty($params['APIKey']) || empty($params['APISecret'])|| empty($params['APIEndPoint'])) {
            throw new Exception("API key or secret is missing.");
        }
        if (empty($params['sld']) || empty($params['tld'])) {
            throw new Exception("SLD or TLD is missing.");
        }

        $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);

        $response = $api->getDomainInfo($params['sld'] . '.' . $params['tld']);

        $response = json_decode($response,true);
    
        if (isset($response['statusCode'])) {
            $message = json_decode($response['message'], true);
            $errorDetail = $message['detail'] ?? 'An unknown error occurred.';
            return [
                'error' => $errorDetail
            ];
        }

        if (empty($response['contacts'])) {
            throw new Exception("Contacts data is missing in the response.");
        }

        $contacts = $response['contacts'];

        $registrant = fetchContactDetails($api, $contacts['registrant']);
        $admin = fetchContactDetails($api, $contacts['admin']);
        $billing = fetchContactDetails($api, $contacts['billing']);
        $tech = fetchContactDetails($api, $contacts['tech']);

        $values = [
            'Registrant' => $registrant,
            'AdministrativeContact' => $admin,
            'BillingContact' => $billing,
            'TechnicalContact' => $tech,
        ];

        return $values;

    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function fetchContactDetails($api, $contactId) {
    try {
        $response = $api->getContactDetails($contactId);

        if (isset($response['statusCode'])) {
            $message = json_decode($response['message'], true);
            throw new Exception($message['detail'] ?? 'An unknown error occurred while fetching contact details.');
        }

        return formatContactDetails($response);

    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function formatContactDetails($contactDetails) {
    $contactDetails = json_decode($contactDetails, true);

    if (!is_array($contactDetails)) {
        throw new Exception("Malformed contact details response.");
    }

    return [
        'First Name' => $contactDetails['firstName'] ?? '',
        'Last Name' => $contactDetails['lastName'] ?? '',
        'Company Name' => $contactDetails['organization'] ?? '',
        'Email Address' => $contactDetails['email'] ?? '',
        'Address 1' => $contactDetails['address1'] ?? '',
        'Address 2' => $contactDetails['address2'] ?? '',
        'City' => $contactDetails['city'] ?? '',
        'State' => $contactDetails['stateProvince'] ?? '',
        'Postcode' => $contactDetails['postalCode'] ?? '',
        'Country' => $contactDetails['country'] ?? '',
        'Phone Number' => $contactDetails['phone'] ?? '',
        'Fax Number' => $contactDetails['fax'] ?? '',
    ];
}

function spaceship_parse_clientinfo($params) {
    $firstname   = $params["First Name"] ?? $params["firstname"];
    $lastname    = $params["Last Name"] ?? $params["lastname"];
    $companyname = $params["Company Name"] ?? $params["companyname"];
    $email       = $params["Email"] ?? $params["email"];
    $address1    = $params["Address 1"] ?? $params["address1"];
    $address2    = $params["Address 2"] ?? $params["address2"];
    $city        = $params["City"] ?? $params["city"];
    $country     = $params["Country"] ?? $params["countrycode"];
    $fax         = $params["Fax"] ?? $params["fax"];
    $faxExt      = $params["Fax Ext"] ?? $params["faxExt"];
    $phone       = $params["Phone"] ?? $params["phonenumber"];
    $phoneExt    = $params["Phone Ext"] ?? $params["phoneExt"];
    $postcode    = $params["ZIP Code"] ?? $params["postcode"];
    $state       = $params["State"] ?? $params["state"];
    $taxNumber   = $params["Tax Number"] ?? $params["taxNumber"];

    return [
        "firstName"     => mb_convert_encoding($firstname, "UTF-8", "auto"),
        "lastName"      => mb_convert_encoding($lastname, "UTF-8", "auto"),
        "organization"  => mb_convert_encoding($companyname, "UTF-8", "auto"),
        "email"         => mb_convert_encoding($email, "UTF-8", "auto"),
        "address1"      => mb_convert_encoding($address1, "UTF-8", "auto"),
        "address2"      => mb_convert_encoding($address2, "UTF-8", "auto"),
        "city"          => mb_convert_encoding($city, "UTF-8", "auto"),
        "country"       => mb_convert_encoding($country, "UTF-8", "auto"),
        "stateProvince" => mb_convert_encoding($state, "UTF-8", "auto"),
        "postalCode"    => mb_convert_encoding($postcode, "UTF-8", "auto"),
        "phone"         => mb_convert_encoding($phone, "UTF-8", "auto"),
        "phoneExt"      => mb_convert_encoding($phoneExt, "UTF-8", "auto"),
        "fax"           => mb_convert_encoding($fax, "UTF-8", "auto"),
        "faxExt"        => mb_convert_encoding($faxExt, "UTF-8", "auto"),
        "taxNumber"     => mb_convert_encoding($taxNumber, "UTF-8", "auto")
    ];
}

function spaceship_SaveContactDetails($params) {
    
    if (empty($params['APIKey']) || empty($params['APISecret'])|| empty($params['APIEndPoint'])) {
        throw new Exception("API key or secret is missing.");
    }
    if (empty($params['sld']) || empty($params['tld'])) {
        throw new Exception("SLD or TLD is missing.");
    }

    $values["error"] = "Contact Details Management yet not supported";
    return $values;
    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);
    
    
    $contactDetails = spaceship_parse_clientinfo($params["contactdetails"]["RegistrantContact"]);
    
    try {
        $response = $api->saveContactDetails($contactDetails);

        if (isset($response['statusCode'])) {
            $message = json_decode($response['message'], true);
            throw new Exception($message['detail'] ?? 'An unknown error occurred while fetching contact details.');
        }

        return ["success" => true];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}


function spaceship_Sync($params) {
    if (empty($params['APIKey']) || empty($params['APISecret'])|| empty($params['APIEndPoint'])) {
        throw new Exception("API key or secret is missing.");
    }
    if (empty($params['sld']) || empty($params['tld'])) {
        throw new Exception("SLD or TLD is missing.");
    }

    $api = new SpaceshipAPI($params['APIKey'], $params['APISecret'],$params['APIEndPoint']);

    $response = $api->getDomainInfo($params['sld'] . '.' . $params['tld']);
    $response = json_decode($response, true);

    if (isset($response['statusCode'])) {
        $message = isset($response['message']) ? json_decode($response['message'], true) : [];
        throw new Exception($message['detail'] ?? "An error occurred while fetching domain info.");
    }

    $nameservers = [];
    if (!empty($response['nameservers']['hosts']) && is_array($response['nameservers']['hosts'])) {
        foreach ($response['nameservers']['hosts'] as $index => $nameserver) {
            $nameservers["ns" . ($index + 1)] = $nameserver;
        }
    }

    $isLocked = false;
    if (isset($response['eppStatuses'])) {
        $isLocked = in_array('clientTransferProhibited', $response['eppStatuses']);
    }

    $expirationDate = isset($response['expirationDate']) ? strtotime($response['expirationDate']) : null;
    if ($expirationDate === null) {
        throw new Exception("Invalid expiration date format.");
    }

    $currentDate = time();
    $active = $currentDate < $expirationDate;
    $expired = !$active;

    $expiryDate = date('Y-m-d', $expirationDate);

    $values = [
        "active"     => $active,
        "expired"    => $expired,
        "expirydate" => $expiryDate,
        "nameservers" => $nameservers,
        "isLocked"   => $isLocked,
    ];

    return $values;
}

