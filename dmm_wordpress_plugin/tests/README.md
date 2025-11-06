# DMM Delivery Bridge Plugin - Unit Tests

This directory contains PHPUnit unit tests for the DMM Delivery Bridge WordPress plugin.

## Test Coverage

The test suite covers the following components:

### 1. API Client Tests (`ApiClientTest.php`)
- API configuration validation
- Circuit breaker functionality
- Rate limiting handling
- Successful API requests
- Retry logic for 429 (rate limit) responses
- Retry-After header handling
- Error classification (retryable vs non-retryable)
- Cache integration
- Duplicate request handling (409)

### 2. Order Processor Tests (`OrderProcessorTest.php`)
- Queue order for sending (with various conditions)
- Auto-send configuration
- Order status validation
- Already-sent order detection
- Robust order processing
- Success handling
- Retry logic on failures
- Maximum retries reached
- Order data preparation

### 3. Courier Provider Tests (`CourierProviderTest.php`)
- ACS Provider validation
- Geniki Provider validation
- ELTA Provider validation
- Generic Provider validation
- Speedex Provider validation
- Provider Registry functionality
- Voucher format detection (`looksLike`)
- Voucher normalization
- Voucher validation (format, patterns, phone/order number detection)
- Route method testing

### 4. Database Tests (`DatabaseTest.php`)
- Voucher deduplication (`has_processed_voucher`)
- Mark voucher as processed (`mark_voucher_processed`)
- Table creation
- Database version management

## Running Tests

### Prerequisites

1. Install PHPUnit (if not already installed):
```bash
composer require --dev phpunit/phpunit
```

2. Ensure you're in the plugin directory:
```bash
cd dmm_wordpress_plugin
```

### Run All Tests

```bash
./vendor/bin/phpunit tests/
```

### Run Specific Test Suite

```bash
# Run only unit tests
./vendor/bin/phpunit tests/Unit/

# Run specific test file
./vendor/bin/phpunit tests/Unit/ApiClientTest.php

# Run specific test method
./vendor/bin/phpunit tests/Unit/ApiClientTest.php --filter test_send_to_api_success
```

### Run with Coverage

```bash
./vendor/bin/phpunit tests/ --coverage-html coverage/
```

## Test Structure

```
tests/
├── bootstrap.php          # Test bootstrap file
├── phpunit.xml            # PHPUnit configuration
├── README.md              # This file
└── Unit/
    ├── ApiClientTest.php
    ├── OrderProcessorTest.php
    ├── CourierProviderTest.php
    └── DatabaseTest.php
```

## Writing New Tests

When adding new tests:

1. **Follow naming conventions**: Test class names should end with `Test` and match the class being tested
2. **Use descriptive test names**: Test method names should describe what they're testing (e.g., `test_send_to_api_with_rate_limit_exceeded`)
3. **Mock dependencies**: Use PHPUnit mocks for WordPress functions and dependencies
4. **Test edge cases**: Include tests for error conditions, boundary values, and edge cases
5. **Keep tests isolated**: Each test should be independent and not rely on other tests

### Example Test Structure

```php
<?php
use PHPUnit\Framework\TestCase;

class MyClassTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        // Setup code here
    }
    
    public function test_feature_under_test() {
        // Arrange
        $input = 'test';
        
        // Act
        $result = $this->subject->method($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

## Mocking WordPress Functions

Since these are unit tests (not integration tests), WordPress functions are mocked. The bootstrap file provides basic mocks, but you may need to extend them for specific test cases.

### Common WordPress Functions to Mock

- `get_option()` - Get WordPress option
- `update_option()` - Update WordPress option
- `get_transient()` - Get transient
- `set_transient()` - Set transient
- `delete_transient()` - Delete transient
- `wp_remote_request()` - HTTP requests
- `wc_get_order()` - Get WooCommerce order
- `current_time()` - Get current time

## Continuous Integration

These tests can be integrated into CI/CD pipelines. Example GitHub Actions workflow:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: php-actions/composer@v6
      - name: Run PHPUnit
        run: ./vendor/bin/phpunit tests/
```

## Notes

- Tests use mocks for WordPress and WooCommerce functions to keep them fast and isolated
- For integration tests that require a full WordPress environment, consider using WordPress PHPUnit test suite
- Some tests may need adjustment based on actual WordPress/WooCommerce API behavior
- Database tests mock `$wpdb` - for real database testing, use WordPress test database setup

## Troubleshooting

### Tests fail with "Class not found"
- Ensure the bootstrap file is loading all required classes
- Check that all dependencies are included in the bootstrap

### WordPress function errors
- Verify that WordPress function mocks are properly set up in the bootstrap
- Some functions may need additional mocking in specific tests

### Database errors
- Database tests use mocked `$wpdb` - they don't actually connect to a database
- For real database testing, use WordPress test suite setup

