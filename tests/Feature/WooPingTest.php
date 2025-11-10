<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WooPingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that /ping endpoint returns 200 and confirms HMAC verification
     */
    public function test_ping_returns_200_and_confirms_hmac(): void
    {
        // Stub global bridge key for deterministic testing
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        
        $tenant = Tenant::factory()->create([
            'require_signed_webhooks' => true,
        ]);
        
        $ts    = now()->timestamp;
        $nonce = Str::random(32);
        $body  = ''; // GET has empty body
        $signed = "{$ts}.{$nonce}.{$body}";
        $sig   = hash_hmac('sha256', $signed, $tenant->getApiSecret());

        $response = $this->withHeaders([
            'X-Api-Key'           => 'TEST_GLOBAL_KEY', // matches config
            'X-Tenant-Id'         => (string) $tenant->id,
            'X-Timestamp'         => (string) $ts,
            'X-Nonce'             => $nonce,
            'X-Payload-Signature' => "sha256={$sig}",
        ])->getJson('/api/woocommerce/ping');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('hmac_verified', true)
            ->assertJsonPath('tenant_id', (string) $tenant->id)
            ->assertJsonPath('ratelimit.limit', 60)
            ->assertJsonStructure([
                'success',
                'message',
                'tenant_id',
                'ip',
                'limiter_key',
                'hmac_verified',
                'time',
                'ratelimit' => [
                    'limit',
                    'remaining',
                    'retry_after',
                    'reset',
                ],
            ]);
    }

    /**
     * Test that /ping works without Content-Type header for GET requests
     */
    public function test_ping_works_without_content_type_for_get(): void
    {
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        
        $tenant = Tenant::factory()->create([
            'require_signed_webhooks' => false, // No signature required for this test
        ]);

        $response = $this->withHeaders([
            'X-Api-Key'   => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id' => (string) $tenant->id,
            // No Content-Type header
        ])->getJson('/api/woocommerce/ping');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * Test that /ping returns 401 when tenant requires signature but none is sent
     */
    public function test_ping_requires_signature_when_tenant_demands_it(): void
    {
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        $tenant = Tenant::factory()->create(['require_signed_webhooks' => true]);

        $this->withHeaders([
            'X-Api-Key'   => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id' => (string) $tenant->id,
        ])->getJson('/api/woocommerce/ping')
          ->assertStatus(401)
          ->assertJsonPath('success', false);
    }

    /**
     * Test that POST requests reject non-JSON Content-Type
     */
    public function test_post_rejects_non_json_content_type(): void
    {
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        $tenant = Tenant::factory()->create(['require_signed_webhooks' => false]);

        $this->withHeaders([
            'X-Api-Key'   => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id' => (string) $tenant->id,
            'Content-Type'=> 'text/plain',
        ])->post('/api/woocommerce/order', 'not json')
          ->assertStatus(415);
    }

    /**
     * Test that rate limit 429 has proper JSON shape
     */
    public function test_woocommerce_rate_limit_429_has_json_shape(): void
    {
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        config(['rate.woocommerce_per_minute' => 2]);

        $tenant = Tenant::factory()->create(['require_signed_webhooks' => false]);

        $headers = [
            'X-Api-Key'   => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id' => (string) $tenant->id,
        ];

        // Burn the 2 tokens
        $this->getJson('/api/woocommerce/ping', $headers)->assertOk();
        $this->getJson('/api/woocommerce/ping', $headers)->assertOk();

        // Third should 429
        $this->getJson('/api/woocommerce/ping', $headers)
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'RATE_LIMIT_EXCEEDED');
    }

    /**
     * Test that HEAD /ping returns 200 and includes rate-limit headers
     */
    public function test_head_ping_returns_headers(): void
    {
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        $tenant = Tenant::factory()->create(['require_signed_webhooks' => false]);

        $res = $this->withHeaders([
            'X-Api-Key'   => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id' => strtoupper((string) $tenant->id), // Test normalization
        ])->head('/api/woocommerce/ping');

        $res->assertOk();
        $this->assertNotEmpty($res->headers->get('X-RateLimit-Limit'));
        $this->assertNotEmpty($res->headers->get('X-RateLimit-Remaining'));
        
        // Verify tenant ID is normalized in response (if returned)
        if ($res->getContent()) {
            $json = json_decode($res->getContent(), true);
            if (isset($json['tenant_id'])) {
                $this->assertEquals(strtolower((string) $tenant->id), $json['tenant_id']);
            }
        }
    }

    /**
     * Test that tenant ID normalization works (uppercase header becomes lowercase)
     */
    public function test_tenant_id_normalization(): void
    {
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        $tenant = Tenant::factory()->create(['require_signed_webhooks' => false]);

        $response = $this->withHeaders([
            'X-Api-Key'   => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id' => strtoupper((string) $tenant->id), // Uppercase
        ])->getJson('/api/woocommerce/ping');

        $response->assertOk()
            ->assertJsonPath('tenant_id', strtolower((string) $tenant->id)); // Normalized to lowercase
    }

    /**
     * Test debug reason codes in local environment - timestamp_skew
     */
    public function test_debug_reason_timestamp_skew(): void
    {
        config(['app.env' => 'local']);
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        
        $tenant = Tenant::factory()->create(['require_signed_webhooks' => true]);
        
        $ts    = now()->subHours(2)->timestamp; // Far in the past
        $nonce = Str::random(32);
        $body  = '';
        $signed = "{$ts}.{$nonce}.{$body}";
        $sig   = hash_hmac('sha256', $signed, $tenant->getApiSecret());

        $response = $this->withHeaders([
            'X-Api-Key'           => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id'         => (string) $tenant->id,
            'X-Timestamp'         => (string) $ts,
            'X-Nonce'             => $nonce,
            'X-Payload-Signature' => "sha256={$sig}",
        ])->getJson('/api/woocommerce/ping');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'timestamp_skew');
    }

    /**
     * Test debug reason codes in local environment - nonce_replay
     */
    public function test_debug_reason_nonce_replay(): void
    {
        config(['app.env' => 'local']);
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        
        $tenant = Tenant::factory()->create(['require_signed_webhooks' => true]);
        
        $ts    = now()->timestamp;
        $nonce = Str::random(32);
        $body  = '';
        $signed = "{$ts}.{$nonce}.{$body}";
        $sig   = hash_hmac('sha256', $signed, $tenant->getApiSecret());

        $headers = [
            'X-Api-Key'           => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id'         => (string) $tenant->id,
            'X-Timestamp'         => (string) $ts,
            'X-Nonce'             => $nonce,
            'X-Payload-Signature' => "sha256={$sig}",
        ];

        // First request succeeds
        $this->withHeaders($headers)->getJson('/api/woocommerce/ping')->assertOk();

        // Second request with same nonce should fail
        $response = $this->withHeaders($headers)->getJson('/api/woocommerce/ping');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'nonce_replay');
    }

    /**
     * Test debug reason codes in local environment - bad_mac
     */
    public function test_debug_reason_bad_mac(): void
    {
        config(['app.env' => 'local']);
        config(['services.dm_bridge.key' => 'TEST_GLOBAL_KEY']);
        
        $tenant = Tenant::factory()->create(['require_signed_webhooks' => true]);
        
        $ts    = now()->timestamp;
        $nonce = Str::random(32);
        $body  = '';
        $signed = "{$ts}.{$nonce}.{$body}";
        $sig   = hash_hmac('sha256', $signed, $tenant->getApiSecret());
        
        // Tamper with the signature
        $tamperedSig = substr($sig, 0, -1) . 'X';

        $response = $this->withHeaders([
            'X-Api-Key'           => 'TEST_GLOBAL_KEY',
            'X-Tenant-Id'         => (string) $tenant->id,
            'X-Timestamp'         => (string) $ts,
            'X-Nonce'             => $nonce,
            'X-Payload-Signature' => "sha256={$tamperedSig}",
        ])->getJson('/api/woocommerce/ping');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'bad_mac');
    }
}

