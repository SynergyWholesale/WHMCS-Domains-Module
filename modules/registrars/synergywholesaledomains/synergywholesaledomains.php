<?php

/**
 * Synergy Wholesale Registrar Module
 *
 * @copyright Copyright (c) Synergy Wholesale Pty Ltd 2020
 * @license https://github.com/synergywholesale/whmcs-domains-module/LICENSE
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Carbon\Carbon;

define('API_ENDPOINT', 'https://{{API}}');
define('WHOIS_URL', 'https://{{FRONTEND}}/home/whmcs-whois-json');
define('WHATS_MY_IP_URL', 'https://{{FRONTEND}}/ip');
define('SW_MODULE_VERSION', '{{VERSION}}');
define('SW_MODULE_NAME', 'synergywholesaledomains');

function synergywholesaledomains_webRequest($url, $method = 'GET', array $params = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if ('POST' === $method) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $response = curl_exec($ch);

    if (0 !== curl_errno($ch)) {
        $info = curl_getinfo($ch);
        throw new \Exception('Curl error: ' . curl_error($ch), $info[CURLINFO_RESPONSE_CODE]);
    }

    curl_close($ch);
    return $response;
}

function synergywholesaledomains_helper_getDomain(array $params)
{
    return $params['sld'] . '.' . $params['tld'];
}

function synergywholesaledomains_helper_getNameservers(array $params)
{
    $nameservers = [];
    for ($i = 1; $i < 6; $i++) {
        if (empty($params["ns$i"])) {
            continue;
        }

        $nameservers[] = $params["ns$i"];
    }

    return $nameservers;
}

/**
 * Sends the API requests to the Synergy Wholesale API.
 *
 * @param string    $command        The Command to run send to the API
 * @param array     $params         The WHMCS parameters that come from the calling function
 * @param array     $request        The data that makes up the API request
 * @param bool      $throw_on_error Throw an exception if the API returns an error
 * @param bool      $force_domain   Insert the "domainName" element if it is not present
 *
 * @throws \Exception
 *
 * @return array
 */
function synergywholesaledomains_apiRequest($command, array $params = [], array $request = [], $throw_on_error = true, $force_domain = true)
{
    $auth = [
        'apiKey' => $params['apiKey'],
        'resellerID' => $params['resellerID'],
    ];

    /**
     * It has been decided that we will always send analytics.
     * This helps us make the most informed decision in terms of
     * backwards compatability across WHMCS versions and PHP support.
     */
    $analytics = [
        'php_ver' => str_replace(PHP_EXTRA_VERSION, '', PHP_VERSION),
        'whmcs_ver' => $params['whmcsVersion'],
        'whmcs_mod_ver' => SW_MODULE_VERSION,
    ];

    $request = array_merge($request, $analytics);

    if (!isset($request['resellerID']) || !isset($request['apiKey'])) {
        $request = array_merge($request, $auth);
    }

    if (!isset($request['domainName']) && $force_domain) {
        $request['domainName'] = $params['sld'] . '.' . $params['tld'];
    }

    $client = new \SoapClient(null, [
        'location' => API_ENDPOINT . '/?wsdl',
        'uri' => '',
        'trace' => true,
    ]);

    try {
        $response = $client->{$command}($request);
        $logResponse = is_string($response) ? $response : (array) $response;
        logModuleCall(SW_MODULE_NAME, $command, $request, $logResponse, $logResponse, $auth);
    } catch (SoapFault $e) {
        logModuleCall(SW_MODULE_NAME, $command, $request, $e->getMessage(), $e->getMessage(), $auth);

        if ($throw_on_error) {
            // Convert SOAP Faults to Exceptions
            throw new \Exception($e->getMessage());
        }
        
        return [
            'error' => $e->getMessage(),
        ];
    }


    if (!preg_match('/^(OK|AVAILABLE).*?/', $response->status)) {
        if ($throw_on_error) {
            throw new \Exception($response->errorMessage);
        }

        return [
            'error' => $response->errorMessage,
        ];
    }

    return get_object_vars($response);
}

/**
 * Gets the contacts from the provided paramters
 *
 * @param      arrray  $params    The parameters
 * @param      array   $contacts  The requested contacts/contactMap
 *
 * @return     array   The contacts.
 */
function synergywholesaledomains_helper_getContacts(array $params, array $contacts = [])
{
    $request = [];

    $contactTypeMap = [
        'registrant_' => '',
        'technical_' => 'admin',
        'admin_' => 'admin',
        'billing_' => 'admin',
    ];

    if (empty($contacts)) {
        $contacts = $contactTypeMap;
    }

    $contactMap = [
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'address' => [
            'address1',
            'address2',
        ],
        'suburb' => 'city',
        'country' => 'country',
        'state' => 'state',
        'postcode' => 'postcode',
        'phone' => 'phonenumber',
        'email' => 'email',
        'organisation' => 'companyname',
    ];

    foreach ($contacts as $sw_contact => $whmcs_contact) {
        foreach ($contactMap as $destination => $source) {
            if (is_array($source)) {
                $request[$sw_contact . $destination] = [];
                foreach ($source as $key) {
                    $request[$sw_contact . $destination][] = $params[$whmcs_contact . $key];
                }
                continue;
            }

            if ('phone' === $destination) {
                $phoneNumber = synergywholesaledomains_formatPhoneNumber(
                    $params[$whmcs_contact . $source],
                    $params[$whmcs_contact . 'country'],
                    $params[$whmcs_contact . 'state'],
                    $params[$whmcs_contact . 'phonecc']
                );

                $request[$sw_contact . 'phone'] = $phoneNumber;
                continue;
            }

            if ('country' === $destination) {
                if (!synergywholesaledomains_validateCountry($params[$whmcs_contact . $source])) {
                    return [
                        'error' => 'Country must be entered as 2 characters - ISO 3166 Standard. EG. AU',
                    ];
                }
            }

            if ('state' === $destination && 'AU' === $params[$whmcs_contact . 'country']) {
                $state = synergywholesaledomains_validateAUState($params[$whmcs_contact . 'state']);
                if (!$state) {
                    return [
                        'error' => 'A Valid Australian State Name Must Be Supplied, EG. NSW, VIC',
                    ];
                }

                $params[$whmcs_contact . $source] = $state;
            }

            $request[$sw_contact . $destination] = $params[$whmcs_contact . $source];
        }
    }

    return $request;
}

/**
 * Sends AJAX response
 *
 * @param      array  $data   The data
 */
function synergywholesaledomains_ajaxResponse(array $data, $response_code = 200)
{
    http_response_code($response_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/*
 * Returns the configuration for the Synergy Wholesale WHMCS domains module.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_getConfigArray(array $params)
{
    $configuration = [
        'Description' => [
            'Value' => 'Not a Synergy Wholesale partner yet? Become one now: <a target="_blank" href="https://synergywholesale.com/become-a-partner/">https://synergywholesale.com/become-a-partner/</a>',
            'Type' => 'System',
        ],
        'FriendlyName' => [
            'Value' => 'Synergy Wholesale',
            'Type' => 'System',
        ],
        'resellerID' => [
            'FriendlyName' => 'Reseller ID',
            'Type' => 'text',
            'Size' => '15',
            'Description' => 'Enter your Reseller ID here',
        ],
        'apiKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '45',
            'Description' => 'Enter your API Key here',
        ],
        'doRenewal' => [
            'FriendlyName' => 'Force .AU Renewal on Transfer',
            'Type' => 'yesno',
            'Size' => '1',
            'Description' => 'Tick if you wish to perform a renewal on any .au domains submitted for transfer that are within 90 days of expiry.',
        ],
        'test_api_connection' => [
            'FriendlyName' => 'Check API Connectivity',
            'Type' => 'yesno',
            'Description' => 'Enable to see connectivity status to the Synergy Wholesale API',
        ],
        'whoisUpdate' => [
            'FriendlyName' => 'Force update WHOIS.json',
            'Type' => 'yesno',
            'Description' => 'Enable this option to force update the WHOIS.json data<br><b>NOTE:</b> This option will be disabled automatically again once you have clicked \'Save Changes\' and the update sequence is completed.',
        ],
        'auDirectShowSingleContestedAvailable' => [
            'FriendlyName' => 'Ordering Form Support - Single Contested .AU Direct Domains',
            'Type' => 'yesno',
            'Size' => '1',
            'Description' => 'Tick if you wish to support single contested .au direct domain names within your ordering forms (requires Synergy Wholesale to be set as your lookup provider). If unticked, single contested domains will show as unavailable upon lookup. <a href="https://synergywholesale.com/faq/article/the-direct-au-domain-name-im-looking-at-is-contested-what-does-this-mean/" target="_blank">Learn more.</a>',
        ],
        'auDirectShowMultiContestedAvailable' => [
            'FriendlyName' => 'Ordering Form Support - Multi-Contested .AU Direct Domains ',
            'Type' => 'yesno',
            'Size' => '1',
            'Description' => 'Tick if you wish to support multi-contested .au direct domain names within your ordering forms (requires Synergy Wholesale to be set as your lookup provider). If unticked, multi-contested domains will show as unavailable upon lookup. <a href="https://synergywholesale.com/faq/article/the-direct-au-domain-name-im-looking-at-is-contested-what-does-this-mean/" target="_blank">Learn more.</a>',
        ],
        'defaultDnsConfig' => [
            'FriendlyName' => 'Default DNS Config',
            'Type' => 'dropdown',
            'Options' => [
                '0' => 'Nothing',
                '1' => 'Nameservers',
                '2' => 'FreeDNS with email forwarding',
                '3' => 'Parked',
                '4' => 'FreeDNS',
                '5' => 'SWS Account Default',
                '6' => 'Legacy Hosting',
                '7' => 'Wholesale Hosting'
            ],
            'Description' => 'Which Default DNS Config will be applied to newly registered domains',
        ],
        'enableDnsManagement' => [
            'FriendlyName' => 'Enable DNS Management',
            'Type' => 'yesno',
            'Size' => '1',
            'Description' => 'Tick if you wish to enable DNS management on the domain, if Default DNS supports it.',
        ],
        'enableEmailForwarding' => [
            'FriendlyName' => 'Enable Email Forwarding',
            'Type' => 'yesno',
            'Size' => '1',
            'Description' => 'Tick if you wish to enable email forwarding on the domain, if Default DNS supports it.',
        ],
        'Version' => [
            'Description' => 'This module version: ' . SW_MODULE_VERSION,
        ],
        '' => [
            'Description' => '<b>Having trouble?</b> Login to the Synergy Wholesale Management Console and <a style="text-decoration:underline;" target="_blank" href="https://manage.synergywholesale.com/home/support/new">create a new support request.</a>',
        ],
    ];

    if (isset($params['test_api_connection']) && 'on' === $params['test_api_connection']) {
        try {
            $ipAddress = synergywholesaledomains_webRequest(WHATS_MY_IP_URL);
        } catch (\Exception $e) {
            $ipAddress = $_SERVER['SERVER_ADDR'];
        }

        try {
            $responseCode = 200;
            synergywholesaledomains_webRequest(API_ENDPOINT);
        } catch (\Exception $e) {
            $responseCode = $e->getCode();
        }

        $apiAuth = 'N/A';
        switch ($responseCode) {
            case 200:
                $message = '<b style="color:#1EB600">200 - Successful</b>';
                try {
                    $balance = synergywholesaledomains_apiRequest('balanceQuery', $params);
                    $apiAuth = '<b style="color:#1EB600">OK</b>';
                } catch (\Exception $e) {
                    $apiAuth = '<b style="color:#FF0000;">' . $e->getMessage() . '</b>';
                }
                break;
            case 403:
                // TODO: Add message about what to do here.
                $message = '<b style="color:#FF0000;">403 - Access Denied</b>';
                break;
            default:
                $message = '<b style="color:#FF0000;">Unable to connect: <i>Check firewall, or submit <a style="color:#FF0000;text-decoration:underline;" target="_blank" href="https://manage.synergywholesale.com/home/support/new">Support Request</a></i></b>';
                break;
        }

        $configuration['test_api_connection']['Description'] = nl2br(trim("
            Disable to hide connectivity status to the Synergy Wholesale API
            <i>This should be disabled unless configuring</i>\n
            This WHMCS installation's IP Address is <b>$ipAddress</b>
            <i>You will need to whitelist this IP address for the API usage within <a style=\"text-decoration:underline;\" target=\"_blank\" href=\"https://manage.synergywholesale.com/home/resellers/api\">Synergy Wholesale > API Information</a></i>\n
            Production API Whitelisting: $message
            Production API Authentication: $apiAuth
        "));

        try {
            /**
             * Finally turn off test_api_connection setting for the customer automatically so
             * this doesn't keep creating log multiple entries in tblmoduleLog
             */
            Capsule::table('tblregistrars')->where([
                'registrar' => 'synergywholesaledomains',
                'setting' => 'test_api_connection',
            ])->update([
                'value' => '',
            ]);
        } catch (\Exception $e) {
            /* Silence */
        }
    }

    // If the conversion option is ticked, then we need to process the conversion
    if (isset($params['whoisUpdate']) && 'on' === $params['whoisUpdate']) {
        $jsonPath = realpath(join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..', 'resources', 'domains', 'whois.json']));
        $whoisBackup = file_get_contents($jsonPath);
        $whois = file_get_contents(WHOIS_URL);

        if (!file_exists($jsonPath)) {
            $configuration['whoisUpdate']['Description'] .= "<br><b>NOTICE:</b> WHOIS.json update unsuccessful. File path invalid. The file at $jsonPath does not exist.";
            return $configuration;
        }

        if ($whois === $whoisBackup) {
            $configuration['whoisUpdate']['Description'] .= '<br><b>NOTICE:</b> WHOIS.json file is already up to date.';
            return $configuration;
        }

        // Testing to see if retrieved data is valid
        @json_decode($whois);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $configuration['whoisUpdate']['Description'] .= '<br><b>NOTICE:</b> WHOIS.json update unsuccessful. Unable to pull file.';
            return $configuration;
        }

        if (!file_put_contents($jsonPath, $whois)) {
            $configuration['whoisUpdate']['Description'] .= '<br><b>NOTICE:</b> <span style="color:red;">WHOIS.json update unsuccessful. Unable to update WHOIS.json file.</span>';

            //Revert any changes made to backup file
            file_put_contents($filePath, $whoisBackup);

            return $configuration;
        }

        $configuration['whoisUpdate']['Description'] .= '<br><b>NOTICE:</b> <span style="color:green;">WHOIS.json successfully updated.</span>';

        try {
            /**
             * Finally disable the setting for the customer automatically so
             * they don't have them needing to turn if off manually
             */
            Capsule::table('tblregistrars')->where([
                'registrar' => 'synergywholesaledomains',
                'setting' => 'whoisUpdate',
            ])->update([
                'value' => 'off',
            ]);
        } catch (\Exception $e) {
            $configuration['whoisUpdate']['Description'] .= '<br><b>NOTICE:</b> WHOIS.json successfully updated however we were unable to disable this option automatically for you. Please untick the option yourself manually and click \'Save Changes\' again.';
        }
    }

    return $configuration;
}

