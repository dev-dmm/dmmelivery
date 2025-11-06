# Testing Guide for DMM Delivery Bridge Plugin

## Overview

This document provides guidance on running and extending the unit tests for the DMM Delivery Bridge WordPress plugin.

## Test Implementation Notes

### Current Test Structure

The tests are structured as unit tests that mock WordPress and WooCommerce dependencies. However, some adjustments may be needed for full functionality:

1. **WordPress Function Mocking**: The bootstrap file provides basic mocks, but for comprehensive testing, consider using:
   - [Brain Monkey](https://github.com/Brain-WP/BrainMonkey) - WordPress function mocking
   - [WP_Mock](https://github.com/10up/wp_mock) - WordPress testing framework
   - Dependency injection in the classes to make them more testable

2. **WooCommerce Mocking**: For WooCommerce order objects, consider:
   - Using PHPUnit mocks (as shown in tests)
   - Creating test fixtures for common order scenarios
   - Using WooCommerce test helpers if available

3. **Database Testing**: The database tests mock `$wpdb`. For integration testing:
   - Use WordPress test suite database setup
   - Consider using in-memory SQLite for faster tests
   - Use database transactions that rollback after tests

## Running Tests

### Basic Setup

1. Install PHPUnit:
```bash
composer require --dev phpunit/phpunit
```

2. Run tests from plugin directory:
```bash
cd dmm_wordpress_plugin
../vendor/bin/phpunit tests/
```

### Recommended Improvements

To make the tests fully functional, consider these enhancements:

#### 1. Add Brain Monkey for WordPress Mocking

```bash
composer require --dev brain/wpunit
```

Then update `bootstrap.php`:
```php
use Brain\Monkey;
use Brain\Monkey\Functions;

Monkey\setUp();
Functions\when('get_option')->returnArg(2);
Functions\when('update_option')->justReturn(true);
// ... more mocks
```

#### 2. Refactor Classes for Better Testability

Consider adding dependency injection to classes:

```php
// Instead of:
$logger = new DMM_Logger($this->options);

// Use:
public function __construct($options = [], $logger = null) {
    $this->logger = $logger ?: new DMM_Logger($this->options);
}
```

This pattern is already used in some classes - extend it to all.

#### 3. Create Test Fixtures

Create reusable test data:

```php
// tests/Fixtures/OrderFixture.php
class OrderFixture {
    public static function createMockOrder($id = 123) {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($id);
        // ... more setup
        return $order;
    }
}
```

## Test Coverage Goals

Current test coverage includes:

- ✅ API client methods (send_to_api, retry logic, circuit breaker)
- ✅ Order processing logic (queue, process, prepare data)
- ✅ Courier provider validation (all providers)
- ✅ Database operations (voucher deduplication)

### Additional Tests to Consider

1. **Integration Tests**:
   - Full order processing flow
   - API communication with real endpoints (in test environment)
   - Database operations with real database

2. **Edge Cases**:
   - Very large orders
   - Orders with missing data
   - Network failures
   - API timeout scenarios

3. **Performance Tests**:
   - Bulk order processing
   - Rate limiting under load
   - Cache effectiveness

## Continuous Integration

### GitHub Actions Example

```yaml
name: PHPUnit Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['7.4', '8.0', '8.1', '8.2']
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mbstring, xml, curl
    
    - name: Install dependencies
      run: |
        cd dmm_wordpress_plugin
        composer install --prefer-dist --no-progress
    
    - name: Run PHPUnit
      run: |
        cd dmm_wordpress_plugin
        ../vendor/bin/phpunit tests/ --coverage-clover=coverage.xml
    
    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: ./dmm_wordpress_plugin/coverage.xml
```

## Best Practices

1. **Test Naming**: Use descriptive names that explain what is being tested
   - ✅ `test_send_to_api_with_rate_limit_exceeded`
   - ❌ `test_api_1`

2. **Arrange-Act-Assert**: Structure tests clearly
   ```php
   // Arrange
   $input = 'test';
   
   // Act
   $result = $subject->process($input);
   
   // Assert
   $this->assertEquals('expected', $result);
   ```

3. **One Assertion Per Test**: When possible, test one thing per test method

4. **Mock External Dependencies**: Don't make real API calls or database connections in unit tests

5. **Test Edge Cases**: Include tests for error conditions, null values, empty strings, etc.

## Troubleshooting

### "Class not found" errors
- Ensure bootstrap.php loads all required classes
- Check autoloading is configured correctly

### WordPress function errors
- Verify mocks are set up in bootstrap.php
- Consider using Brain Monkey or WP_Mock for better mocking

### WooCommerce errors
- Ensure WC_Order mock is properly configured
- Check that all required methods are mocked

### Database errors
- Unit tests should mock $wpdb, not use real database
- For integration tests, use WordPress test database setup

## Next Steps

1. ✅ Basic test structure created
2. ⏳ Add WordPress mocking framework (Brain Monkey or WP_Mock)
3. ⏳ Refactor classes for better dependency injection
4. ⏳ Add integration tests
5. ⏳ Set up CI/CD pipeline
6. ⏳ Add code coverage reporting

