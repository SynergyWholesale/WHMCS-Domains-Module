<?php

/**
 * Synergy Wholesale Registrar Module
 *
 * @copyright Copyright (c) Synergy Wholesale Pty Ltd 2020
 * @license https://github.com/synergywholesale/whmcs-domains-module/LICENSE
 */

// http://docs.whmcs.com/Editing_Client_Area_Menus
use WHMCS\View\Menu\Item as MenuItem;

// We use this hook to override the WHMCS default manage private nameservers page
// As well as email forwarding and dns modification pages as well
add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $primarySidebar) {

    // Get the domain being visited and grab the id of it
    // http://docs.whmcs.com/classes/classes/WHMCS.Domain.Domain.html
    $domain              = Menu::context('domain');
    $hasEmailForwarding  = $domain->hasEmailForwarding;
    $hasDNSManagement    = $domain->hasDnsManagement;
    $hasIdProtection     = $domain->hasIdProtection;
    $registrarModuleName = $domain->registrarModuleName;

    // Make sure the domain belongs to the Synergy Wholesale Domains module before we go forth
    // and hide the default pages
    if ("synergywholesaledomains" == $registrarModuleName) {
        // If the Manage Private Nameservers page is defined, rid ourselves of it
        if (!is_null($primarySidebar->getChild('Domain Details Management')) && !is_null($primarySidebar->getChild('Domain Details Management')->getChild('Manage Private Nameservers'))) {
            $primarySidebar->getChild('Domain Details Management')->removeChild('Manage Private Nameservers');
        }

        // If we can find the user has dns management addon enabled, we hide WHMCS default page
        if ($hasDNSManagement) {
            if (!is_null($primarySidebar->getChild('Domain Details Management')) && !is_null($primarySidebar->getChild('Domain Details Management')->getChild('Manage DNS Host Records'))) {
                $primarySidebar->getChild('Domain Details Management')->removeChild('Manage DNS Host Records');
            }
        }

        // If we can find the user has email forwarding addon enabled, we hide the WHMCS default page
        if ($hasEmailForwarding) {
            if (!is_null($primarySidebar->getChild('Domain Details Management')) && !is_null($primarySidebar->getChild('Domain Details Management')->getChild('Manage Email Forwarding'))) {
                $primarySidebar->getChild('Domain Details Management')->removeChild('Manage Email Forwarding');
            }
        }
    }
});

// We use this hook to override the WHMCS default domain dns management page
// We want to go to our custom page, as long as the registrarModuleName is set and
// is pointing to our custom module
add_hook('ClientAreaPageDomainDNSManagement', 1, function (array $vars) {

    // Map the domain id
    $domainid = $vars['domainid'];

    // If the registarModule under dnsrecords -> vars is set then assign it
    // Otherwise is null
    if (isset($vars['dnsrecords']['vars']['registrarModule'])) {
        $registrarModuleName = $vars['dnsrecords']['vars']['registrarModule'];
    } else {
        $registrarModuleName = null;
    }

    // If the registrarModuleName is not null and equals synergywholesaledomains
    if (!is_null($registrarModuleName) && "synergywholesaledomains" == $registrarModuleName) {
        // Redirect to our custom page
        header("Location: clientarea.php?action=domaindetails&id=" . $domainid . "&modop=custom&a=manageDNSURLForwarding");
    }
});

// We use this hook to override the WHMCS default domain email forwarding page
// We want to go to our custom page, as long as the registrarModuleName is set and
// is pointing to our custom module
add_hook('ClientAreaPageDomainEmailForwarding', 1, function (array $vars) {

    // Map the domain id
    $domainid = $vars['domainid'];

    // If the registarModule under dnsrecords -> vars is set then assign it
    // Otherwise is null
    if (isset($vars['emailforwarders']['vars']['registrarModule'])) {
        $registrarModuleName = $vars['emailforwarders']['vars']['registrarModule'];
    } else {
        $registrarModuleName = null;
    }

    // If the registrarModuleName is not null and equals synergywholesaledomains
    if (!is_null($registrarModuleName) && "synergywholesaledomains" == $registrarModuleName) {
        // Redirect to our custom page
        header("Location: clientarea.php?action=domaindetails&id=" . $domainid . "&modop=custom&a=manageEmailForwarding");
    }
});

// https://support.cloudflare.com/hc/en-us/articles/200169436-How-can-I-have-Rocket-Loader-ignore-specific-JavaScripts-
add_hook('ClientAreaHeadOutput', 1, function (array $vars) {
    return str_replace('{WEB_ROOT}', $vars['WEB_ROOT'], '
        <script data-cfasync="false" src="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
        <link href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet" />
        <script data-cfasync="false" src="{WEB_ROOT}/modules/registrars/synergywholesaledomains/js/functions.min.js"></script>
        <link rel="stylesheet" type="text/css" href="{WEB_ROOT}/modules/registrars/synergywholesaledomains/css/synergywholesaledomains.min.css" />
    ');
});