/**
 * Get the nameservers from the Synergy Wholesale API via the "domainInfo" command.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_getNameservers(array $params, $include_dns_config = false)
{
    try {
        $response = synergywholesaledomains_apiRequest('domainInfo', $params);
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }

    $values = [];

    if ($include_dns_config) {
        $values['dnsConfigType'] = $response['dnsConfig'];
    }

    foreach ($response['nameServers'] as $index => $value) {
        $values['ns' . ($index + 1)] = strtolower($value);
    }


    return $values;
}

/**
 * Updates a domain names nameservers.
 *
 * @param array $params
 *
 * @return array|void
 */
function synergywholesaledomains_SaveNameservers(array $params)
{
    $request = [
        'dnsConfigType' => 1,
        'nameServers' => synergywholesaledomains_helper_getNameservers($params),
    ];

    // TODO: Add hostname validation onto the provided nameservers.

    return synergywholesaledomains_apiRequest('updateNameServers', $params, $request, false);
}

/**
 * Returns the transfer lock status (if supported).
 *
 * @param array $params common module parameters
 *
 * @return string|array|null Lock status or error message, or nothing if not supported.
 */
function synergywholesaledomains_GetRegistrarLock(array $params)
{
    if (!preg_match('/\.(au|uk)$/i', $params['tld'])) {
        try {
            $response = synergywholesaledomains_apiRequest('domainInfo', $params);
            $locked = 'clientTransferProhibited' === $response['domain_status'];
            return $locked ? 'locked' : 'unlocked';
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    return null;
}

/**
 * Set registrar lock status.
 *
 * @param  $params common module parameters
 *
 * @return array|void
 */
function synergywholesaledomains_SaveRegistrarLock(array $params)
{
    $locked = synergywholesaledomains_GetRegistrarLock($params);
    if (is_null($locked)) {
        return [
            'error' => 'This domain name does not support registrar lock.',
        ];
    }

    $command = 'locked' === $locked ? 'unlockDomain' : 'lockDomain';
    return synergywholesaledomains_apiRequest($command, $params, [], false);
}

/**
 * .UK domain push function.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_ReleaseDomain(array $params)
{
    return synergywholesaledomains_apiRequest('domainReleaseUK', $params, [
        'tagName' => $params['transfertag'],
    ], false);
}

/**
 * Domain name registration function.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_RegisterDomain(array $params)
{
    $request = [
        'nameServers' => synergywholesaledomains_helper_getNameservers($params),
        'years' => $params['regperiod'],
        'idProtect' => $params['idprotection'],
        'specialConditionsAgree' => true,
    ];

    $eligibility = [];
    $contacts = synergywholesaledomains_helper_getContacts($params, ['registrant_' => '']);
    $request = array_merge($request, $contacts);

    if (preg_match('/\.?au$/', $params['tld'])) {
        $eligibility['registrantName'] = $params['additionalfields']['Registrant Name'];
        $eligibility['registrantID'] = $params['additionalfields']['Registrant ID'];

        if ('Business Registration Number' === $params['additionalfields']['Registrant ID Type']) {
            $params['additionalfields']['Registrant ID Type'] = 'OTHER';
        }

        $eligibility['registrantIDType'] = $params['additionalfields']['Registrant ID Type'];
        $eligibility['eligibilityType'] = $params['additionalfields']['Eligibility Type'];

        $brn = preg_match(
            '/(\w+) Business Number$|\((.{2,3})\)$|^Other/',
            $params['additionalfields']['Eligibility ID Type'],
            $matches
        );

        list(,, $brn) = $matches;
        if (synergywholesaledomains_validateAUState($brn)) {
            $brn .= ' BN';
        }

        $eligibility['eligibilityIDType'] = strtoupper($brn);
        $eligibility['eligibilityID'] = $params['additionalfields']['Eligibility ID'];
        $eligibility['eligibilityName'] = $params['additionalfields']['Eligibility Name'];


        // .AU Direct
        if (!empty($params['additionalfields']['Priority contact ID']) && !empty($params['additionalfields']['Priority authInfo'])) {
            $eligibility['associationID'] = $params['additionalfields']['Priority contact ID'];
            $eligibility['associationAuthInfo'] = $params['additionalfields']['Priority authInfo'];
        }
    }

    if (preg_match('/\.?uk$/', $params['tld'])) {
        $eligibility['tradingName'] = $params['additionalfields']['Registrant Name'];
        $eligibility['number'] = $params['additionalfields']['Registrant ID'];
        $eligibility['type'] = $params['additionalfields']['Registrant ID Type'];
        $eligibility['optout'] = $params['additionalfields']['WHOIS Opt-out'];
    }


    if (preg_match('/\.?us$/', $params['tld'])) {
        $eligibility['nexusCategory'] = $params['additionalfields']['Nexus Category'];
        if (!empty($params['additionalfields']['Nexus Country'])) {
            $eligibility['nexusCountry'] = $params['additionalfields']['Nexus Country'];
        }

        switch ($params['additionalfields']['Application Purpose']) {
            case 'Business use for profit':
                $eligibility['appPurpose'] = 'P1';
                break;
            case 'Non-profit business':
            case 'Club':
            case 'Association':
            case 'Religious Organization':
                $eligibility['appPurpose'] = 'P2';
                break;
            case 'Personal Use':
                $eligibility['appPurpose'] = 'P3';
                break;
            case 'Educational purposes':
                $eligibility['appPurpose'] = 'P4';
                break;
            case 'Government purposes':
                $eligibility['appPurpose'] = 'P5';
                break;
            default:
                $eligibility['appPurpose'] = '';
                break;
        }
    }

    if (!empty($eligibility)) {
        $request['eligibility'] = json_encode($eligibility);
    }

    // "premiumCost" is the price the API returned on "CheckAvailability"
    if (isset($params['premiumEnabled']) && $params['premiumEnabled'] && !empty($params['premiumCost'])) {
        $request['costPrice'] = $params['premiumCost'];
        $request['premium'] = true;
    }

    try {
        synergywholesaledomains_apiRequest('domainRegister', $params, $request);

        return [
            'success' => true,
        ];
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Transfer domain name functionality.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_TransferDomain(array $params)
{
     // This is a lazy way of getting the contact data in the format we need.
    $contact = synergywholesaledomains_helper_getContacts($params, ['' => '']);

    if (preg_match('/\.uk$/', $params['tld'])) {
        return synergywholesaledomains_apiRequest('transferDomain', $params, $contact, false);
    }

    $request = [
        'authInfo' => $params['transfersecret'],
        'doRenewal' => 1,
    ];

    if (preg_match('/\.au$/', $params['tld'])) {
        $canRenew = synergywholesaledomains_apiRequest('domainRenewRequired', $params, $request, false);
        $request['doRenewal'] = (int) ('on' === $params['doRenewal'] && 'OK_RENEWAL' === $canRenew['status']);
    }

    /**
     * We don't want to send the idProtect flag with the "can renew"
     * check. So let's append it to the request here.
     */
    $request['idProtect'] = $params['idprotection'];

    // Merge contact data into request
    $request = array_merge($request, $contact);

    if (isset($params['premiumEnabled']) && $params['premiumEnabled'] && !empty($params['premiumCost'])) {
        $request['costPrice'] = $params['premiumCost'];
        $request['premium'] = true;
    }
    
    return synergywholesaledomains_apiRequest('transferDomain', $params, $request, false);
}

/**
 * Enable or Disables ID Protection.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_IDProtectToggle(array $params)
{
    $command = $params['protectenable'] ? 'enableIDProtection' : 'disableIDProtection';
    return synergywholesaledomains_apiRequest($command, $params, [], false);
}

/**
 * Renew domain name function.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_RenewDomain(array $params)
{
    $request = [
        'years' => $params['regperiod'],
    ];

    if (isset($params['premiumEnabled']) && $params['premiumEnabled'] && !empty($params['premiumCost'])) {
        $request['costPrice'] = $params['premiumCost'];
        $request['premium'] = true;
    }

    return synergywholesaledomains_apiRequest('renewDomain', $params, $request, false);
}


/**
 * Synergy Wholesale uses a custom function instead of this.
 *
 * This is because the default WHMCS behaviour does not support SRV records.
 * We still register this so the "dnsmanagement" condition is met.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_GetDNS(array $params)
{
    // Synergy Wholesale Module does not use this function
    return [
        'vars' => [
            'registrarModule' => $params['registrar'],
        ],
    ];
}

/**
 * This function will save any dns records to the database
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_SaveDNS(array $params)
{
    return [];
}

/**
 * Syncs the domain name with the information in Synergy Wholesale.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_Sync(array $params)
{
    // Run the sync command on the domain specified
    try {
        $associationId = Capsule::table('tbldomainsadditionalfields')
            ->where('domainid', $params['domainid'])
            ->where('name', 'Priority contact ID')
            ->first();
        
        $response = synergywholesaledomains_apiRequest(
            'domainInfo',
            $params, 
            ['associationID' => $associationId->value ?? null],
            false
        );
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }

    $domain = Capsule::table('tbldomains')
        ->where('id', $params['domainid'])
        ->first();

    // Sync ID Protection
    if (isset($response['idProtect'])) {
        $idProtect = $response['idProtect'] === 'Enabled';
        if ($domain->idprotection != $idProtect) {
            Capsule::table('tbldomains')
                ->where('id', $params['domainid'])
                ->update([
                    'idprotection' => (int) $idProtect,
                ]);
        }
    }

    try {
        $check = synergywholesaledomains_apiRequest('checkDomain', $params, [
            'command' => 'renew',
        ]);
        
        if ($check['premium']) {
            // Get the currency ID for AUD
            $currency = Capsule::table('tblcurrencies')
                ->select('id')
                ->where('code', 'AUD')
                ->first();
            if (!isset($currency->id)) {
                return [
                    'error' => 'Failed to find AUD in currency table.',
                ];
            }

            $markup = $check['costPrice'];
            if (class_exists('WHMCS\Domains\Pricing\Premium')) {
                $markup *= 1 + WHMCS\Domains\Pricing\Premium::markupForCost($markup) / 100;
            }

            Capsule::table('tbldomains')
                ->where('id', $params['domainid'])
                ->update([
                    'is_premium' => 1,
                    'recurringamount' => $markup,
                ]);

            Capsule::table('tbldomains_extra')
                ->updateOrInsert(
                    [
                        'domain_id' => $params['domainid'],
                        'name' => 'registrarRenewalCostPrice',
                    ],
                    [
                        'value' => $check['costPrice'],
                    ]
                );
            Capsule::table('tbldomains_extra')
                ->updateOrInsert(
                    [
                        'domain_id' => $params['domainid'],
                        'name' => 'registrarCurrency',
                    ],
                    [
                        'value' => $currency->id,
                    ]
                );
        } elseif (!$check['premium'] && $domain->is_premium) {
            // Mark as not premium and recalculate the recurring amount.
            $pricing = localAPI('GetTLDPricing')['pricing'];
            $recurringamount = $pricing[$params['tld']]['renew'][$domain->registrationperiod];
            
            Capsule::table('tbldomains')
                ->where('id', $params['domainid'])
                ->update([
                    'is_premium' => 0,
                    'recurringamount' => $recurringamount,
                ]);
        }
    } catch (\Exception $e) {
        logModuleCall('synergywholesaledomains', 'sync_process', 'Update DB', $e->getMessage());
    }

    if (isset($response['transfer_status'])) {
        return synergywholesaledomains_TransferSync($params);
    }

    $returnData = [];
    if (preg_match('/\.au$/', $params['tld'])) {
        $appMap = [
            'auRegistrantIDType' => 'Registrant ID Type',
            'auRegistrantID' => 'Registrant ID',
            'auRegistrantName' => 'Registrant Name',
            'auEligibilityName' => 'Eligibility Name',
            'auEligibilityID' => 'Eligibility ID',
            'auEligibilityType' => 'Eligibility Type',
            'auEligibilityIDType' => 'Eligibility ID Type',
            'auPolicyID' => 'Eligibility Reason',
        ];
        try {
            foreach ($appMap as $apiName => $whmcsName) {
                if (empty($response[$apiName])) {
                    continue;
                }
                if ('auPolicyID' === $apiName) {
                    switch ($response[$apiName]) {
                        case 1:
                            $response[$apiName] = 'Domain name is an Exact Match Abbreviation or Acronym of your Entity or Trading Name.';
                            break;
                        case 2:
                            $response[$apiName] = 'Close and substantial connection between the domain name and the operations of your Entity.';
                            break;
                    }
                }
                Capsule::table('tbldomainsadditionalfields')
                    ->where('domainid', $params['domainid'])
                    ->where('name', $whmcsName)
                    ->update([
                        'value' => $response[$apiName],
                    ]);
            }
        } catch (\Exception $e) {
            logModuleCall('synergywholesaledomains', 'sync_process', 'Update DB', $e->getMessage());
        }
    }

    try {
        $selectInfo = Capsule::table('tbldomains')
            ->select('expirydate', 'additionalnotes', 'status')
            ->where('id', $params['domainid'])
            ->first();
        // If the domain used to exist in this whmcs installation it's safe to say if we get these errors then
        // it has been transferred away to another reseller
        if ('Domain Info Failed - Unable to retrieve domain id' === $response['error']) {
            // If now is after the domains expiry date mark it as cancelled
            if (time() >= strtotime($selectInfo->expirydate)) {
                $note = 'Domain has been marked as cancelled due to not being in your account and, the current date is past the expiry date';
                $returnData['cancelled'] = true;
            } else {
                $note = 'Domain has been marked as transferred away due to not being in your account';
                $returnData['transferredAway'] = true;
            }
        } elseif (!isset($response['domain_status'])) {
            return [
                'active' => 'Active' === $selectInfo->status,
                'expired' => 'Active' !== $selectInfo->status,
            ];
        } else {
            switch (strtolower($response['domain_status'])) {
                case 'ok':
                case 'clienttransferprohibited':
                case 'inactive':
                    $returnData = [
                        'active' => true,
                        'expirydate' => substr($response['domain_expiry'], 0, 10),
                    ];
                    break;
                case 'expired':
                case 'clienthold':
                case 'redemption':
                    $returnData = [
                        'expired' => true,
                        'expirydate' => substr($response['domain_expiry'], 0, 10),
                    ];
                    break;
                case 'outbound':
                case 'outbound_emailed':
                case 'transferaway':
                case 'outbound_approved':
                    $note = 'Domain is transferring out of your reseller account';
                    $returnData = [
                        'active' => true,
                    ];
                    break;
                case 'deleted':
                case 'dropped':
                case 'policydelete':
                    $note = 'Domain has been marked as cancelled due to being deleted';
                    $returnData = [ // Double check this is actually an okay thing.
                        'cancelled' => true,
                    ];
                    break;
                case 'transferredaway':
                case 'domain does not exist':
                    $note = 'Domain has transferred out of your partner account';
                    $returnData = [
                        'transferredAway' => true,
                    ];
                    break;
                case 'application_pending':
                // Application Approved and Rejected are transitional statuses, meaning Approved will eventually turn into OK and Rejected will turn into Deleted
                case 'application_approved':
                case 'application_rejected':
                case 'register_au_identity_verification':
                case 'register_au_identity_verification_success_registration_failure':
                case 'register_manual':
                    $returnData = [
                        'active' => false,
                        'cancelled' => false,
                        'transferredAway' => false,
                    ];
                    Capsule::table('tbldomains')
                        ->where('id', $params['domainid'])
                        ->update([
                            'status' => 'Pending Registration',
                        ]);
                    break;
                default:
                    $returnData = [
                        'active' => true,
                    ];
                    if (isset($response['domain_expiry'])) {
                        $returnData['expirydate'] = substr($response['domain_expiry'], 0, 10);
                    }
                    break;
            }
        }

        if (isset($note)) {
            Capsule::table('tbldomains')
                ->where('id', $params['domainid'])
                ->update(
                    [
                        'additionalnotes' => $selectInfo->additionalnotes . PHP_EOL . date('d/m/Y') . ' - Sync Cron - ' . $note,
                    ]
                );
        }
    } catch (\Exception $e) {
        logModuleCall('synergywholesaledomains', 'sync_process', 'Update DB', $e->getMessage());
    }

    return $returnData;
}


/**
 * Syncs the appropriate WHMCS status with the relevent
 * domain status in Synergy Wholesale.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_TransferSync(array $params)
{
    try {
        $response = synergywholesaledomains_apiRequest('domainInfo', $params);
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }

    if (!isset($response['domain_status'])) {
        return [
            'completed' => false,
        ];
    }

    switch (strtolower($response['domain_status'])) {
        case 'ok':
        case 'clienttransferprohibited':
        case 'clienthold':
        case 'expired':
        case 'inactive':
            return [
                'completed' => true,
                'expirydate' => substr($response['domain_expiry'], 0, 10),
                'failed' => false,
            ];
            break;
        case 'transfer_rejected':
        case 'transfer_cancelled':
        case 'transfer_rejected_registry':
        case 'transfer_timeout':
            return [
                'completed' => false,
                'failed' => true,
                'reason' => 'Transfer was either rejected, cancelled or timed out',
            ];
            break;
        default:
            return [
                'completed' => false,
            ];
    }
}

/**
 * Updates the contacts on a domain name.
 *
 * @param array $params
 *
 * @return array|void
 */
function synergywholesaledomains_SaveContactDetails(array $params)
{
    $request = [];
    $contactTypes = [
        'registrant' => 'Registrant', 
        'admin' => 'Admin',
        'technical' => 'Tech',
        'billing' => 'Billing',
    ];

    foreach ($contactTypes as $contactType => $whmcs_contact) {
        if (!isset($params['contactdetails'][$whmcs_contact])) {
            continue;
        }

        $request["{$contactType}_firstname"] = $params['contactdetails'][$whmcs_contact]['First Name'];
        $request["{$contactType}_lastname"]  = $params['contactdetails'][$whmcs_contact]['Last Name'];
        
        $request["{$contactType}_address"] = [
            $params['contactdetails'][$whmcs_contact]['Address 1'],
            $params['contactdetails'][$whmcs_contact]['Address 2'],
            $params['contactdetails'][$whmcs_contact]['Address 3'],
        ];

        $request["{$contactType}_email"] = $params['contactdetails'][$whmcs_contact]['Email'];
        $request["{$contactType}_suburb"] = $params['contactdetails'][$whmcs_contact]['City'];
        $request["{$contactType}_postcode"] = $params['contactdetails'][$whmcs_contact]['Postcode'];

        if (!preg_match('/\.?uk$/', $params['tld'])) {
            $request["{$contactType}_organisation"] = $params['contactdetails'][$whmcs_contact]['Organisation'];
        }
        // Validate the country being specified
        if (!synergywholesaledomains_validateCountry($params['contactdetails'][$whmcs_contact]['Country'])) {
            return [
                'error' => "$whmcs_contact Country must be entered as 2 characters - ISO 3166 Standard. EG. AU",
            ];
        }

        $request["{$contactType}_country"] = $params['contactdetails'][$whmcs_contact]['Country'];
        // See if country is AU
        if ('AU' == $request["{$contactType}_country"]) {
            // It is, so check to see if a valid AU State has been specified
            $state = synergywholesaledomains_validateAUState($params['contactdetails'][$whmcs_contact]['State']);
            if (!empty($params['contactdetails'][$whmcs_contact]['State']) && !$state) {
                return [
                    'error' => 'A Valid Australian State Name Must Be Supplied, EG. NSW, VIC',
                ];
            }

            // Yes - store the state
            $request["{$contactType}_state"] = $state;
        } else {
            // Country is not Australia, so we can just use whatever has been supplied as we can't validate it
            $request["{$contactType}_state"] = $params['contactdetails'][$whmcs_contact]['State'];
        }

        $request["{$contactType}_phone"] = synergywholesaledomains_formatPhoneNumber(
            $params['contactdetails'][$whmcs_contact]['Phone'],
            $params['contactdetails'][$whmcs_contact]['Country'],
            $params['contactdetails'][$whmcs_contact]['State'],
            $params['contactdetails']['Registrant']['Phone Country Code']
        );

        $request["{$contactType}_fax"] = synergywholesaledomains_formatPhoneNumber(
            $params['contactdetails'][$whmcs_contact]['Fax'],
            $params['contactdetails'][$whmcs_contact]['Country'],
            $params['contactdetails'][$whmcs_contact]['State'],
            $params['contactdetails']['Registrant']['Phone Country Code']
        );
    }

    try {
        synergywholesaledomains_apiRequest('updateContact', $params, $request);
        return [
            'success' => true,
        ];
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Get the contacts for a domain name. If ID Protect is enabled,
 * it'll still display the protected contact data.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_GetContactDetails(array $params)
{
    $idProtectStatus = synergywholesaledomains_apiRequest('domainInfo', $params, [], false);
    $command = ('Enabled' === $idProtectStatus['idProtect'] ? 'listProtectedContacts' : 'listContacts');
    $contacts = synergywholesaledomains_apiRequest($command, $params, [], false);
    $response = [];

    $map = [
        'firstname' => 'First Name',
        'lastname' => 'Last Name',
        'address1' => 'Address 1',
        'address2' => 'Address 2',
        'address3' => 'Address 3',
        'organisation' => 'Organisation',
        'suburb' => 'City',
        'state' => 'State',
        'country' => 'Country',
        'postcode' => 'Postcode',
        'phone' => 'Phone',
        'email' => 'Email',
    ];


    if (preg_match('/\.?uk$/', $params['tld'])) {
        unset($map['organisation']);
    }


    $contactTypes = ['registrant'];
    foreach (['admin', 'billing', 'tech'] as $otherTypes) {
        if (isset($contacts[$otherTypes])) {
            $contactTypes[] = $otherTypes;
        }
    }

    foreach ($contactTypes as $contact) {
        $whmcs_contact = ucfirst($contact);
        $response[$whmcs_contact] = [];
        foreach ($map as $from => $to) {
            $response[$whmcs_contact][$to] = $contacts[$contact]->$from;
        }
    }

    return $response;
}

/**
 * Returns the EPP Code for the domain name.
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_GetEPPCode(array $params)
{
    try {
        $eppCode = synergywholesaledomains_apiRequest('domainInfo', $params);
        return [
            'eppcode' => $eppCode['domainPassword'],
        ];
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * @param $params
 *
 * @return array
 */
function synergywholesaledomains_domainOptions(array $params)
{
    $request = $errors = [];

    $domainInfo = Capsule::table('tbldomains')
        ->select('dnsmanagement', 'emailforwarding')
        ->where('id', $params['domainid'])
        ->first();

    $tldInfo = Capsule::table("tbldomainpricing")
        ->where("extension", "=", ".{$params['tld']}")
        ->first();

    $vars = [
        'dnsmanagement' => $domainInfo->dnsmanagement,
        'emailforwarding' => $domainInfo->emailforwarding,
        'tlddnsmanagement' => $tldInfo->dnsmanagement,
        'tldemailforwarding' => $tldInfo->emailforwarding,
        'tld' => $params['tld'],
    ];

    try {
        $info = synergywholesaledomains_apiRequest('domainInfo', $params);
        $vars['dnsConfigType'] = $info['dnsConfig'];
        $vars['icannStatus'] = $info['icannStatus'];
    } catch (\Exception $e) {
        $errors[] = 'An error occured retrieving the domain information: ' . $e->getMessage();
    }

    if (isset($_REQUEST['sub']) && 'save' === $_REQUEST['sub'] && isset($_REQUEST['opt'])) {
        switch ($_REQUEST['opt']) {
            case 'dnstype':
                $request['nameServers'] = synergywholesaledomains_helper_getNameservers($info['nameServers']);
                // Set nameservers to DNS hosting if selected.
                if (1 == $_REQUEST['option']) {
                    $request['nameServers'] = [
                        'ns1.nameserver.net.au',
                        'ns2.nameserver.net.au',

                        'ns3.nameserver.net.au',
                    ];
                }
                
                // Set the new DNS Configuration Type.
                $vars['dnsConfigType'] = $request['dnsConfigType'] = $_REQUEST['option'];
                
                try {
                    $response = synergywholesaledomains_apiRequest('updateNameServers', $params, $request);
                } catch (\Exception $e) {
                    $errors[] = 'Update DNS type failed: ' . $e->getMessage();
                }
                break;
            case 'xxxmembership':
                try {
                    $response = synergywholesaledomains_apiRequest('updateXXXMembership', [
                        'membershipToken' => $_POST['xxxToken'],
                    ]);
                    $vars['info'] = 'Update XXX Membership successful.';
                } catch (\Exception $e) {
                    $errors[] = 'Update XXX Membership failed: ' . $e->getMessage();
                }
                break;
            case 'resendwhoisverif':
                try {
                    $response = synergywholesaledomains_apiRequest('resendVerificationEmail', $params, $request);
                    $vars['info'] = 'Resend WHOIS Verification Email successful';
                } catch (\Exception $e) {
                    $errors[] = 'Resend WHOIS Verification Email failed: ' . $e->getMessage();
                }
                break;
        }
    }

    if (!empty($errors)) {
        $vars['error'] = implode('<br>', $errors);
    } elseif (isset($_REQUEST['sub']) && 'save' === $_REQUEST['sub']) {
        $vars['info'] = 'Domain options have been updated successfully';
    }

    $uri = 'clientarea.php?' . http_build_query([
        'action' => 'domaindetails',
        'domainid' => $params['domainid'],
        'modop' => 'custom',
        'a' => 'domainOptions',
    ]);

    return [
        'templatefile' => 'domainoptions',
        'breadcrumb' => [
            $uri => 'Domain Options',
        ],
        'vars' => $vars,
    ];
}

/**
 * Controller for the "Manage DNSSEC" page.
 *
 * @param array $params
 */
function synergywholesaledomains_manageDNSSEC(array $params)
{
    $errors = $vars = $values = [];

    if (isset($_REQUEST['sub'])) {
        switch ($_REQUEST['sub']) {
            case 'save':
                try {
                    $save = synergywholesaledomains_apiRequest('DNSSECAddDS', $params, [
                        'algorithm' => $_REQUEST['algorithm'],
                        'digestType' => $_REQUEST['digestType'],
                        'digest' => $_REQUEST['digest'],
                        'keyTag' => $_REQUEST['keyTag'],
                    ]);

                    $vars['info'] = 'DNSSEC Record added successfully';
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
                break;
            case 'delete':
                try {
                    $delete = synergywholesaledomains_apiRequest('DNSSECRemoveDS', $params, [
                        'UUID' => $_REQUEST['uuid'],
                    ]);

                    $vars['info'] = 'DNSSEC Record deleted successfully';
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
                break;
        }
    }

    // Get a current list of any dnssec records
    try {
        $vars['records'] = [];
        $response = synergywholesaledomains_apiRequest('DNSSECListDS', $params);
        if (is_array($response['DSData'])) {
            foreach ($response['DSData'] as $record) {
                $vars['records'][] = $record;
            }
        }
    } catch (\Exception $e) {
        $errors[] = $e->getMessage();
    }


    if (!empty($errors)) {
        $vars['error'] = implode('<br>', $errors);
    }

    $uri = 'clientarea.php?' . http_build_query([
        'action' => 'domaindetails',
        'domainid' => $params['domainid'],
        'modop' => 'custom',
        'a' => 'manageDNSSEC',
    ]);

    return [
        'templatefile' => 'domaindnssec',
        'breadcrumb'   => [
            $uri => 'Manage DNSSEC Records',
        ],
        'vars' => $vars,
    ];
}

/**
 * Controller for the "Manage Child Hosts" page.
 *
 * @param array $params
 */
function synergywholesaledomains_manageChildHosts(array $params)
{
    $request = $vars = $errors = [];

    $domainName = synergywholesaledomains_helper_getDomain($params);

    if (isset($_REQUEST['sub'])) {
        switch ($_REQUEST['sub']) {
            case 'Manage Host':
                $vars = [
                    'manageHost' => 1,
                    'hostname' => $_REQUEST['ipHost'],
                ];
                break;
            case 'Delete Host':
                if (!preg_match("/(.*)\.$domainName/", $_REQUEST['ipHost'], $matches)) {
                    $errors[] = 'Unable to determine the hostname of the child host record';
                    break;
                }

                list(, $ipHost) = $matches;
                try {
                    synergywholesaledomains_apiRequest('deleteHost', $params, [
                        'host' => $ipHost,
                    ]);
                    $vars['info'] = 'Child Host has been successfully deleted';
                } catch (\Exception $e) {
                    $error[] = 'Unable to delete host record: ' . $e->getMessage();
                }
                break;
            case 'Save Host':
                try {
                    $ret = synergywholesaledomains_apiRequest('addHost', $params, [
                        'host' => $_REQUEST['newHostName'],
                        'ipAddress' => [
                            $_REQUEST['ipRecord'],
                        ],
                    ]);
                } catch (\Exception $e) {
                    $errors[] = 'There was an error adding the new host: ' . $e->getMessage();
                }
                break;
            case 'Delete Host IP':
            case 'Save Host IP':
                $vars = [
                    'manageHost' => 1,
                    'hostname' => $_REQUEST['ipHost'],
                ];

                if (!preg_match("/(.*)\.$domainName/", $_REQUEST['ipHost'], $matches)) {
                    $errors[] = 'Unable to determine the hostname of the child host record';
                    break;
                }

                $save = 'Save Host IP' === $_REQUEST['sub'];

                list(, $ipHost) = $matches;
                try {
                    $command = ($save ? 'addHostIP' : 'deleteHostIP');
                    synergywholesaledomains_apiRequest($command, $params, [
                        'host' => $ipHost,
                        'ipAddress' => [
                            $_REQUEST['ipRecord'],
                        ],
                    ]);

                    $vars['info'] = sprintf(
                        'IP %s successfully %s the child host record',
                        ($save ? 'added' : 'removed'),
                        ($save ? 'to' : 'from')
                    );
                } catch (\Exception $e) {
                    $errors[] = 'There was an error updating the ' . ($save ? 'adding' : 'deleting')  . ' IP: ' . $e->getMessage();
                }
                break;
        }
    }

    try {
        $vars['records'] = [];
        $hosts = synergywholesaledomains_apiRequest('listAllHosts', $params);
        foreach ($hosts['hosts'] as $host) {
             $vars['records'][$host->hostName] = [];
            foreach ($host->ip as $ipAddress) {
                $vars['records'][$host->hostName][] = $ipAddress;
            }
        }
    } catch (\Exception $e) {
        if (preg_match('/No Host Records Present/i', $e->getMessage())) {
            $vars['info'] = 'No host records have been found for this domain name';
        } else {
            $errors[] = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $vars['error'] = implode('<br>', $errors);
    }

    $uri = 'clientarea.php?' . http_build_query([
        'action' => 'domaindetails',
        'domainid' => $params['domainid'],
        'modop' => 'custom',
        'a' => 'manageChildHosts',
    ]);

    return [
        'templatefile' => 'domainchildhosts',
        'breadcrumb' => [
            $uri => 'Manage Child Host Records',
        ],
        'vars' => $vars,
    ];
}

/**
 * Controller for the "Initiate CoR" page.
 *
 * @param array $params
 * @return array
 */
function synergywholesaledomains_initiateAuCorClient(array $params)
{
    $errors = $vars = [];

    // Get pricing for input field
    $vars['pricing'] = getTLDPriceList($params['tld'], false);
    // Remove 10 year renewal since it's not possible.
    unset($vars['pricing']['10']);

    try {
        $response = synergywholesaledomains_apiRequest('domainInfo', $params);
    } catch (\Exception $e) {
        $vars['error'] = implode('<br>', $errors);
    }

    $vars['pending_cor'] = false;
    if(strtolower($response['domain_status']) == 'ok_pending_cor') {
        $vars['pending_cor'] = true;
    }

    // Check for any current Cors
    $cor = Capsule::table('tbldomains_extra')
        ->where([
            ['domain_id', $params['domainid']],
            ['name', 'like', 'cor_%'],
        ])
        ->orderByDesc('id')
        ->first();

    $invoiceId = !empty($cor) ? substr($cor->name, 4) : null;

    $corInvoice = Capsule::table('tblinvoices')
        ->where([
            ['id',  $invoiceId],
            ['status', 'Unpaid'],
        ])
        ->first();

    // If a Cor exists return invoice ID
    $vars['cor'] = !empty($corInvoice) ? $corInvoice->id : '';

    // If renewal period and no Cors exists
    if (!empty($_REQUEST['renewalLength']) && empty($vars['cor']) && !$vars['pending_cor']) {
        $renewalLength = $_REQUEST['renewalLength'];
        // If valid period create an invoice and add meta
        if (array_key_exists($renewalLength, $vars['pricing'])) {
            $invoiceData = [
                'userid' => $params['userid'],
                'itemdescription1' => "Initiate CoR for {$params['domain']}",
                'itemamount1' => $vars['pricing'][$renewalLength]['renew'],
            ];

            $invoice = localAPI('CreateInvoice', $invoiceData);

            if ($invoice['result'] == 'success') {
                // Add meta for domain extras with cor_invoiceId, value will be the renewal length
                Capsule::table('tbldomains_extra')->insert([
                    'domain_id' => $params['domainid'],
                    'name' => "cor_{$invoice['invoiceid']}",
                    'value' => $renewalLength,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            } else {
                $errors[] = 'Failed to create invoice.';
            }
        } else {
            $errors[] = 'Selected renewal length is invalid.';
        }
    }

    if (!empty($errors)) {
        $vars['error'] = implode('<br>', $errors);
    }

    $uri = 'clientarea.php?' . http_build_query([
            'action' => 'domaindetails',
            'domainid' => $params['domainid'],
            'modop' => 'custom',
            'a' => 'initiateAuCorClient',
        ]);

    return [
        'templatefile' => 'domaincor',
        'breadcrumb'   => [
            $uri => 'Initiate CoR',
        ],
        'vars' => $vars,
    ];
}

/**
 * Adds a URL Forwarder. This functionality is only available when
 * using the Synergy Wholesale "DNS Hosting" DNS/Nameserver configuration.
 *
 * @param string $type  The record type
 * @param array $record The record information
 * @param array $params The WHMCS parameters
 *
 * @return mixed
 */
function synergywholesaledomains_UrlForward($type, array $record, array $params)
{
    return synergywholesaledomains_apiRequest('addSimpleURLForward', $params, [
        'hostName' => $record['hostname'],
        'destination' => $record['address'],
        'type' => $type,
    ], false);
}


/**
 * Deletes a URL forwarder from the DNS Hosting/Forwarding system.
 *
 * @param array $record
 * @param array $params
 *
 * @return mixed
 */
function synergywholesaledomains_DelURLForward(array $record, array $params)
{
    return synergywholesaledomains_apiRequest('deleteSimpleURLForward', $params, [
        'recordID' => $record['record_id'],
    ], false);
}

/**
 * Adds a DNS record from the DNS Hosting zone.
 *
 * @param array $record
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_AddDNSRec(array $record, array $params)
{
    $request = [
        'recordName' => $record['hostname'],
        'recordType' => $record['type'],
        'recordContent' => $record['address'],
        'recordTTL' => 14400
    ];

    // See if the TTL has been specified
    if (isset($record['ttl'])) {
        $request['recordTTL'] = $record['ttl'];
    }

    // See if priority has been specified
    if (isset($record['priority'])) {
        $request['recordPrio'] = $record['priority'];
    }

    if ('NS' === $request['recordType'] && $request['recordName'] === $request['domainName']) {
        return [
            'error' => 'Cannot add or remove NS records from root domain.'
        ];
    }

    return synergywholesaledomains_apiRequest('addDNSRecord', $params, $request, false);
}

/**
 * Deletes a DNS record from the DNS Hosting zone.
 *
 * @param array $record
 * @param array $params
 *
 * @return array|void
 */
function synergywholesaledomains_DelDNSRec(array $record, array $params)
{
    try {
        synergywholesaledomains_apiRequest('deleteDNSRecord', $params, [
            'domainName' => synergywholesaledomains_helper_getDomain($params),
            'recordID' => $record['record_id'],
        ]);
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Synergy Wholesale Module does not use this function
 *
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_GetEmailForwarding(array $params)
{
    return [
        'vars' => [
            'registrarModule' => $params['registrar'],
        ],
    ];
}

/**
 * @param array $params
 *
 * @return array
 */
function synergywholesaledomains_SaveEmailForwarding(array $params)
{
    // Synergy Wholesale Module does not use this function
    return [];
}

/**
 * Handles the functionality for the DNS and URL forwarding page.
 *
 * @param $params
 */
function synergywholesaledomains_manageDNSURLForwarding(array $params)
{
    $dnsHostingNameservers = [
        'ns1.nameserver.net.au',
        'ns2.nameserver.net.au',
        'ns3.nameserver.net.au',
    ];

    $request = $records = [];

    if (isset($_REQUEST['op'])) {
        switch ($_REQUEST['op']) {
            case 'getRecords':
                $records = synergywholesaledomains_custom_GetDNS($params);
                if (isset($records['error'])) {
                    if (preg_match('/DNS Hosting Is Not Enabled For This Domain/i', $records['error'])) {
                        // Just means the system is not enabled for it
                        return synergywholesaledomains_ajaxResponse(['info' => 'NOTE: It appears that DNS Hosting is not enabled for this domain name. Any records you add will automatically update the nameservers set on the domain name which may result in an undesired outcome which could result in your website and email being taken offline.']);
                    }

                    return synergywholesaledomains_ajaxResponse($records);
                }

                if (empty($records)) {
                    return synergywholesaledomains_ajaxResponse(['info' => 'No records exist for this domain name.']);
                }

                $data['domain'] = $params['domain'];
                $data['records'] = $records;
                return synergywholesaledomains_ajaxResponse($data);
            case 'deleteRecord':
                if (empty($_REQUEST['record_id'])) {
                    return synergywholesaledomains_ajaxResponse(['error' => 'Missing identifier for record delete request.']);
                }

                if ('NS' === $_REQUEST['type'] && $_REQUEST['hostname'] == $request['domainName']) {
                    return synergywholesaledomains_ajaxResponse([
                        'error' => 'Error Deleting DNS record from database: Cannot add or remove NS records from root domain.'
                    ]);
                }

                $isUrl = in_array($_REQUEST['type'], ['URL', 'FRAME']);
                $type = ($isUrl ? 'URL forwarder' : 'DNS record');

                if ($isUrl) {
                    $delete = synergywholesaledomains_DELURLForward([
                        'record_id' => $_REQUEST['record_id'],
                    ], $params);
                } else {
                    $delete = synergywholesaledomains_DELDNSRec([
                        'record_id' => $_REQUEST['record_id'],
                    ], $params);
                }

                if (isset($delete['error'])) {
                    return synergywholesaledomains_ajaxResponse([
                        'error' => "Error deleting $type:" . $delete['error'],
                    ]);
                }

                return synergywholesaledomains_ajaxResponse([
                    'info' => "$type has been deleted",
                ]);
            case 'addRecord':
                $nameservers = synergywholesaledomains_getNameservers($params, true);
                if (isset($nameservers['error'])) {
                    return synergywholesaledomains_ajaxResponse(['error' => 'Unable to get the currently configured name servers']);
                }

                $correct = 0;
                foreach ($nameservers as $nameserver) {
                    if (in_array($nameserver, $dnsHostingNameservers)) {
                        $correct++;
                    }
                }

                // See if we match all conditions required
                if (3 !== $correct || !in_array($nameservers['dnsConfigType'], [2, 4])) {
                    try {
                        synergywholesaledomains_apiRequest('updateNameServers', $params, [
                            'dnsConfigType' => 2,
                            'nameServers' => $dnsHostingNameservers,
                        ]);
                    } catch (\Exception $e) {
                        return synergywholesaledomains_ajaxResponse(['error' => $e->getMessage()]);
                    }
                }

                $domain = synergywholesaledomains_helper_getDomain($params);

                if ('NS' === $_REQUEST['type'] && $_REQUEST['hostname'] === $domain) {
                    return synergywholesaledomains_ajaxResponse(['error' => 'Error adding DNS record: Cannot add NS on root domain.']);
                }

                if ($_REQUEST['type'] == 'SRV') {
                    $_REQUEST['address'] = "{$_REQUEST['address1']} {$_REQUEST['address2']} {$_REQUEST['address3']}";
                }

                $record = [];
                foreach (['type', 'address', 'priority', 'ttl'] as $key) {
                    if (!empty($_REQUEST[$key])) {
                        $record[$key] = $_REQUEST[$key];
                    }
                }

                if (empty($_REQUEST['hostname'])) {
                    // This may just be an empty record, which means we just use the domain name
                    $record['hostname'] = synergywholesaledomains_helper_getDomain($params);
                } else {
                    $record['hostname'] = strtolower($_REQUEST['hostname']);
                    $record['hostname'] = preg_replace('/(^https?:\/\/|\.$)/i', '', $record['hostname']);

                    if (!preg_match("/$domain$/i", $record['hostname'])) {
                        $record['hostname'] = $record['hostname'] . '.' . $domain;
                    }
                }

                if (in_array($record['type'], ['URL', 'FRAME']) && empty($record['address'])) {
                    return synergywholesaledomains_ajaxResponse(['error' => 'Address cannot be empty.']);
                }

                switch ($record['type']) {
                    case 'URL':
                        $add = synergywholesaledomains_UrlForward('P', $record, $params);
                        if (isset($add['error'])) {
                            return synergywholesaledomains_ajaxResponse(['error' => 'Error adding permanent URL forward: ' . $add['error']]);
                        }
                        $add['info'] = 'Permanent URL forward has been created';
                        break;
                    case 'FRAME':
                        $add = synergywholesaledomains_UrlForward('C', $record, $params);
                        if (isset($add['error'])) {
                            return synergywholesaledomains_ajaxResponse(['error' => 'Error adding URL Cloak forward: ' . $add['error']]);
                        }
                        $add['info'] = 'URL Cloaking forward has been created';
                        break;
                    default:
                        $add = synergywholesaledomains_AddDNSRec($record, $params);
                        if (isset($add['error'])) {
                            return synergywholesaledomains_ajaxResponse(['error' => 'Error adding DNS record: ' . $add['error']]);
                        }

                        // Strip the domain name from the record for cosmetic reasons.
                        $add['recordName'] = preg_replace("/(?:\.$domain\s*)$/m", '', $add['recordName']);
                        $add['info'] = 'DNS record has been created';
                        break;
                }
                // Let's give the 'id' some context.
                if (isset($add['id'])) {
                    $add['record_id'] = $add['id'];
                    unset($add['id']);
                }

                return synergywholesaledomains_ajaxResponse($add);
        }
    }

    $uri = 'clientarea.php?' . http_build_query([
        'action' => 'domaindetails',
        'domainid' => $params['domainid'],
        'modop' => 'custom',
        'a' => 'manageDNSURLForwarding',
    ]);

    // Return specific template details to smarty
    return [
        'templatefile' => 'domaindnsurlforwarding',
        'breadcrumb' => [
            $uri => 'DNS Hosting / URL Forwarding',
        ],
    ];
}

/**
 * Handles the functionality for the email forwarding page.
 *
 * @param $params
 */
function synergywholesaledomains_manageEmailForwarding(array $params)
{
    $dnsHostingNameservers = [
        'ns1.nameserver.net.au',
        'ns2.nameserver.net.au',
        'ns3.nameserver.net.au',
    ];

    $domain = synergywholesaledomains_helper_getDomain($params);

    if (isset($_REQUEST['op'])) {
        switch ($_REQUEST['op']) {
            case 'getRecords':
                try {
                    $forwarders = synergywholesaledomains_apiRequest('listMailForwards', $params);
                    if (empty($forwarders['forwards'])) {
                        $records['info'] = 'No records exist for this domain name.';
                    } else {
                        foreach ($forwarders['forwards'] as $forwarder) {
                            $records[] = [
                                'prefix' => str_replace("@$domain", '', $forwarder->source),
                                'forward_to' => $forwarder->destination,
                                'record_id' => $forwarder->id,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    if (preg_match('/Email Forwarding Is Not Enabled For This Domain/i', $e->getMessage())) {
                        return synergywholesaledomains_ajaxResponse([
                            'info' => 'NOTE: It appears that DNS Hosting is not enabled for this domain name. Any records you add will automatically update the nameservers set on the domain name which may result in an undesired outcome which could result in your website and email being taken offline.'
                        ]);
                    }

                    return synergywholesaledomains_ajaxResponse([
                        'info' => $e->getMessage()
                    ]);
                }

                return synergywholesaledomains_ajaxResponse($records);
            case 'deleteRecord':
                if (empty($_REQUEST['record_id'])) {
                    return synergywholesaledomains_ajaxResponse(['error' => 'Missing identifier for email forwarder.']);
                }

                $response = [];

                try {
                    synergywholesaledomains_apiRequest('deleteMailForward', $params, [
                        'forwardID' => $_REQUEST['record_id'],
                    ]);

                    $response['info'] = 'Email forwarder deleted.';
                } catch (\Exception $e) {
                    $response['error'] = 'Error deleteing email forwarder: ' . $e->getMessage();
                }

                return synergywholesaledomains_ajaxResponse($response);
            case 'addRecord':
                $nameservers = synergywholesaledomains_getNameservers($params, true);
                if (isset($nameservers['error'])) {
                    return synergywholesaledomains_ajaxResponse(['error' => 'Unable to get the currently configured name servers']);
                }

                $correct = 0;
                foreach ($nameservers as $nameserver) {
                    if (in_array($nameserver, $dnsHostingNameservers)) {
                        $correct++;
                    }
                }

                // See if we match all conditions required
                if (3 !== $correct || !in_array($nameservers['dnsConfigType'], [2, 4])) {
                    try {
                        synergywholesaledomains_apiRequest('updateNameServers', $params, [
                            'dnsConfigType' => 2,
                            'nameServers' => $dnsHostingNameservers,
                        ]);
                    } catch (\Exception $e) {
                        return synergywholesaledomains_ajaxResponse(['error' => 'Unable to update the DNS Hosting Nameservers']);
                    }
                }

                $request = $response = [];

                if (!empty($_REQUEST['prefix'])) {
                    $request['source'] = strtolower($_REQUEST['prefix']);
                    $request['source'] = rtrim($_REQUEST['prefix'], '@');

                    if (!preg_match("/$domain$/i")) {
                        $request['source'] = $request['source'] . '@' . $domain;
                    }
                }

                if (!empty($_REQUEST['forwardto'])) {
                    $request['destination'] = $_REQUEST['forwardto'];
                }

                try {
                    $add = synergywholesaledomains_apiRequest('addMailForward', $params, $request);
                    $response = [
                        'info' => 'Mail forwarder has been created',
                        'recordID' => $add['recordID'],
                    ];
                } catch (\Exception $e) {
                    $response['error'] = 'Error adding mail forwarder: ' . $e->getMessage();
                }

                return synergywholesaledomains_ajaxResponse($response);
        }
    }

    $uri = 'clientarea.php?' . http_build_query([
        'action' => 'domaindetails',
        'domainid' => $params['domainid'],
        'modop' => 'custom',
        'a' => 'manageEmailForwarding',
    ]);

    return [
        'templatefile' => 'domaindnsemailforwarding',
        'breadcrumb'   => [
            $uri => 'Email Forwarding',
        ],
    ];
}

/**
 * Get's the DNS Records for the domain name
 *
 * Note: This feature is only available to domains using DNS Hosting
 *
 * @param array $params
 * @return array
 */
function synergywholesaledomains_custom_GetDNS(array $params)
{
    $errors = $records = [];

    try {
        $forwarders = synergywholesaledomains_apiRequest('getSimpleURLForwards', $params);
        if (!empty($forwarders['records'])) {
            foreach ($forwarders['records'] as $record) {
                switch ($record->redirectType) {
                    case 'C':
                        $type = 'FRAME';
                        break;
                    case 'H':
                    case 'T':
                    case 'P':
                    default:
                        $type = 'URL';
                        break;
                }

                $records[] = [
                    'address' => $record->destination,
                    'hostname' => $record->hostname,
                    'record_id' => (int) $record->recordID,
                    'type' => $type,
                ];
            }
        }
    } catch (\Exception $e) {
        $errors[] = 'Get URL Forwards failed: ' . $e->getMessage();
    }

    try {
        $dns = synergywholesaledomains_apiRequest('listDNSZone', $params);
        if (!empty($dns['records'])) {

            /**
             * This is to remove the hostname from the record purely for cosmetic purposes.
             *
             * e.g. It will make "mail.mydomain.com.au" appear as "mail"
             */
            $hostNameRegex = '/(?:\.' . synergywholesaledomains_helper_getDomain($params) . '\s*)$/m';
            foreach ($dns['records'] as $record) {
                if ('SOA' === $record->type) {
                    continue;
                }
                $data = [
                    'address' => $record->content,
                    'hostname' => preg_replace($hostNameRegex, '', $record->hostName),
                    'record_id' => (int) $record->id,
                    'ttl' => (int) $record->ttl,
                    'type' => $record->type,
                ];

                if (in_array($record->type, ['MX', 'SRV'])) {
                    $data['priority'] = (int) $record->prio;
                }

                $records[] = $data;
            }
        }
    } catch (\Exception $e) {
        $errors[] = 'Get DNS records failed: ' . $e->getMessage();
    }

    if (!empty($errors)) {
        return [
            'error' => implode('<br>', $errors),
        ];
    }

    return $records;
}

function synergywholesaledomains_push(array $params)
{
    return synergywholesaledomains_apiRequest('transferOutboundApprove', $params, [], false);
}

/**
 * Register our custom pages we want to display in the Client Area.
 *
 * @param array $params
 * @return array
 */
function synergywholesaledomains_ClientAreaCustomButtonArray(array $params)
{
    $pages = [
        'Manage Child Host Records' => 'manageChildHosts',
        'Domain Options'            => 'domainOptions',
        'Manage DNSSEC Records'     => 'manageDNSSEC',
    ];

    if (substr($params['tld'], -3) == '.au') {
        $pages = array_merge($pages, ['Initiate CoR' => 'initiateAuCorClient']);
    }

    return $pages;
}

/**
 * Register the functionality we want available (conditionally) in the Client Area.
 *
 * @param  $params
 * @return mixed
 */
function synergywholesaledomains_ClientAreaAllowedFunctions(array $params)
{
    $domainInfo = Capsule::table('tbldomains')
        ->select('dnsmanagement', 'emailforwarding')
        ->where('id', $params['domainid'])
        ->first();

    $functions = [];

    if ($domainInfo->dnsmanagement) {
        $functions['DNS Hosting / URL Forwarding'] = 'manageDNSURLForwarding';
    }

    if ($domainInfo->emailforwarding) {
        $functions['Email Forwarding'] = 'manageEmailForwarding';
    }

    return $functions;
}


/**
 * @param  $phoneNumber
 * @param  $country
 * @param  $state
 * @return mixed
 */
function synergywholesaledomains_formatPhoneNumber($phoneNumber, $country, $state = '', $countryCode = null)
{
    // If the phone number is empty, then simply return
    if (empty($phoneNumber)) {
        return $phoneNumber;
    }

    // Remove any white space from the number
    $phoneNumber = preg_replace('/ /', '', $phoneNumber);

    // Remove any dashes from the number
    $phoneNumber = preg_replace('/-/', '', $phoneNumber);

    // First let's see if this is valid international format
    if (preg_match('/\+61\.[2,3,4,7,8]{1}[0-9]{8}/', $phoneNumber)) {
        // Valid International (Australian) Format so simply return
        return $phoneNumber;
    }

    // Now let's see if this is a valid international phone number
    if (preg_match('/\+[0-9]{1,3}\.[0-9]*/', $phoneNumber)) {
        // Valid International (Non Australian) Format so simply return
        return $phoneNumber;
    }

    // See if we can match onto the format 61.404040404
    preg_match_all('/^61\.([2,3,4,7,8]{1})([0-9]{8})$/', $phoneNumber, $result, PREG_PATTERN_ORDER);
    if (strlen($result[1][0]) > 0 && strlen($result[2][0]) > 0) {
        // Create new phone number (formatted)
        $phoneNumber = '+61.' . $result[1][0] . $result[2][0];
    }

    // Now let'e see if this is a psuedo international phone number for Australia
    preg_match_all('/^61([2,3,4,7,8]{1})([0-9]{8})$|^\+61([2,3,4,7,8]{1})([0-9]{8})$/i', $phoneNumber, $result, PREG_PATTERN_ORDER);
    if (strlen($result[1][0]) > 0 && strlen($result[2][0]) > 0) {
        // Create new phone number (formatted)
        $phoneNumber = '+61.' . $result[1][0] . $result[2][0];
        // Return phone number
        return $phoneNumber;
    } elseif (strlen($result[3][0] > 0) && strlen($result[4][0]) > 0) {
        // Create new phone number (formatted)
        $phoneNumber = '+61.' . $result[3][0] . $result[4][0];
        // Return phone number
        return $phoneNumber;
    }

    // If it doesn't match any of those, it might be in AU specific phone format
    preg_match_all('/^\(0([2,3,7,8])\)([0-9]{8})|^0([2,3,4,7,8])([0-9]{8})/', $phoneNumber, $result, PREG_PATTERN_ORDER);
    if (strlen($result[1][0]) > 0 && strlen($result[2][0]) > 0) {
        // Create the new formatted phone number
        $phoneNumber = '+61.' . $result[1][0] . $result[2][0];
        return $phoneNumber;
    } elseif (strlen($result[3][0] > 0) && strlen($result[4][0]) > 0) {
        // Create the new formatted phone number
        $phoneNumber = '+61.' . $result[3][0] . $result[4][0];
        return $phoneNumber;
    }

    // Final check before we give up, see if the phone number is only 8 digits long
    if (preg_match('/^([0-9]{8})$/', $phoneNumber, $regs)) {
        $result = $regs[0];

        // If country is AU
        if ('AU' == strtoupper($country)) {
            // Strip any spaces from the state name and change to all UPPER case chars
            $state = preg_replace('/ /', '', $state);
            $state = strtoupper($state);

            // Switch statement on the state
            switch (strtoupper($state)) {
                case 'VICTORIA':
                case 'VIC':
                case 'TASMANIA':
                case 'TAS':
                    $areacode = '3';
                    break;

                case 'QUEENSLAND':
                case 'QLD':
                    $areacode = '7';
                    break;

                case 'AUSTRALIANCAPITALTERRITORY':
                case 'AUSTRALIACAPITALTERRITORY':
                case 'ACT':
                case 'NEWSOUTHWALES':
                case 'NSW':
                    $areacode = '2';
                    break;

                case 'SOUTHAUSTRALIA':
                case 'SA':
                case 'NORTHERNTERRITORY':
                case 'NT':
                case 'WESTERNAUSTRALIA':
                case 'WA':
                    $areacode = '8';
                    break;
            }
        } else {
            // Not Australia, so simply return
            return $phoneNumber;
        }

        // Format the phone number
        $phoneNumber = '+61.' . $areacode . $result;

        // Return the formatted phone number
        return $phoneNumber;
    }

    // If we get here we have no idea what type of number has been supplied
    // Simply return it
    if (is_null($countryCode)) {
        return $phoneNumber;
    } else {
        // Country code can be inserted as integer because it should never exceed three digits.
        // Phone number could potentially be bigger than PHP_MAX_INT so let's insert it
        // into the string as a string to be ont he safe side.
        return sprintf('+%d.%s', $countryCode, $phoneNumber);
    }
}

/*
This function will validate the supplied country code
 */

/**
 * @param  $country
 * @return bool
 */
function synergywholesaledomains_validateCountry($country)
{
    // Set a list of valid country codes
    $cc = 'AF,AX,AL,DZ,AS,AD,AO,AI,AQ,AG,AR,AM,AW,AU,AT,AZ,BS,BH,BD,BB,BY,BE,BZ,BJ,BM,BT,BO,BQ,BA,BW,BV,BR,IO,BN,BG,BF,BI,
            KH,CM,CA,CV,KY,CF,TD,CL,CN,CX,CC,CO,KM,CG,CD,CK,CR,CI,HR,CU,CW,CY,CZ,DK,DJ,DM,DO,EC,EG,SV,GQ,ER,EE,ET,FK,FO,FJ,FI,FR,
            GF,PF,TF,GA,GM,GE,DE,GH,GI,GR,GL,GD,GP,GU,GT,GG,GN,GW,GY,HT,HM,VA,HN,HK,HU,IS,IN,ID,IR,IQ,IE,IM,IL,IT,JM,JP,JE,JO,KZ,KE,
            KI,KP,KR,KW,KG,LA,LV,LB,LS,LR,LY,LI,LT,LU,MO,MK,MG,MW,MY,MV,ML,MT,MH,MQ,MR,MU,YT,MX,FM,MD,MC,MN,ME,MS,MA,MZ,MM,NA,NR,NP,
            NL,NC,NZ,NI,NE,NG,NU,NF,MP,NO,OM,PK,PW,PS,PA,PG,PY,PE,PH,PN,PL,PT,PR,QA,RE,RO,RU,RW,BL,SH,KN,LC,MF,PM,VC,WS,SM,ST,SA,SN,
            RS,SC,SL,SG,SX,SK,SI,SB,SO,ZA,GS,SS,ES,LK,SD,SR,SJ,SZ,SE,CH,SY,TW,TJ,TZ,TH,TL,TG,TK,TO,TT,TN,TR,TM,TC,TV,UG,UA,AE,GB,US,
            UM,UY,UZ,VU,VE,VN,VG,VI,WF,EH,YE,ZM,ZW';

    // Explode into an array
    $ccArray = explode(',', $cc);

    return in_array(strtoupper($country), $ccArray);
}

/*

This function will return a valid au state name

 */

/**
 * @param string $state
 * @return bool|string
 */
function synergywholesaledomains_validateAUState($state)
{

    // Remove any spaces from the state
    $state = preg_replace('/\s|\./', '', $state);

    switch (strtoupper($state)) {
        case 'VICTORIA':
        case 'VIC':
            return 'VIC';

        case 'NEWSOUTHWALES':
        case 'NSW':
            return 'NSW';

        case 'QUEENSLAND':
        case 'QLD':
            return 'QLD';

        case 'AUSTRALIANCAPITALTERRITORY':
        case 'AUSTRALIACAPITALTERRITORY':
        case 'ACT':
            return 'ACT';

        case 'SOUTHAUSTRALIA':
        case 'SA':
            return 'SA';

        case 'WESTERNAUSTRALIA':
        case 'WA':
            return 'WA';

        case 'NORTHERNTERRITORY':
        case 'NT':
            return 'NT';

        case 'TASMANIA':
        case 'TAS':
            return 'TAS';

        default:
            return false;
    }
}

function synergywholesaledomains_AdminCustomButtonArray(array $params)
{
    $buttons =  [
        'Sync' => 'sync_adhoc',
        'Push' => 'push',
    ];

    if (substr($params['tld'], -3) == '.au') {
        $buttons = array_merge($buttons, ['Initiate .au CoR' => 'initiateAuCor']);
    }

    return $buttons;
}

/**
 * @param array $params
 * @return array|string[]|void
 */
function synergywholesaledomains_initiateAuCor(array $params)
{
    // Get domain Info
    try {
        $domainInfo = Capsule::table('tbldomains')
            ->where('id', $params['domainid'])
            ->first();
    } catch (Exception $e) {
        logModuleCall('synergywholesaledomains', 'initiateAuCor', 'Select DB', $e->getMessage());
        return [
            'error' => $e->getMessage(),
        ];
    }

    // Check if it's a .au domain
    if (substr($domainInfo->domain, -3) != '.au') {
        return [
            'error' => 'Selected domain is not .au',
        ];
    }

    try {
        // If it is we can send the Cor Request
        synergywholesaledomains_apiRequest('initiateAUCOR', $params, [
            'years' => $params['renewal'] ?? 1, // Admin default is 1, client can provide input
            'domainName' => $domainInfo->domain ?? '',
        ], true);
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function synergywholesaledomains_sync_adhoc(array $params)
{
    try {
        $domainInfo = Capsule::table('tbldomains')
            ->where('id', $params['domainid'])
            ->first();
    } catch (\Exception $e) {
        logModuleCall('synergywholesaledomains', 'syncButton', 'Select DB', $e->getMessage());
        return [
            'error' => $e->getMessage(),
        ];
    }

    if ('Pending Transfer' === $domainInfo->status) {
        return synergywholesaledomains_adhocTransferSync($params, $domainInfo);
    }

    return synergywholesaledomains_adhocSync($params, $domainInfo);
}

/**
 * This function syncs domain transfers via "Sync" button in the admin panel.
 *
 * @param      array   $params      The parameters
 * @param      object  $domainInfo  The domain information
 *
 * @return     array   ( description_of_the_return_value )
 */
function synergywholesaledomains_adhocTransferSync(array $params, $domainInfo)
{
    global $_LANG, $CONFIG;

    $response = synergywholesaledomains_TransferSync($params);
    $update = $syncMessages = [];
    if (isset($response['error'])) {
        return $response;
    }

    if ($response['failed'] && 'Cancelled' != $domainInfo->status) {
        $update['status'] = 'Cancelled';
        $errorMessage = (isset($response['reason']) ? $response['reason'] : $_LANG['domaintrffailreasonunavailable']);
    } elseif ($response['completed']) {
        $response = synergywholesaledomains_Sync($params);
        if ($response['active'] && 'Active' != $domainInfo->status) {
            $update['status'] = 'Active';
            $syncMessages[] = sprintf('Status updated from %s to Active', $domainInfo->status);
            sendMessage('Domain Transfer Completed', $domainInfo->id);
        }

        if ($response['expirydate']) {
            $newBillDate = $update['expirydate'] = $response['expirydate'];
            if ($CONFIG['DomainSyncNextDueDate'] && $CONFIG['DomainSyncNextDueDateDays']) {
                $unix_expiry = strtotime($response['expirydate']);
                $newBillDate = date('Y-m-d', strtotime(sprintf('-%d days', $CONFIG['DomainSyncNextDueDateDays']), $unix_expiry));
            }

            $update['nextinvoicedate'] = $update['nextduedate'] = $newBillDate;
        }
    }

    if (!empty($update)) {
        try {
            $update['synced'] = 1;

            Capsule::table('tbldomains')
                ->where('id', $params['domainid'])
                ->update($update);
        } catch (\Exception $e) {
            logModuleCall('synergywholesaledomains', 'adhocTransferSync', 'Update DB', $e->getMessage());
            return ['error' => 'Error updating domain; ' . $e->getMessage()];
        }
    }

    if (isset($errorMessage)) {
        return ['error' => $errorMessage];
    }

    global $domainstatus, $nextduedate, $expirydate;
    if (isset($update['status'])) {
        $domainstatus = $update['status'];
    }

    if (isset($update['nextduedate'])) {
        $nextduedate = str_replace('-', '/', $update['nextduedate']);
        $nextduedate = date('d/m/Y', strtotime($nextduedate));
    }

    if (isset($update['expirydate'])) {
        $expirydate = str_replace('-', '/', $update['expirydate']);
        $expirydate = date('d/m/Y', strtotime($expirydate));
    }

    $hookName = '';
    switch ($update['status']) {
        case 'Active':
            $hookName = 'DomainTransferCompleted';
            break;
        case 'Cancelled':
            $hookName = 'DomainTransferFailed';
            break;
    }

    if (!empty($hookName)) {
        run_hook(
            $hookName,
            [
                'domainId' => $params['domainid'],
                'domain' => $params['domainname'],
                'expiryDate' => $update['expirydate'],
                'registrar' => $params['registrar'],
            ]
        );
    }

    return [
        'message' => nl2br(
            empty($syncMessages) ?
            'Domain Sync successful.' :
            "Updated;\n    - " . implode("\n    - ", $syncMessages)
        )
    ];
}

/**
 * This function syncs domain names via "Sync" button in the admin panel.
 *
 * Most of the stuff we are updating here is to actually update the interface. This is
 * because the interface has the data fetched prior to this function running.
 *
 * @param      array   $params      The parameters
 * @param      object  $domainInfo  The domain information from the DB
 *
 * @return     array   Returns a message containing the updated information.
 */
function synergywholesaledomains_adhocSync(array $params, $domainInfo)
{
    global $CONFIG;

    $response = synergywholesaledomains_Sync($params);
    $syncMessages = $update = [];
    if (isset($response['error'])) {
        return $response;
    }

    if ($response['active'] && 'Active' != $domainInfo->status) {
        $update['status'] = 'Active';
    }

    if ($response['expired'] && 'Expired' != $domainInfo->status) {
        $update['status'] = 'Expired';
    }

    if ($response['cancelled'] && 'Active' == $domainInfo->status) {
        $update['status'] = 'Cancelled';
    }

    if (isset($response['transferredAway']) && $response['transferredAway'] && 'Transferred Away' != $domainInfo->status) {
        $update['status'] = 'Transferred Away';
    }

    if (isset($update['status'])) {
        $syncMessages[] = sprintf("Status from '%s' to '%s'", $domainInfo->status, $update['status']);
        $domainstatus = $update['status'];
    }

    if ($response['expirydate'] && $domainInfo->expirydate != $response['expirydate']) {
        $update['expirydate'] = $response['expirydate'];
        $diExpiryFormat = date('d/m/Y', strtotime($domainInfo->expirydate));
        $updateExpiryFormat = date('d/m/Y', strtotime($update['expirydate']));
        $syncMessages[] = sprintf("Expiry date from '%s' to '%s'", $diExpiryFormat, $updateExpiryFormat);
    }

    if ($response['expirydate']) {
        $newBillDate = $update['expirydate'] = $response['expirydate'];
        if ($CONFIG['DomainSyncNextDueDate'] && $CONFIG['DomainSyncNextDueDateDays']) {
            $unix_expiry = strtotime($response['expirydate']);
            $newBillDate = date('Y-m-d', strtotime(sprintf('-%d days', $CONFIG['DomainSyncNextDueDateDays']), $unix_expiry));
        }

        if ($newBillDate != $domainInfo->nextinvoicedate) {
            $update['nextinvoicedate'] = $update['nextduedate'] = $newBillDate;
            $diInvoiceDateFormat = date('d/m/Y', strtotime($domainInfo->nextinvoicedate));
            $updateBillDateFormat = date('d/m/Y', strtotime($newBillDate));
            $syncMessages[] = sprintf("Next Due Date from '%s' to '%s'", $diInvoiceDateFormat, $updateBillDateFormat);
        }
    }

    if (!empty($update)) {
        try {
            $update['synced'] = 1;

            Capsule::table('tbldomains')
                ->where('id', $params['domainid'])
                ->update($update);
        } catch (\Exception $e) {
            logModuleCall('synergywholesaledomains', 'adhocSync', 'Update DB', $e->getMessage());
            return ['error' => 'Error updating domain; ' . $e->getMessage()];
        }
    }

    global $domainstatus, $nextduedate, $expirydate, $recurringamount, $isPremium, $idprotection;
    if (isset($update['status'])) {
        $domainstatus = $update['status'];
    }

    if (isset($update['nextduedate'])) {
        $nextduedate = fromMySQLDate($update['nextduedate']);
    }

    if (isset($update['expirydate'])) {
        $expirydate = fromMySQLDate($update['expirydate']);
    }

    $domain = Capsule::table('tbldomains')
        ->where('id', $params['domainid'])
        ->first();
    
    if ($isPremium != $domain->is_premium) {
        if ($domain->is_premium) {
            $syncMessages[] = 'Domain has been identified as premium.';
        } else {
            $syncMessages[] = 'Domain is no longer identified as premium.';
        }
    }

    $idprotection = $domain->idprotection;
    $recurringamount = $domain->recurringamount;
    $isPremium = $domain->is_premium;

    return [
        'message' => nl2br(
            empty($syncMessages) ?
            'Domain Sync successful.' :
            "Updated;\n    - " . implode("\n    - ", $syncMessages)
        )
    ];
}

if (
    class_exists('\WHMCS\Domains\DomainLookup\SearchResult') &&
    class_exists('\WHMCS\Domains\DomainLookup\ResultsList')
) {

    /**
     * Check Domain Availability.
     *
     * Determine if a domain or group of domains are available for
     * registration or transfer.
     *
     * @param array $params common module parameters
     * @see https://developers.whmcs.com/domain-registrars/module-parameters/
     *
     * @see \WHMCS\Domains\DomainLookup\SearchResult
     * @see \WHMCS\Domains\DomainLookup\ResultsList
     *
     * @throws Exception Upon domain availability check failure.
     *
     * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
     */
    function synergywholesaledomains_CheckAvailability(array $params)
    {
        $type = App::isInRequest('epp') ? 'transfer' : 'register';

        try {
            $list = [];
            foreach ($params['tldsToInclude'] as $tld) {
                $list[] = $params['sld'] . $tld;
            }

            $check = synergywholesaledomains_apiRequest('bulkCheckDomain', $params, [
                'domainList' => $list,
                'command' => ('register' === $type ? 'create' : $type),
            ], true, false);
            
            $results = new WHMCS\Domains\DomainLookup\ResultsList();
            
            foreach ($check['domainList'] as $domain) {
                list($sld, $tld) = explode('.', $domain->domain, 2);

                $searchResult = new WHMCS\Domains\DomainLookup\SearchResult($sld, '.' . $tld);
                
                $status = WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED;

                if ('transfer' === $type && $domain->available) {
                    $status = WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED;
                }

                if ('register' === $type && !$domain->available) {
                    $status = WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED;
                }

                if ('au' === $tld) {
                    // Check if showing single contested domains as available is enabled
                    if ('register' === $type && (!isset($params['auDirectShowSingleContestedAvailable']) || $params['auDirectShowSingleContestedAvailable'] !== 'on')
                        && isset($domain->requiresMembership) && $domain->requiresMembership
                        && isset($domain->requiresApplication) && !$domain->requiresApplication
                    ) {
                        $status = WHMCS\Domains\DomainLookup\SearchResult::STATUS_RESERVED;
                    }

                    // Check if showing multi contested domains as available is enabled
                    if ('register' === $type && (!isset($params['auDirectShowMultiContestedAvailable']) || $params['auDirectShowMultiContestedAvailable'] !== 'on')
                        && isset($domain->requiresApplication) && $domain->requiresApplication
                    ) {
                        $status = WHMCS\Domains\DomainLookup\SearchResult::STATUS_RESERVED;
                    }
                }

                if (
                    (!empty($domain->premium) && !$params['premiumEnabled'] && $domain->available) ||
                    (!empty($domain->premium) && empty($domain->costPrice))
                ) {
                    $status = WHMCS\Domains\DomainLookup\SearchResult::STATUS_RESERVED;
                }

                $searchResult->setStatus($status);

                if ($domain->premium && $params['premiumEnabled'] && $domain->available) {
                    $searchResult->setPremiumDomain(true);
                    $searchResult->setPremiumCostPricing([
                        'renew' => $domain->costPrice,
                        $type => $domain->costPrice,
                        'CurrencyCode' => 'AUD',
                    ]);
                }

                $results->append($searchResult);
            }

            /**
             * This is a bit of a hack, but is required to makes premium transfer work.
             *
             * @see cart.php#L207-231 of 7.4.1 source.
             */
            if ('transfer' === $type) {
                $premiumSessionData = [];
                foreach ($results as $domain) {
                    $domain = $domain->toArray();
                    if ($domain['isPremium']) {
                        $premiumSessionData[$domain['domainName']] = [
                            'markupPrice' => $domain['pricing'],
                            'cost' => $domain['premiumCostPricing'],
                        ];
                    }
                }

                $storedSessionData = WHMCS\Session::get('PremiumDomains');
                if (is_array($storedSessionData)) {
                    $premiumSessionData = array_merge($storedSessionData, $premiumSessionData);
                }

                WHMCS\Session::set('PremiumDomains', $premiumSessionData);
            }

            return $results;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synergy Wholesale does not utilise this method.
     *
     * @param array $params common module parameters
     * @see https://developers.whmcs.com/domain-registrars/module-parameters/
     *
     * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
     */
    function synergywholesaledomains_GetDomainSuggestions(array $params)
    {
        return new WHMCS\Domains\DomainLookup\ResultsList();
    }

    function synergywholesaledomains_GetPremiumPrice(array $params)
    {
        $pricing = [
            'CurrencyCode' => 'AUD',
        ];

        try {
            foreach ($params['type'] as $type) {
                $type = strtolower($type);
                $check = synergywholesaledomains_apiRequest('checkDomain', $params, [
                    'command' => ('register' === $type ? 'create' : $type),
                ]);
    
                $pricing[$type] = $check->costPrice;
            }

            return $pricing;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}

if (class_exists('\WHMCS\Domain\TopLevel\ImportItem') && class_exists('\WHMCS\Results\ResultsList')) {
    function synergywholesaledomains_GetTldPricing(array $params)
    {
        try {
            $response = synergywholesaledomains_apiRequest('getDomainPricing', $params);
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }

        $results = new WHMCS\Results\ResultsList();

        foreach ($response['pricing'] as $extension) {
            $tld = '.' . $extension->tld;
            $transfer_price = $extension->transfer;
            $register_price = $extension->register_1_year;

            if (preg_match('/\.au$/', $tld)) {
                $transfer_price = 0.00;
            }

            if ($register_price < $extension->renew) {
                $register_price = $extension->renew;
            }

            $results[] = (new WHMCS\Domain\TopLevel\ImportItem())
                ->setExtension($tld)
                ->setMinYears($extension->minPeriod)
                ->setMaxYears($extension->maxPeriod)
                ->setRegisterPrice($register_price)
                ->setRenewPrice($extension->renew)
                ->setTransferPrice($transfer_price)
                ->setRedemptionFeePrice($extension->redemption)
                ->setRedemptionFeeDays($extension->cannotRenewWithin)
                ->setCurrency('AUD')
                ->setEppRequired(!preg_match('/\.uk$/', $tld))
                ->setGraceFeeDays($extension->canRenewWithin)
                ->setGraceFeePrice('0.00')
            ;
        }

        return $results;
    }
}

if (class_exists('\WHMCS\Domain\Registrar\Domain') && class_exists('\WHMCS\Carbon')) {
    function synergywholesaledomains_GetDomainInformation(array $params)
    {
        try {
            $response = synergywholesaledomains_apiRequest('domainInfo', $params);
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }

        $status = constant('\WHMCS\Domain\Registrar\Domain::STATUS_ACTIVE');

        if (isset($response['transfer_status'])) {
            return (new WHMCS\Domain\Registrar\Domain())
                ->setDomain($response['domainName'])
                ->setRegistrationStatus($status)
            ;
        }

        $nameservers = [];
        foreach ($response['nameServers'] as $index => $value) {
            $nameservers['ns' . ($index + 1)] = strtolower($value);
        }

        switch (strtolower($response['domain_status'])) {
            case 'expired':
            case 'clienthold':
            case 'redemption':
                $status = constant('\WHMCS\Domain\Registrar\Domain::STATUS_EXPIRED');
                break;
            case 'deleted':
            case 'dropped':
            case 'policydelete':
                $status = constant('\WHMCS\Domain\Registrar\Domain::STATUS_DELETED');
                break;
            case 'outbound':
            case 'transferaway':
            case 'transferredaway':
            case 'outbound_approved':
                $status = constant('\WHMCS\Domain\Registrar\Domain::STATUS_INACTIVE');
                break;
            case 'domain does not exist':
                $status = constant('\WHMCS\Domain\Registrar\Domain::STATUS_ARCHIVED');
                break;
        }

        if ('Suspended' === $response['icannStatus']) {
            $status = constant('\WHMCS\Domain\Registrar\Domain::STATUS_SUSPENDED');
        }

        return (new WHMCS\Domain\Registrar\Domain())
            ->setDomain($response['domainName'])
            ->setNameservers($nameservers)
            ->setTransferLock('clientTransferProhibited' === $response['domain_status'])
            ->setExpiryDate(WHMCS\Carbon::createFromFormat('Y-m-d H:i:s', $response['domain_expiry']))
            ->setIdProtectionStatus('Enabled' === $response['idProtect'])
            ->setRegistrationStatus($status)
        ;
    }
}
