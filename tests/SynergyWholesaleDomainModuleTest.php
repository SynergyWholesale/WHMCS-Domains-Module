<?php
namespace SynergyWholesale\WHMCS\Test;

use PHPUnit\Framework\TestCase;

/**
 * Synergy Wholesale Registrar Module Test
 *
 * PHPUnit test that asserts the fundamental requirements of a WHMCS
 * registrar module.
 *
 * Custom module tests are added in addtion.
 *
 * @copyright Copyright (c) Synergy Wholesale Pty Ltd 2020
 * @license https://github.com/synergywholesale/whmcs-domains-module/LICENSE
 */

class SynergyWholesaleDomainModuleTest extends TestCase
{
    public static function providerCoreFunctionNames()
    {
        return [
            ['RegisterDomain'],
            ['TransferDomain'],
            ['RenewDomain'],
            ['GetNameservers'],
            ['SaveNameservers'],
            ['GetRegistrarLock'],
            ['SaveRegistrarLock'],
            ['GetContactDetails'],
            ['SaveContactDetails'],
            // Extra methods
            ['ReleaseDomain'],
            ['GetEPPCode'],
            ['IDProtectToggle'],
            ['CheckAvailability'],
            ['GetPremiumPrice'],
            ['GetDomainSuggestions']
        ];
    }

    /**
     * Test Core Module Functions Exist
     *
     * This test confirms that the functions recommended by WHMCS (and more)
     * are defined in this module.
     *
     * @param $method
     *
     * @dataProvider providerCoreFunctionNames
     */
    public function testCoreModuleFunctionsExist($method)
    {
        $this->assertTrue(function_exists(SW_MODULE_NAME . '_' . $method));
    }

    public function testMinimumTldSyncPrices()
    {
        $this->assertSame('-1.00', synergywholesaledomains_helper_applyMinimumPrice('-1.00', '20.00'));
        $this->assertSame('0.00', synergywholesaledomains_helper_applyMinimumPrice('0.00', '20.00'));
        $this->assertSame('20.00', synergywholesaledomains_helper_applyMinimumPrice('19.99', '20.00'));
        $this->assertSame('20.00', synergywholesaledomains_helper_applyMinimumPrice('20.00', '20.00'));
        $this->assertSame('25.00', synergywholesaledomains_helper_applyMinimumPrice('25.00', '20.00'));
        $this->assertSame('20.005', synergywholesaledomains_helper_applyMinimumPrice('19.99', '20.005'));
    }

    public function testRenewMinimumDoesNotRaiseRegistrationPrice()
    {
        $prices = synergywholesaledomains_helper_applyTldSyncMinimums('15.00', '20.00', '10.00', '38.50', '25.00');

        $this->assertSame('20.00', $prices[0]);
        $this->assertSame('38.50', $prices[1]);
        $this->assertSame('25.00', $prices[2]);
    }

    public function testMinimumPricesAreRegistrarSettings()
    {
        $configuration = synergywholesaledomains_getConfigArray([]);

        $this->assertSame('0', $configuration['minimumRenewPrice']['Default']);
        $this->assertSame('0', $configuration['minimumTransferPrice']['Default']);
    }

    public function testTldSyncSettingsValidation()
    {
        $input = [
            'margin_type' => 'percentage',
            'margin' => '15',
            'rounding_value' => '0.95',
        ];
        $settings = synergywholesaledomains_validateTldSyncSettings($input);

        $this->assertSame('15', $settings['profitMargin']);

        $input['rounding_value'] = '1.00';
        $this->assertNotNull(synergywholesaledomains_validateTldSyncSettings($input));

        $input['margin'] = '-1';
        $this->assertNull(synergywholesaledomains_validateTldSyncSettings($input));
    }

    public function testModernTldSyncPageDetection()
    {
        $_SERVER['REQUEST_URI'] = '/admin/index.php/admin/utilities/tools/tldsync/import';

        $this->assertTrue(synergywholesaledomains_isTldSyncPage());
    }
}
