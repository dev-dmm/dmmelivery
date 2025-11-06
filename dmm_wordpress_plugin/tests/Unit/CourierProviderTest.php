<?php
/**
 * Unit Tests for Courier Provider Validation
 *
 * @package DMM_Delivery_Bridge
 */

use PHPUnit\Framework\TestCase;
use DMM\Courier\AcsProvider;
use DMM\Courier\GenikiProvider;
use DMM\Courier\EltaProvider;
use DMM\Courier\GenericProvider;
use DMM\Courier\SpeedexProvider;
use DMM\Courier\Registry;

class CourierProviderTest extends TestCase {
    
    private $mock_order;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Create mock order
        $this->mock_order = $this->createMock(WC_Order::class);
        $this->mock_order->method('get_id')->willReturn(123);
        $this->mock_order->method('get_order_number')->willReturn('12345');
        $this->mock_order->method('get_billing_phone')->willReturn('2101234567');
        $this->mock_order->method('get_shipping_phone')->willReturn('2101234567');
    }
    
    // ACS Provider Tests
    public function test_acs_provider_id() {
        $provider = new AcsProvider();
        $this->assertEquals('acs', $provider->id());
    }
    
    public function test_acs_provider_label() {
        $provider = new AcsProvider();
        $this->assertEquals('ACS', $provider->label());
    }
    
    public function test_acs_looks_like_valid() {
        $provider = new AcsProvider();
        $this->assertTrue($provider->looksLike('1234567890'));
        $this->assertTrue($provider->looksLike('123456789012'));
        $this->assertTrue($provider->looksLike('12-34-56-78-90'));
    }
    
    public function test_acs_looks_like_invalid() {
        $provider = new AcsProvider();
        $this->assertFalse($provider->looksLike('123456789')); // Too short
        $this->assertFalse($provider->looksLike('1234567890123')); // Too long
        $this->assertFalse($provider->looksLike('ABC123'));
    }
    
    public function test_acs_normalize() {
        $provider = new AcsProvider();
        $this->assertEquals('1234567890', $provider->normalize('12-34-56-78-90'));
        $this->assertEquals('1234567890', $provider->normalize('12 34 56 78 90'));
        $this->assertEquals('1234567890', $provider->normalize('1234567890'));
    }
    
    public function test_acs_validate_valid() {
        $provider = new AcsProvider();
        $result = $provider->validate('1234567890', $this->mock_order);
        
        $this->assertTrue($result[0]);
        $this->assertStringContainsString('Valid', $result[1]);
    }
    
    public function test_acs_validate_invalid_format() {
        $provider = new AcsProvider();
        $result = $provider->validate('12345', $this->mock_order);
        
        $this->assertFalse($result[0]);
        $this->assertStringContainsString('format', $result[1]);
    }
    
    public function test_acs_validate_sequential_pattern() {
        $provider = new AcsProvider();
        $result = $provider->validate('0000000000', $this->mock_order);
        
        $this->assertFalse($result[0]);
        $this->assertStringContainsString('pattern', $result[1]);
    }
    
    public function test_acs_validate_looks_like_phone() {
        $provider = new AcsProvider();
        $order = $this->createMock(WC_Order::class);
        $order->method('get_id')->willReturn(123);
        $order->method('get_order_number')->willReturn('12345');
        $order->method('get_billing_phone')->willReturn('2101234567');
        $order->method('get_shipping_phone')->willReturn('2101234567');
        
        $result = $provider->validate('2101234567', $order);
        
        $this->assertFalse($result[0]);
        $this->assertStringContainsString('phone', $result[1]);
    }
    
    public function test_acs_validate_looks_like_order_number() {
        $provider = new AcsProvider();
        $order = $this->createMock(WC_Order::class);
        $order->method('get_id')->willReturn(123);
        $order->method('get_order_number')->willReturn('1234567890');
        $order->method('get_billing_phone')->willReturn('');
        $order->method('get_shipping_phone')->willReturn('');
        
        $result = $provider->validate('1234567890', $order);
        
        $this->assertFalse($result[0]);
        $this->assertStringContainsString('order number', $result[1]);
    }
    
    // Geniki Provider Tests
    public function test_geniki_provider_id() {
        $provider = new GenikiProvider();
        $this->assertEquals('geniki', $provider->id());
    }
    
    public function test_geniki_looks_like_valid() {
        $provider = new GenikiProvider();
        $this->assertTrue($provider->looksLike('12345678'));
        $this->assertTrue($provider->looksLike('123456789012'));
    }
    
    public function test_geniki_looks_like_invalid() {
        $provider = new GenikiProvider();
        $this->assertFalse($provider->looksLike('1234567')); // Too short
        $this->assertFalse($provider->looksLike('00000000')); // Sequential zeros
        $this->assertFalse($provider->looksLike('12345678')); // Sequential pattern
    }
    
    public function test_geniki_validate_valid() {
        $provider = new GenikiProvider();
        $result = $provider->validate('87654321', $this->mock_order);
        
        $this->assertTrue($result[0]);
        $this->assertStringContainsString('Valid', $result[1]);
    }
    
    public function test_geniki_validate_invalid_format() {
        $provider = new GenikiProvider();
        $result = $provider->validate('12345', $this->mock_order);
        
        $this->assertFalse($result[0]);
        $this->assertStringContainsString('format', $result[1]);
    }
    
    // ELTA Provider Tests
    public function test_elta_provider_id() {
        $provider = new EltaProvider();
        $this->assertEquals('elta', $provider->id());
    }
    
    public function test_elta_looks_like_valid() {
        $provider = new EltaProvider();
        $this->assertTrue($provider->looksLike('123456789'));
        $this->assertTrue($provider->looksLike('1234567890123'));
    }
    
    public function test_elta_validate_valid() {
        $provider = new EltaProvider();
        $result = $provider->validate('987654321', $this->mock_order);
        
        $this->assertTrue($result[0]);
        $this->assertStringContainsString('Valid', $result[1]);
    }
    
    public function test_elta_validate_invalid_format() {
        $provider = new EltaProvider();
        $result = $provider->validate('12345', $this->mock_order);
        
        $this->assertFalse($result[0]);
        $this->assertStringContainsString('format', $result[1]);
    }
    
    // Generic Provider Tests
    public function test_generic_provider_id() {
        $provider = new GenericProvider();
        $this->assertEquals('generic', $provider->id());
    }
    
    public function test_generic_looks_like_valid() {
        $provider = new GenericProvider();
        $this->assertTrue($provider->looksLike('ABC12345'));
        $this->assertTrue($provider->looksLike('12345678'));
        $this->assertTrue($provider->looksLike('ABC-123-XYZ'));
    }
    
    public function test_generic_looks_like_invalid() {
        $provider = new GenericProvider();
        $this->assertFalse($provider->looksLike('ABC')); // Too short
        $this->assertFalse($provider->looksLike('123456789012345678901')); // Too long
    }
    
    public function test_generic_validate_valid() {
        $provider = new GenericProvider();
        $result = $provider->validate('ABC12345', $this->mock_order);
        
        $this->assertTrue($result[0]);
        $this->assertStringContainsString('Valid', $result[1]);
    }
    
    public function test_generic_validate_invalid() {
        $provider = new GenericProvider();
        $result = $provider->validate('ABC', $this->mock_order);
        
        $this->assertFalse($result[0]);
        $this->assertStringContainsString('short', $result[1]);
    }
    
    // Speedex Provider Tests
    public function test_speedex_provider_id() {
        $provider = new SpeedexProvider();
        $this->assertEquals('speedex', $provider->id());
    }
    
    public function test_speedex_looks_like_valid() {
        $provider = new SpeedexProvider();
        $this->assertTrue($provider->looksLike('1234567890'));
        $this->assertTrue($provider->looksLike('12345678901234'));
    }
    
    public function test_speedex_validate_valid() {
        $provider = new SpeedexProvider();
        $result = $provider->validate('1234567890', $this->mock_order);
        
        $this->assertTrue($result[0]);
        $this->assertStringContainsString('Valid', $result[1]);
    }
    
    // Registry Tests
    public function test_registry_register_and_get() {
        Registry::clear();
        
        $provider = new AcsProvider();
        Registry::register($provider);
        
        $this->assertTrue(Registry::has('acs'));
        $this->assertEquals($provider, Registry::get('acs'));
    }
    
    public function test_registry_get_nonexistent() {
        Registry::clear();
        
        $this->assertNull(Registry::get('nonexistent'));
        $this->assertFalse(Registry::has('nonexistent'));
    }
    
    public function test_registry_all() {
        Registry::clear();
        
        $acs = new AcsProvider();
        $geniki = new GenikiProvider();
        
        Registry::register($acs);
        Registry::register($geniki);
        
        $all = Registry::all();
        $this->assertCount(2, $all);
        $this->assertContains($acs, $all);
        $this->assertContains($geniki, $all);
    }
    
    public function test_registry_ids() {
        Registry::clear();
        
        Registry::register(new AcsProvider());
        Registry::register(new GenikiProvider());
        Registry::register(new EltaProvider());
        
        $ids = Registry::ids();
        $this->assertContains('acs', $ids);
        $this->assertContains('geniki', $ids);
        $this->assertContains('elta', $ids);
    }
    
    public function test_registry_clear() {
        Registry::register(new AcsProvider());
        Registry::clear();
        
        $this->assertEmpty(Registry::all());
        $this->assertFalse(Registry::has('acs'));
    }
    
    // Route method tests
    public function test_provider_route() {
        $provider = new AcsProvider();
        $payload = ['order_id' => 123];
        
        $routed = $provider->route($payload);
        
        $this->assertEquals('acs', $routed['courier']);
        $this->assertEquals('acs_tracking', $routed['api_endpoint']);
        $this->assertEquals(123, $routed['order_id']);
    }
}

