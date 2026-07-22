@profilefield @profilefield_cpf @javascript
Feature: CPF profile field masking
  In order to enter a Brazilian CPF reliably
  As a Moodle user
  I need the CPF profile field to format my input without inline JavaScript.

  Background:
    Given the following "custom profile fields" exist:
      | datatype | shortname | name | visible |
      | cpf      | cpf       | CPF  | 2       |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | cpfuser  | CPF       | User     | cpfuser@example.com |

  Scenario: A user enters and saves a formatted CPF
    Given I log in as "cpfuser"
    And I follow "Profile" in the user menu
    When I click on "Edit profile" "link" in the "region-main" "region"
    And I set the following fields to these values:
      | CPF | 52998224725 |
    Then the following fields match these values:
      | CPF | 529.982.247-25 |
    When I click on "Update profile" "button"
    Then I should see "529.982.247-25"
