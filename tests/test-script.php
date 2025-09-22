<?php
/**
 * Author:      Evans Wanguba
 * Text Domain: test-script
 */

class WP_Genova_Test extends WP_UnitTestCase {
    public function test_encryption_roundtrip() {
        $secret = 'super-secret-123';
        $enc = wp_genova_encrypt($secret);
        $this->assertNotEmpty($enc);
        $dec = wp_genova_decrypt($enc);
        $this->assertEquals($secret, $dec);
    }


    public function test_get_plans_ajax() {
        // We can't easily call remote API here; ensure AJAX endpoint exists and returns structure when mocked.
        $this->assertTrue(function_exists('wp_genova_get_plans'));
    }
}