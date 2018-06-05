@mod @mod_assign
Feature: Activity completion visual notifications for assignment submissions
  As a teacher
  I need to set activity completion settings for assignments to provide visual
  notifications to students submitting assignments.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode | enablecompletion |
      | Course 1 | C1 | 0 | 1 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  @javascript
  Scenario: Test disabled assignment activity completion
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | A1 - Do not indicate activity completion |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_file_enabled | 0 |
      | completion                    | 0 |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "A1 - Do not indicate activity completion"
    And "Not completed" "icon" should not exist
    And I log out

  @javascript
  Scenario: Test manual assignment activity completion
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | A2 - Students can manually mark the activity as completed Assignment |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_file_enabled | 0 |
      | completion                    | 1 |
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should see "A2 - Students can manually mark the activity as completed Assignment"
    And "Not completed" "icon" should exist
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "A2 - Students can manually mark the activity as completed Assignment"
    And "Not completed" "icon" should exist
    And I click on "Not completed" "icon"
    And I log out

  @javascript
  Scenario: Test automatic assignment activity completion view activity changing to submit activity
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | A3 - Activity completion with view activity |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_file_enabled | 0 |
      | completion                    | 2 |
      | completionview                | 1 |
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should see "A3 - Activity completion with view activity"
    And "Not completed" "icon" should exist
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "A3 - Activity completion with view activity"
    And "Not completed" "icon" should exist
    And I follow "A3 - Activity completion with view activity"
    And I am on "Course 1" course homepage
    And "Completed: A3 - Activity completion with view activity" "icon" should exist
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "A3 - Activity completion with view activity"
    And I navigate to "Edit settings" in current page administration
    And I follow "Expand all"
    And I should see "Completion options locked"
    And I press "Unlock completion options"
    And I set the following fields to these values:
      | Assignment name | A3 - Activity completion changed from view activity to submit activity |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_file_enabled | 0 |
      | completion                    | 2 |
      | completionview                | 0 |
      | completionsubmit              | 0 |
    And I press "Save and display"
    And I should see "When you select automatic completion, you must also enable at least one requirement (below)."
    And I set the following fields to these values:
      | completionsubmit              | 1 |
    And I press "Save and display"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And "Not completed" "icon" should exist
    And I follow "A3 - Activity completion changed from view activity to submit activity"
    And I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student1 first submission |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And "Completed: A3 - Activity completion changed from view activity to submit activity" "icon" should exist
    And I log out

  @javascript
  Scenario: Test automatic assignment activity completion with student grade required
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | A4 - Student must receive a grade to complete this activity |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_file_enabled | 0 |
      | completion                    | 2 |
      | completionview                | 0 |
      | completionusegrade            | 1 |
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should see "A4 - Student must receive a grade to complete this activity"
    And "Not completed" "icon" should exist
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "A4 - Student must receive a grade to complete this activity"
    And "Not completed" "icon" should exist
    And I follow "A4 - Student must receive a grade to complete this activity"
    And I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student1 first submission |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And "Not completed" "icon" should exist
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "A4 - Student must receive a grade to complete this activity"
    And I navigate to "View all submissions" in current page administration
    And I click on "Grade" "link" in the "Student 1" "table_row"
    And I set the following fields to these values:
      | Grade out of 100 | 50.0 |
      | Feedback comments | I'm the teacher first feedback |
    And I press "Save changes"
    And I press "Ok"
    And I follow "Course 1"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And "Completed: A4 - Student must receive a grade to complete this activity" "icon" should exist
    And I log out

  @javascript
  Scenario: Test automatic assignment activity completion with student grade required with grade pass
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | A5 - Require passing grade |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_file_enabled | 0 |
      | completion                    | 2 |
      | completionview                | 0 |
      | completionusegrade            | 1 |
      | completionpass                | 1 |
    And I press "Save and display"
    And I should see "This assignment does not have a grade to pass set so you cannot use this option. Please set the require grade setting first."
    And I set the following fields to these values:
      | gradepass                     | -50.0 |
    And I press "Save and display"
    And I should see "Grade to pass cannot be less than zero as this assignment has its completion method set to require passing grade. Please set a positive numeric value."
    And I set the following fields to these values:
      | gradepass                     | 50.0 |
    And I press "Save and display"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should see "A5 - Require passing grade"
    And "Not completed" "icon" should exist
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "A5 - Require passing grade"
    And "Not completed" "icon" should exist
    And I follow "A5 - Require passing grade"
    And I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student1 first submission |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And "Not completed" "icon" should exist
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "A5 - Require passing grade"
    And I navigate to "View all submissions" in current page administration
    And I click on "Grade" "link" in the "Student 1" "table_row"
    And I set the following fields to these values:
      | Grade out of 100 | 49.99 |
      | Feedback comments | I'm the teacher first feedback |
    And I press "Save changes"
    And I press "Ok"
    And I follow "Course 1"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And "Completed: A5 - Require passing grade (did not achieve pass grade)" "icon" should exist
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "A5 - Require passing grade"
    And I navigate to "View all submissions" in current page administration
    And I click on "Grade" "link" in the "Student 1" "table_row"
    And I set the following fields to these values:
      | Grade out of 100 | 50.01 |
      | Feedback comments | I'm the teacher second feedback |
    And I press "Save changes"
    And I press "Ok"
    And I follow "Course 1"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And "Completed: A5 - Require passing grade (achieved pass grade)" "icon" should exist
    And I log out

  @javascript
  Scenario: Test automatic assignment activity completion with student submission required
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | A6 - Student must submit to this activity to complete it |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_file_enabled | 0 |
      | completion                    | 2 |
      | completionview                | 0 |
      | completionsubmit              | 1 |
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should see "A6 - Student must submit to this activity to complete it"
    And "Not completed" "icon" should exist
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "A6 - Student must submit to this activity to complete it"
    And "Not completed" "icon" should exist
    And I follow "A6 - Student must submit to this activity to complete it"
    And I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student1 first submission |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And "Completed: A6 - Student must submit to this activity to complete it" "icon" should exist
    And I log out
