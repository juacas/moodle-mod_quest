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
      | student2  | Student   | Two      | student2@example.com  |
    And the following "courses" exist:
      | fullname  | shortname | category |
      | Course 1  | C1        | 0        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher1  | C1     | editingteacher |
      | student1  | C1     | student        |
      | student2  | C1     | student        |
    And the following "activities" exist:
      | activity | name    | intro                     | course | idnumber | allowqbankquestions |
      | quest    | Quest 1 | Quest activity description | C1     | quest1   | 1                   |
      | quest    | Quest restricted | Restricted Quest description | C1 | questrestricted | 0 |

  Scenario: A student can open a Quest activity and read its description
    When I am on the "Quest 1" "quest activity" page logged in as student1
    Then I should see "Quest 1"
    And I should see "Quest activity description"

  Scenario: A teacher can disable question-bank challenges from the activity settings
    Given I am on the "Quest 1" "quest activity editing" page logged in as teacher1
    When I set the field "Allow students to add question bank challenges" to "No"
    And I press "Save and display"
    Then I should see "Quest activity description"
    When I navigate to "Settings" in current page administration
    Then the field "Allow students to add question bank challenges" matches value "0"

  Scenario: A teacher can open the Quest activity without student participation data
    When I am on the "Quest 1" "quest activity" page logged in as teacher1
    Then I should see "Quest 1"
    And I should see "Quest activity description"

  Scenario: A student sees only open challenge creation when the question bank is disabled
    When I am on the "Quest restricted" "quest activity" page logged in as student1
    Then I should see "Add challenge"
    And I should not see "Add challenge with question"

  Scenario: A teacher sees the restricted activity without student creation controls
    When I am on the "Quest restricted" "quest activity" page logged in as teacher1
    Then I should see "Quest restricted"
    And I should not see "Add challenge with question"

  Scenario: A teacher sees both actionable states for a challenge awaiting approval
    Given a Quest essay challenge "Essay challenge" created by "student1" is awaiting approval in "Quest 1"
    When I am on the "Essay challenge" "mod_quest > Quest challenge" page logged in as teacher1
    Then I should see "Approval pending"
    And I should see "Not evaluated"
    And I should see "Approve"
    And I should see "Evaluate"

  Scenario: A student author can see the answer assessment after participation
    Given a Quest essay challenge "Essay challenge" created by "student1" is awaiting approval in "Quest 1"
    And the Quest essay challenge "Essay challenge" has been approved and assessed by "teacher1"
    And student "student2" has answered Quest essay challenge "Essay challenge" and its author has assessed it
    When I am on the "Essay challenge" "mod_quest > Quest challenge" page logged in as student1
    Then I should see "Essay challenge"
    And I should see "Essay answer"
    And I should see "Assessed"
