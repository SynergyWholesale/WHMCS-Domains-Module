<?php

/**
 * Synergy Wholesale Registrar Module
 *
 * @copyright Copyright (c) Synergy Wholesale Pty Ltd 2020
 * @license https://github.com/synergywholesale/whmcs-domains-module/LICENSE
 */

// http://docs.whmcs.com/Editing_Client_Area_Menus
use WHMCS\View\Menu\Item as MenuItem;

/**
 * We have our own custom ones, so remove the default;
 *  - Manage Private Nameservers
 *  - Manage DNS Host Records
 *  - Manage Email Forwarding
 *  - Registrar Lock Status (for unsupported TLDs)
 */
add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $primarySidebar) {

    $context = Menu::context('domain');
    $menu = $primarySidebar->getChild('Domain Details Management');

    // Make sure the domain belongs to the Synergy Wholesale Domains module
    if (!is_null($menu) && 'synergywholesaledomains' === $context->registrarModuleName) {
        if (!is_null($menu->getChild('Manage Private Nameservers'))) {
            $menu->removeChild('Manage Private Nameservers');
        }

        if ($context->hasDnsManagement && !is_null($menu->getChild('Manage DNS Host Records'))) {
            $menu->removeChild('Manage DNS Host Records');
        }

        if ($context->hasEmailForwarding && !is_null($menu->getChild('Manage Email Forwarding'))) {
            $menu->removeChild('Manage Email Forwarding');
        }

        if (preg_match('/\.au$/', $context->domain) && !is_null($menu->getChild('Registrar Lock Status'))) {
            $menu->removeChild('Registrar Lock Status');
        }
    }
});

/**
 * Override the DNS Management page to link to our custom one
 */
add_hook('ClientAreaPageDomainDNSManagement', 1, function (array $vars) {

    $domain_id = $vars['domainid'];
    $registrarModuleName = null;

    if (isset($vars['dnsrecords']['vars']['registrarModule'])) {
        $registrarModuleName = $vars['dnsrecords']['vars']['registrarModule'];
    }

    if ('synergywholesaledomains' === $registrarModuleName) {
        header('Location: clientarea.php?action=domaindetails&id=' . $domain_id . '&modop=custom&a=manageDNSURLForwarding');
    }
});

/**
 * Override the Email Forwarding page to link to our custom one
 */
add_hook('ClientAreaPageDomainEmailForwarding', 1, function (array $vars) {

    $domain_id = $vars['domainid'];
    $registrarModuleName = null;

    if (isset($vars['emailforwarders']['vars']['registrarModule'])) {
        $registrarModuleName = $vars['emailforwarders']['vars']['registrarModule'];
    }

    if ('synergywholesaledomains' === $registrarModuleName) {
        header('Location: clientarea.php?action=domaindetails&id=' . $domain_id . '&modop=custom&a=manageEmailForwarding');
    }
});

/**
 * We've had reports of things not working/loading properly when they're using Cloudflare Rocket Loader, so let's add an exemption.
 * @see https://support.cloudflare.com/hc/en-us/articles/200169436-How-can-I-have-Rocket-Loader-ignore-specific-JavaScripts-
 */
add_hook('ClientAreaHeadOutput', 1, function (array $vars) {
    return str_replace('{WEB_ROOT}', $vars['WEB_ROOT'], '
        <script data-cfasync="false" src="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
        <link href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet" />
        <script data-cfasync="false" src="{WEB_ROOT}/modules/registrars/synergywholesaledomains/js/functions.min.js?v={{VERSION}}"></script>
        <link rel="stylesheet" type="text/css" href="{WEB_ROOT}/modules/registrars/synergywholesaledomains/css/synergywholesaledomains.min.css?v={{VERSION}}" />
    ');
});


/*
 * Remove the "Domain Currently Unlocked!" error message on the domain overview for TLDs that don't support registrar lock (such as .au)
 */
add_hook('ClientAreaPageDomainDetails', 1, function (array $vars) {

    $menu = Menu::context('domain');
        
    if (preg_match('/\.au$/', $menu->domain) && 'synergywholesaledomains' === $menu->registrar) {
        // Required to hide the error message
        $vars['managementoptions']['locking'] = false;
        $vars['lockstatus'] = false;

        return $vars;
    }
});
