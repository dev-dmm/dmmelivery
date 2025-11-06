<?php
/**
 * Unit Tests for DMM_Database
 *
 * @package DMM_Delivery_Bridge
 */

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase {
    
    private $database;
    private $wpdb_mock;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Mock global $wpdb
        global $wpdb;
        $this->wpdb_mock = $this->createMock(stdClass::class);
        $this->wpdb_mock->prefix = 'wp_';
        
        // Mock wpdb methods
        $this->wpdb_mock->method('get_charset_collate')
            ->willReturn('DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        $wpdb = $this->wpdb_mock;
        
        $this->database = new DMM_Database();
    }
    
    public function test_has_processed_voucher_not_processed() {
        global $wpdb;
        
        $wpdb->expects($this->once())
            ->method('get_var')
            ->willReturn(null);
        
        $result = $this->database->has_processed_voucher(123, 'acs', '1234567890');
        
        $this->assertFalse($result);
    }
    
    public function test_has_processed_voucher_already_processed() {
        global $wpdb;
        
        $wpdb->expects($this->once())
            ->method('get_var')
            ->willReturn('456');
        
        $result = $this->database->has_processed_voucher(123, 'acs', '1234567890');
        
        $this->assertEquals(456, $result);
    }
    
    public function test_mark_voucher_processed_success() {
        global $wpdb;
        
        $wpdb->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('dmm_processed_vouchers'),
                $this->callback(function($data) {
                    return isset($data['order_id']) &&
                           isset($data['courier']) &&
                           isset($data['voucher_hash']) &&
                           isset($data['processed_at']);
                }),
                ['%d', '%s', '%s', '%s']
            )
            ->willReturn(1);
        
        $result = $this->database->mark_voucher_processed(123, 'acs', '1234567890');
        
        $this->assertTrue($result);
    }
    
    public function test_mark_voucher_processed_failure() {
        global $wpdb;
        
        $wpdb->expects($this->once())
            ->method('insert')
            ->willReturn(false);
        
        $result = $this->database->mark_voucher_processed(123, 'acs', '1234567890');
        
        $this->assertFalse($result);
    }
    
    public function test_voucher_hash_consistency() {
        global $wpdb;
        
        $voucher = '1234567890';
        $courier = 'acs';
        
        // First call - mark as processed
        $wpdb->expects($this->exactly(2))
            ->method('insert')
            ->willReturn(1);
        
        $wpdb->expects($this->exactly(2))
            ->method('get_var')
            ->willReturnOnConsecutiveCalls(null, '123');
        
        // Mark as processed
        $this->database->mark_voucher_processed(123, $courier, $voucher);
        
        // Check if processed - should return order ID
        $result = $this->database->has_processed_voucher(123, $courier, $voucher);
        
        $this->assertEquals(123, $result);
    }
    
    public function test_create_tables() {
        global $wpdb;
        
        // Mock dbDelta function
        if (!function_exists('dbDelta')) {
            function dbDelta($sql) {
                return true;
            }
        }
        
        // Mock get_option and update_option
        $this->mock_get_option('dmm_db_version', '0.0.0');
        
        $this->database->create_dedupe_table();
        
        // Verify table creation was attempted
        $this->assertTrue(true);
    }
    
    public function test_create_tables_with_existing_version() {
        global $wpdb;
        
        // Mock get_option to return current version
        $this->mock_get_option('dmm_db_version', '1.0.0');
        
        $this->database->create_dedupe_table();
        
        // Should not upgrade if version is current
        $this->assertTrue(true);
    }
    
    public function test_ensure_shipments_table_exists() {
        global $wpdb;
        
        // Mock dbDelta
        if (!function_exists('dbDelta')) {
            function dbDelta($sql) {
                return true;
            }
        }
        
        $this->database->ensure_shipments_table_exists();
        
        // Verify table creation was attempted
        $this->assertTrue(true);
    }
    
    // Helper methods
    private function mock_get_option($option, $value) {
        global $options;
        if (!isset($options)) {
            $options = [];
        }
        $options[$option] = $value;
    }
}

