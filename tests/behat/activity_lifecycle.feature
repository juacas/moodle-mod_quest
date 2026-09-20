@mod @mod_quest
Feature: Quest activity lifecycle and question-bank setting
  In order to run a Quest activity safely
  As a teacher or student
  I need the activity description and its participation settings to be
  available in the normal Moodle activity flow.

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                 |
      | teacher1  | Teacher   | One      | teacher1@example.com  |
      | student1  | Student   | One      | student1@example.com  |
    And the following "courses" exist:
      | fullname  | shortname | category |
      | Course 1  | C1        | 0        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher1  | C1     | editingteacher |
      | student1  | C1     | student        |
    And the following "activities" exist:
      | activity | name    | intro                     | course | idnumber | allowqbankquestions |
      | quest    | Quest 1 | Quest activity description | C1     | quest1   | 1                   |

  Scenario: A student can open a Quest activity and read its description
    When I am on the "Quest 1" "quest activity" page logged in as student1
    Then I should see "Quest 1"
    And I should see "Quest activity description" in the "activity-header" "region"

  @javascript
  Scenario: A teacher can disable question-bank challenges from the activity settings
    Given I am on the "Quest 1" "quest activity editing" page logged in as teacher1
    And I expand all fieldsets
    When I set the field "Allow students to add question bank challenges" to "No"
    And I press "Save and display"
    Then I should see "Quest activity description"
    When I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then the field "Allow students to add question bank challenges" matches value "0"

  Scenario: A teacher can open the Quest activity without student participation data
    When I am on the "Quest 1" "quest activity" page logged in as teacher1
    Then I should see "Quest 1"
    And I should see "Quest activity description"
