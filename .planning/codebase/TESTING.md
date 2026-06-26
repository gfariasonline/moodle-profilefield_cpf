# Testing Patterns

**Analysis Date:** 2026-06-26

## Test Framework

**Runner:**
- PHPUnit (via Moodle's bundled vendor/bin/phpunit)
- Version: PHPUnit 10+ (as of Moodle 4.5+)
- Config: Inherited from Moodle root `phpunit.xml`

**Assertion Library:**
- PHPUnit's built-in assertions: `$this->assertEquals()`, `$this->assertTrue()`, `$this->assertFalse()`, etc.
- Moodle extensions: `$this->getDataGenerator()` for test fixtures

**Run Commands:**
```bash
cd ../../..  # Change to Moodle root

# Run all tests for this plugin
vendor/bin/phpunit --filter profilefield_cpf

# Run specific test file
vendor/bin/phpunit user/profile/field/cpf/tests/myclass_test.php

# Run specific test method
vendor/bin/phpunit --filter test_validate_cpf

# With debug output (shows each test name)
vendor/bin/phpunit --debug --filter profilefield_cpf

# With coverage report
vendor/bin/phpunit --coverage-text --filter profilefield_cpf
```

## Test File Organization

**Location:**
- Tests co-located with source code in `tests/` directory at plugin root
- Pattern: `tests/{classname}_test.php` corresponds to `{classname}.class.php`

**Naming:**
- Test file: `{ClassName}_test.php` (e.g., `field_test.php` for `field.class.php`)
- Test class: `{ClassName}_test` extending `\advanced_testcase`
- Test methods: `test_{functionality}()` (e.g., `test_validate_cpf()`, `test_cpf_is_unique()`)

**Structure:**
```
tests/
├── field_test.php          # Tests for profile_field_cpf
├── define_test.php         # Tests for profile_define_cpf
└── behat/
    └── cpf_field.feature   # Acceptance tests
```

## Test Structure

**Suite Organization:**
```php
<?php
namespace profilefield_cpf;
defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for field.class.php
 *
 * @package   profilefield_cpf
 * @copyright 2026 Thiago Serrao
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \profile_field_cpf
 */
class field_test extends \advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_validate_cpf_valid(): void {
        // Arrange
        $field = $this->get_test_field();
        $cpf = '11144477735';  // Valid CPF
        
        // Act
        $result = $field->validate_cpf($cpf);
        
        // Assert
        $this->assertTrue($result);
    }
}
```

**Patterns:**
- `setUp()`: Called before each test method; call `$this->resetAfterTest()` to isolate tests
- `tearDown()`: Called after each test (inherited); use only if cleanup is needed beyond reset
- Test discovery: PHPUnit finds all `public function test_*()` methods
- Assertion: Calls happen in "Act" phase after "Arrange" phase

## Mocking

**Framework:**
- PHPUnit's built-in mocking via `$this->createMock(ClassName::class)`
- Moodle mocking: Use `$this->getDataGenerator()` for test fixtures instead of mocking
- Database: Tests run against test database (configured by `resetAfterTest()`)

**Patterns:**
```php
// Creating test data instead of mocking
$course = $this->getDataGenerator()->create_course();
$user = $this->getDataGenerator()->create_user();

// Database is automatically reset, no need to mock
global $DB;
$result = $DB->get_record('user', ['id' => $user->id]);
$this->assertNotNull($result);
```

**What to Mock:**
- External APIs (if plugin integrates with external services)
- File system operations (create temporary files in Moodle's temp directory)
- Clock/time functions (use Moodle's time-freezing if needed)

**What NOT to Mock:**
- Database operations (use test database with `resetAfterTest()`)
- Moodle core functions (`get_string()`, `format_string()`, etc.)
- User and course creation (use `getDataGenerator()`)

## Fixtures and Factories

**Test Data:**
```php
// Create test field and user
$field = $this->get_cpf_field();
$user = $this->getDataGenerator()->create_user(['email' => 'test@example.com']);

// Set CPF value and save
$userupdate = (object)[
    'id' => $user->id,
    'profile_field_cpf' => '11144477735',
];
profile_save_data($userupdate);
```

**Location:**
- Fixtures go in test class methods (setUp, individual tests)
- Shared fixtures: Create protected helper methods
- Example: `protected function get_cpf_field()` to create a CPF profile field

## Coverage

**Requirements:**
- No coverage requirement enforced at plugin level
- Moodle CI runs coverage via `moodle-plugin-ci phpunit --coverage-text`

**View Coverage:**
```bash
cd ../../..
vendor/bin/phpunit --coverage-text user/profile/field/cpf/tests/field_test.php
```

**Coverage Goals:**
- Aim for >80% coverage of public methods
- At minimum, cover critical validation logic (CPF validation, uniqueness checks)
- Edge cases: invalid format, empty values, duplicate detection

## Test Types

**Unit Tests:**
- Scope: Single method or function in isolation
- Approach: Test validation methods (`validate_cpf()`, `normalize_cpf()`, etc.) directly
- Location: `tests/{classname}_test.php`
- Example:
  ```php
  public function test_normalize_cpf_removes_formatting(): void {
      $field = $this->get_test_field();
      $formatted = '111.444.777-35';
      
      $result = $field->normalize_cpf($formatted);
      
      $this->assertEquals('11144477735', $result);
  }
  ```

**Integration Tests:**
- Scope: Multiple components working together (form submission, database storage)
- Approach: Test full user profile form submission with CPF field
- Example:
  ```php
  public function test_edit_validate_field_with_duplicate(): void {
      $user1 = $this->getDataGenerator()->create_user();
      // Set CPF for user1...
      
      $user2 = $this->getDataGenerator()->create_user();
      $usernew = (object)['id' => $user2->id, $this->fieldname => '11144477735'];
      
      $errors = $field->edit_validate_field($usernew);
      
      $this->assertArrayHasKey($this->fieldname, $errors);
  }
  ```

**E2E/Acceptance Tests:**
- Framework: Behat (Gherkin syntax)
- Location: `tests/behat/{feature_name}.feature`
- Example:
  ```gherkin
  @profilefield_cpf @javascript
  Feature: CPF profile field
    
    Scenario: User can enter CPF in profile
      Given I log in as "admin"
      When I edit my profile
      And I enter "111.444.777-35" in the CPF field
      And I click "Save changes"
      Then I should see "111.444.777-35" in my profile
  ```

**Run Behat:**
```bash
# From Moodle root
php admin/tool/behat/cli/util.php --enable

vendor/bin/behat \
  --config moodledata_behat/behatrun/behat/behat.yml \
  --tags=@profilefield_cpf
```

## Common Patterns

**Async Testing:**
- Not applicable (PHP plugin, no async code)

**Error Testing:**
```php
/**
 * @dataProvider invalid_cpf_provider
 */
public function test_validate_cpf_invalid(string $cpf): void {
    $field = $this->get_test_field();
    
    $result = $field->validate_cpf($cpf);
    
    $this->assertFalse($result);
}

public static function invalid_cpf_provider(): array {
    return [
        'all zeros'        => ['00000000000'],
        'repeated digits'  => ['11111111111'],
        'wrong check digit' => ['11144477736'],
        'short input'      => ['111'],
    ];
}
```

**Data Provider Pattern (PHPUnit 10+):**
```php
/**
 * @dataProvider normalize_cpf_provider
 */
public function test_normalize_cpf(string $input, string $expected): void {
    $field = $this->get_test_field();
    
    $result = $field->normalize_cpf($input);
    
    $this->assertEquals($expected, $result);
}

public static function normalize_cpf_provider(): array {
    return [
        'formatted cpf'  => ['input' => '111.444.777-35', 'expected' => '11144477735'],
        'unformatted'    => ['input' => '11144477735', 'expected' => '11144477735'],
        'null input'     => ['input' => null, 'expected' => ''],
    ];
}
```

## Test Utilities

**Base Test Class:**
- All tests extend `\advanced_testcase` (Moodle's test base class)
- Provides:
  - `$this->getDataGenerator()` - Create test data (users, courses, etc.)
  - `$this->resetAfterTest()` - Isolate database changes
  - All standard PHPUnit assertions

**Helper Methods:**
Create protected helper methods in test class:
```php
protected function get_cpf_field(): profile_field_cpf {
    global $DB;
    
    // Create or fetch test field definition
    $field = $DB->get_record('user_info_field', ['datatype' => 'cpf']);
    if (!$field) {
        $field = (object)[
            'name' => 'CPF',
            'datatype' => 'cpf',
            'required' => 0,
        ];
        $field->id = $DB->insert_record('user_info_field', $field);
    }
    
    return new profile_field_cpf($field);
}
```

## Running Tests Locally

**PHPUnit Setup:**
```bash
cd ../../..

# First time: Check if phpunit.xml exists
ls phpunit.xml

# Run plugin tests
vendor/bin/phpunit --filter profilefield_cpf

# Run with verbose output
vendor/bin/phpunit --verbose --filter profilefield_cpf
```

**With moodle-plugin-ci:**
```bash
# From plugin root
moodle-plugin-ci phpunit --coverage-text
```

## CI Matrix

**Tested Against:**
- PHP 8.2, 8.3
- Moodle 4.5, 5.0, 5.1, 5.2
- Databases: MariaDB 10.11, PostgreSQL 14+

Tests run automatically on:
- Pull requests
- Pushes to main/master
- Pushes to MOODLE_*_STABLE branches

---

*Testing analysis: 2026-06-26*
