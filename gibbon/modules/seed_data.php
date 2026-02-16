#!/usr/bin/env php
<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Development Seed Data Script
 *
 * Creates sample data for development and testing:
 * - Organization and school year
 * - Form groups (roll groups)
 * - Staff members
 * - Student enrollments
 * - Care records (attendance, meals, naps, diapers, incidents, activities)
 *
 * This script is idempotent - it checks for existing data before inserting.
 *
 * Usage: php seed_data.php [options]
 *
 * Options:
 *   --reset       Delete existing seed data before creating new
 *   --verbose     Show detailed output
 *   --help        Show this help message
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Ensure CLI only
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse command line options
$options = getopt('', ['reset', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "Development Seed Data Script\n";
    echo "============================\n\n";
    echo "Usage: php seed_data.php [options]\n\n";
    echo "Options:\n";
    echo "  --reset       Delete existing seed data before creating new\n";
    echo "  --verbose     Show detailed output\n";
    echo "  --help        Show this help message\n\n";
    exit(0);
}

$reset = isset($options['reset']);
$verbose = isset($options['verbose']);

// Helper function for output
function output($message, $forceOutput = false) {
    global $verbose;
    if ($verbose || $forceOutput) {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }
}

// Find and load Gibbon bootstrap
$gibbonPath = realpath(__DIR__ . '/../..');
if (!$gibbonPath || !file_exists($gibbonPath . '/gibbon.php')) {
    // Try alternative paths
    $possiblePaths = [
        __DIR__ . '/../..',
        __DIR__ . '/../../..',
        dirname(__DIR__, 2),
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path . '/gibbon.php')) {
            $gibbonPath = realpath($path);
            break;
        }
    }
}

if (!$gibbonPath || !file_exists($gibbonPath . '/gibbon.php')) {
    die("Error: Could not find Gibbon installation. Searched in:\n" .
        " - " . realpath(__DIR__ . '/../..') . "\n" .
        " - Current directory: " . getcwd() . "\n");
}

output("Found Gibbon at: $gibbonPath", true);

// Load Gibbon core
$_SERVER['SCRIPT_NAME'] = '/index.php'; // Prevent redirect issues
chdir($gibbonPath);
require $gibbonPath . '/gibbon.php';

// Get database connection
global $pdo;
if (!isset($pdo)) {
    die("Error: Database connection not available\n");
}

output("Database connection established", true);

// Seed data configuration
const NUM_STAFF = 5;
const NUM_CHILDREN = 20;
const NUM_FAMILIES = 15;
const CARE_RECORDS_PER_CHILD = 10;

// Sample data arrays
$staffMembers = [
    ['firstName' => 'Sarah', 'lastName' => 'Johnson', 'email' => 'sarah.johnson@laya.test', 'role' => 'Director'],
    ['firstName' => 'Michael', 'lastName' => 'Chen', 'email' => 'michael.chen@laya.test', 'role' => 'Lead Educator'],
    ['firstName' => 'Emily', 'lastName' => 'Rodriguez', 'email' => 'emily.rodriguez@laya.test', 'role' => 'Educator'],
    ['firstName' => 'David', 'lastName' => 'Thompson', 'email' => 'david.thompson@laya.test', 'role' => 'Assistant Educator'],
    ['firstName' => 'Lisa', 'lastName' => 'Patel', 'email' => 'lisa.patel@laya.test', 'role' => 'Educator'],
];

$childrenData = [
    // Age group 0-2 years (Infants)
    ['firstName' => 'Emma', 'lastName' => 'Williams', 'dob' => date('Y-m-d', strtotime('-15 months')), 'gender' => 'F'],
    ['firstName' => 'Oliver', 'lastName' => 'Brown', 'dob' => date('Y-m-d', strtotime('-18 months')), 'gender' => 'M'],
    ['firstName' => 'Sophia', 'lastName' => 'Davis', 'dob' => date('Y-m-d', strtotime('-10 months')), 'gender' => 'F'],
    ['firstName' => 'Liam', 'lastName' => 'Miller', 'dob' => date('Y-m-d', strtotime('-20 months')), 'gender' => 'M'],
    ['firstName' => 'Ava', 'lastName' => 'Wilson', 'dob' => date('Y-m-d', strtotime('-12 months')), 'gender' => 'F'],
    ['firstName' => 'Noah', 'lastName' => 'Moore', 'dob' => date('Y-m-d', strtotime('-22 months')), 'gender' => 'M'],
    ['firstName' => 'Isabella', 'lastName' => 'Taylor', 'dob' => date('Y-m-d', strtotime('-16 months')), 'gender' => 'F'],

    // Age group 2-4 years (Toddlers)
    ['firstName' => 'Ethan', 'lastName' => 'Anderson', 'dob' => date('Y-m-d', strtotime('-30 months')), 'gender' => 'M'],
    ['firstName' => 'Mia', 'lastName' => 'Thomas', 'dob' => date('Y-m-d', strtotime('-36 months')), 'gender' => 'F'],
    ['firstName' => 'Lucas', 'lastName' => 'Jackson', 'dob' => date('Y-m-d', strtotime('-40 months')), 'gender' => 'M'],
    ['firstName' => 'Charlotte', 'lastName' => 'White', 'dob' => date('Y-m-d', strtotime('-32 months')), 'gender' => 'F'],
    ['firstName' => 'Mason', 'lastName' => 'Harris', 'dob' => date('Y-m-d', strtotime('-38 months')), 'gender' => 'M'],
    ['firstName' => 'Amelia', 'lastName' => 'Martin', 'dob' => date('Y-m-d', strtotime('-34 months')), 'gender' => 'F'],

    // Age group 4-6 years (Preschool)
    ['firstName' => 'James', 'lastName' => 'Garcia', 'dob' => date('Y-m-d', strtotime('-50 months')), 'gender' => 'M'],
    ['firstName' => 'Harper', 'lastName' => 'Martinez', 'dob' => date('Y-m-d', strtotime('-55 months')), 'gender' => 'F'],
    ['firstName' => 'Benjamin', 'lastName' => 'Robinson', 'dob' => date('Y-m-d', strtotime('-60 months')), 'gender' => 'M'],
    ['firstName' => 'Evelyn', 'lastName' => 'Clark', 'dob' => date('Y-m-d', strtotime('-52 months')), 'gender' => 'F'],
    ['firstName' => 'Alexander', 'lastName' => 'Rodriguez', 'dob' => date('Y-m-d', strtotime('-58 months')), 'gender' => 'M'],
    ['firstName' => 'Abigail', 'lastName' => 'Lewis', 'dob' => date('Y-m-d', strtotime('-54 months')), 'gender' => 'F'],
    ['firstName' => 'William', 'lastName' => 'Lee', 'dob' => date('Y-m-d', strtotime('-56 months')), 'gender' => 'M'],
];

$parentNames = [
    ['father' => 'John Williams', 'mother' => 'Sarah Williams', 'fatherEmail' => 'john.williams@example.com', 'motherEmail' => 'sarah.williams@example.com'],
    ['father' => 'Robert Brown', 'mother' => 'Jennifer Brown', 'fatherEmail' => 'robert.brown@example.com', 'motherEmail' => 'jennifer.brown@example.com'],
    ['father' => 'James Davis', 'mother' => 'Patricia Davis', 'fatherEmail' => 'james.davis@example.com', 'motherEmail' => 'patricia.davis@example.com'],
    ['father' => 'Michael Miller', 'mother' => 'Linda Miller', 'fatherEmail' => 'michael.miller@example.com', 'motherEmail' => 'linda.miller@example.com'],
    ['father' => 'David Wilson', 'mother' => 'Barbara Wilson', 'fatherEmail' => 'david.wilson@example.com', 'motherEmail' => 'barbara.wilson@example.com'],
    ['father' => 'Joseph Moore', 'mother' => 'Elizabeth Moore', 'fatherEmail' => 'joseph.moore@example.com', 'motherEmail' => 'elizabeth.moore@example.com'],
    ['father' => 'Thomas Taylor', 'mother' => 'Susan Taylor', 'fatherEmail' => 'thomas.taylor@example.com', 'motherEmail' => 'susan.taylor@example.com'],
    ['father' => 'Richard Anderson', 'mother' => 'Jessica Anderson', 'fatherEmail' => 'richard.anderson@example.com', 'motherEmail' => 'jessica.anderson@example.com'],
    ['father' => 'Christopher Thomas', 'mother' => 'Karen Thomas', 'fatherEmail' => 'christopher.thomas@example.com', 'motherEmail' => 'karen.thomas@example.com'],
    ['father' => 'Daniel Jackson', 'mother' => 'Nancy Jackson', 'fatherEmail' => 'daniel.jackson@example.com', 'motherEmail' => 'nancy.jackson@example.com'],
    ['father' => 'Matthew White', 'mother' => 'Betty White', 'fatherEmail' => 'matthew.white@example.com', 'motherEmail' => 'betty.white@example.com'],
    ['father' => 'Anthony Harris', 'mother' => 'Helen Harris', 'fatherEmail' => 'anthony.harris@example.com', 'motherEmail' => 'helen.harris@example.com'],
    ['father' => 'Donald Martin', 'mother' => 'Sandra Martin', 'fatherEmail' => 'donald.martin@example.com', 'motherEmail' => 'sandra.martin@example.com'],
    ['father' => 'Steven Garcia', 'mother' => 'Ashley Garcia', 'fatherEmail' => 'steven.garcia@example.com', 'motherEmail' => 'ashley.garcia@example.com'],
    ['father' => 'Paul Martinez', 'mother' => 'Kimberly Martinez', 'fatherEmail' => 'paul.martinez@example.com', 'motherEmail' => 'kimberly.martinez@example.com'],
];

// Activity types and names
$activityTypes = [
    'Art' => ['Finger Painting', 'Playdough Creations', 'Collage Making', 'Drawing Station'],
    'Music' => ['Music with Instruments', 'Singing Time', 'Dance Party', 'Rhythm Games'],
    'Physical' => ['Obstacle Course', 'Ball Games', 'Climbing', 'Balance Activities'],
    'Language' => ['Story Time', 'Show and Tell', 'Letter Recognition', 'Rhyme Time'],
    'Math' => ['Counting Games', 'Shape Sorting', 'Pattern Building', 'Number Recognition'],
    'Science' => ['Nature Exploration', 'Simple Experiments', 'Sensory Play', 'Discovery Table'],
    'Social' => ['Group Circle Time', 'Cooperative Games', 'Role Play', 'Friendship Activities'],
    'Free Play' => ['Block Building', 'Dramatic Play', 'Puzzle Time', 'Construction'],
];

/**
 * Helper function to check if data exists
 */
function recordExists($pdo, $table, $where) {
    $conditions = [];
    $params = [];
    foreach ($where as $key => $value) {
        $conditions[] = "$key = :$key";
        $params[":$key"] = $value;
    }
    $sql = "SELECT COUNT(*) FROM $table WHERE " . implode(' AND ', $conditions);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

/**
 * Helper function to get record ID
 */
function getRecordId($pdo, $table, $idColumn, $where) {
    $conditions = [];
    $params = [];
    foreach ($where as $key => $value) {
        $conditions[] = "$key = :$key";
        $params[":$key"] = $value;
    }
    $sql = "SELECT $idColumn FROM $table WHERE " . implode(' AND ', $conditions) . " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Reset data if requested
    if ($reset) {
        output("Resetting seed data...", true);

        // Delete care records (in reverse order of dependencies)
        $pdo->exec("DELETE FROM gibbonCareActivity WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test')");
        $pdo->exec("DELETE FROM gibbonCareIncident WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test')");
        $pdo->exec("DELETE FROM gibbonCareDiaper WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test')");
        $pdo->exec("DELETE FROM gibbonCareNap WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test')");
        $pdo->exec("DELETE FROM gibbonCareMeal WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test')");
        $pdo->exec("DELETE FROM gibbonCareAttendance WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test')");

        // Delete enrollments and family relationships
        $pdo->exec("DELETE FROM gibbonStudentEnrolment WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com')");
        $pdo->exec("DELETE FROM gibbonFamilyChild WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com')");
        $pdo->exec("DELETE FROM gibbonFamilyAdult WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com')");
        $pdo->exec("DELETE FROM gibbonFamily WHERE name LIKE '%Test Family%'");

        // Delete staff
        $pdo->exec("DELETE FROM gibbonStaff WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@laya.test')");

        // Delete persons (students and parents)
        $pdo->exec("DELETE FROM gibbonPerson WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test'");

        output("Seed data reset complete", true);
    }

    // Get current school year
    $sql = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear WHERE status = 'Current' LIMIT 1";
    $result = $pdo->query($sql);
    $schoolYear = $result->fetch(PDO::FETCH_ASSOC);

    if (!$schoolYear) {
        throw new Exception("No current school year found. Please set up a school year first.");
    }

    $gibbonSchoolYearID = $schoolYear['gibbonSchoolYearID'];
    output("Using school year: {$schoolYear['name']} (ID: {$gibbonSchoolYearID})", true);

    // Create form groups (roll groups) if they don't exist
    output("\nCreating form groups...", true);
    $formGroups = [
        ['name' => 'Infants (0-2)', 'nameShort' => 'INF', 'ageGroup' => '0-2'],
        ['name' => 'Toddlers (2-4)', 'nameShort' => 'TOD', 'ageGroup' => '2-4'],
        ['name' => 'Preschool (4-6)', 'nameShort' => 'PRE', 'ageGroup' => '4-6'],
    ];

    $formGroupIds = [];
    foreach ($formGroups as $group) {
        if (!recordExists($pdo, 'gibbonRollGroup', [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'nameShort' => $group['nameShort']
        ])) {
            $sql = "INSERT INTO gibbonRollGroup (gibbonSchoolYearID, name, nameShort, gibbonPersonIDTutor, gibbonPersonIDTutor2, gibbonPersonIDTutor3, gibbonSpaceID, attendance, website)
                    VALUES (:schoolYearID, :name, :nameShort, NULL, NULL, NULL, NULL, 'Y', '')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'schoolYearID' => $gibbonSchoolYearID,
                'name' => $group['name'],
                'nameShort' => $group['nameShort'],
            ]);
            $formGroupIds[$group['nameShort']] = $pdo->lastInsertId();
            output("Created form group: {$group['name']}", $verbose);
        } else {
            $formGroupIds[$group['nameShort']] = getRecordId($pdo, 'gibbonRollGroup', 'gibbonRollGroupID', [
                'gibbonSchoolYearID' => $gibbonSchoolYearID,
                'nameShort' => $group['nameShort']
            ]);
            output("Form group already exists: {$group['name']}", $verbose);
        }
    }

    // Create staff members
    output("\nCreating staff members...", true);
    $staffIds = [];
    foreach ($staffMembers as $staff) {
        if (!recordExists($pdo, 'gibbonPerson', ['email' => $staff['email']])) {
            // Create person record
            $sql = "INSERT INTO gibbonPerson (title, surname, firstName, preferredName, officialName, nameInCharacters, gender, username, password, passwordStrong, passwordStrongSalt, passwordForceReset, status, canLogin, gibbonRoleIDPrimary, gibbonRoleIDAll, email, emailAlternate, phone1, phone2, phone3, phone4, address1, address1District, address1Country, address2, address2District, address2Country, website, languageFirst, languageSecond, languageThird, countryOfBirth, birthCertificateScan, ethnicity, citizenship1, citizenship1Passport, citizenship2, citizenship2Passport, religion, nationalIDCardNumber, residencyStatus, visaExpiryDate, profession, employer, jobTitle, emergency1Name, emergency1Number1, emergency1Number2, emergency1Relationship, emergency2Name, emergency2Number1, emergency2Number2, emergency2Relationship, gibbonHouseID, studentID, dateStart, dateEnd, gibbonApplicationFormID, lockerNumber, vehicleRegistration, personalBackground, messengerLastBubble, privacy, dayType, gibbonThemeIDPersonal, gibboni18nID, receiveNotificationEmails, fields)
                    VALUES ('Mr', :surname, :firstName, :firstName, :officialName, '', 'M', :username, '', '', '', 'N', 'Full', 'Y', 0, '0', :email, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, '', '', NULL, '', '', NULL, NULL, 'Y', '')";

            $username = strtolower($staff['firstName'] . '.' . $staff['lastName']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'surname' => $staff['lastName'],
                'firstName' => $staff['firstName'],
                'officialName' => $staff['firstName'] . ' ' . $staff['lastName'],
                'username' => $username,
                'email' => $staff['email'],
            ]);
            $personId = $pdo->lastInsertId();

            // Create staff record
            $sql = "INSERT INTO gibbonStaff (gibbonPersonID, initials, type, jobTitle, dateStart, dateEnd, biographicalInformation, fields)
                    VALUES (:personID, :initials, 'Teaching', :jobTitle, CURDATE(), NULL, '', '')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'personID' => $personId,
                'initials' => substr($staff['firstName'], 0, 1) . substr($staff['lastName'], 0, 1),
                'jobTitle' => $staff['role'],
            ]);

            $staffIds[$staff['email']] = $personId;
            output("Created staff: {$staff['firstName']} {$staff['lastName']} ({$staff['role']})", $verbose);
        } else {
            $staffIds[$staff['email']] = getRecordId($pdo, 'gibbonPerson', 'gibbonPersonID', ['email' => $staff['email']]);
            output("Staff already exists: {$staff['firstName']} {$staff['lastName']}", $verbose);
        }
    }

    // Create families with parents and children
    output("\nCreating families with parents and children...", true);
    $childrenIds = [];
    $parentIds = [];

    for ($i = 0; $i < NUM_CHILDREN && $i < count($childrenData); $i++) {
        $child = $childrenData[$i];
        $familyIndex = $i % NUM_FAMILIES;
        $parents = $parentNames[$familyIndex];

        $familyName = $child['lastName'] . ' Test Family';

        // Create family if it doesn't exist
        if (!recordExists($pdo, 'gibbonFamily', ['name' => $familyName])) {
            $sql = "INSERT INTO gibbonFamily (name, nameAddress, homeAddress, homeAddressDistrict, homeAddressCountry, status, languageHomePrimary, languageHomeSecondary)
                    VALUES (:name, :name, '123 Test Street', '', '', 'Married', 'English', 'French')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['name' => $familyName]);
            $familyId = $pdo->lastInsertId();
            output("Created family: {$familyName}", $verbose);
        } else {
            $familyId = getRecordId($pdo, 'gibbonFamily', 'gibbonFamilyID', ['name' => $familyName]);
            output("Family already exists: {$familyName}", $verbose);
        }

        // Create father if doesn't exist
        $fatherEmail = $parents['fatherEmail'];
        if (!isset($parentIds[$fatherEmail]) && !recordExists($pdo, 'gibbonPerson', ['email' => $fatherEmail])) {
            $fatherNames = explode(' ', $parents['father']);
            $sql = "INSERT INTO gibbonPerson (title, surname, firstName, preferredName, officialName, gender, username, password, passwordStrong, passwordStrongSalt, passwordForceReset, status, canLogin, gibbonRoleIDPrimary, gibbonRoleIDAll, email, phone1, fields)
                    VALUES ('Mr', :surname, :firstName, :firstName, :officialName, 'M', :username, '', '', '', 'N', 'Full', 'Y', 0, '0', :email, '555-0001', '')";
            $username = strtolower(str_replace(' ', '.', $parents['father']));
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'surname' => $fatherNames[1],
                'firstName' => $fatherNames[0],
                'officialName' => $parents['father'],
                'username' => $username,
                'email' => $fatherEmail,
            ]);
            $fatherId = $pdo->lastInsertId();
            $parentIds[$fatherEmail] = $fatherId;

            // Link father to family
            $sql = "INSERT INTO gibbonFamilyAdult (gibbonFamilyID, gibbonPersonID, comment, childDataAccess, contactPriority, contactCall, contactSMS, contactEmail, contactMail)
                    VALUES (:familyID, :personID, '', 'Y', 1, 'Y', 'Y', 'Y', 'Y')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['familyID' => $familyId, 'personID' => $fatherId]);

            output("Created parent: {$parents['father']}", $verbose);
        } elseif (isset($parentIds[$fatherEmail])) {
            $fatherId = $parentIds[$fatherEmail];
        }

        // Create mother if doesn't exist
        $motherEmail = $parents['motherEmail'];
        if (!isset($parentIds[$motherEmail]) && !recordExists($pdo, 'gibbonPerson', ['email' => $motherEmail])) {
            $motherNames = explode(' ', $parents['mother']);
            $sql = "INSERT INTO gibbonPerson (title, surname, firstName, preferredName, officialName, gender, username, password, passwordStrong, passwordStrongSalt, passwordForceReset, status, canLogin, gibbonRoleIDPrimary, gibbonRoleIDAll, email, phone1, fields)
                    VALUES ('Mrs', :surname, :firstName, :firstName, :officialName, 'F', :username, '', '', '', 'N', 'Full', 'Y', 0, '0', :email, '555-0002', '')";
            $username = strtolower(str_replace(' ', '.', $parents['mother']));
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'surname' => $motherNames[1],
                'firstName' => $motherNames[0],
                'officialName' => $parents['mother'],
                'username' => $username,
                'email' => $motherEmail,
            ]);
            $motherId = $pdo->lastInsertId();
            $parentIds[$motherEmail] = $motherId;

            // Link mother to family
            $sql = "INSERT INTO gibbonFamilyAdult (gibbonFamilyID, gibbonPersonID, comment, childDataAccess, contactPriority, contactCall, contactSMS, contactEmail, contactMail)
                    VALUES (:familyID, :personID, '', 'Y', 2, 'Y', 'Y', 'Y', 'Y')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['familyID' => $familyId, 'personID' => $motherId]);

            output("Created parent: {$parents['mother']}", $verbose);
        } elseif (isset($parentIds[$motherEmail])) {
            $motherId = $parentIds[$motherEmail];
        }

        // Create child
        $childEmail = strtolower($child['firstName'] . '.' . $child['lastName'] . '@example.com');
        if (!recordExists($pdo, 'gibbonPerson', ['email' => $childEmail])) {
            $sql = "INSERT INTO gibbonPerson (title, surname, firstName, preferredName, officialName, gender, dob, username, password, passwordStrong, passwordStrongSalt, passwordForceReset, status, canLogin, gibbonRoleIDPrimary, gibbonRoleIDAll, email, fields)
                    VALUES (:title, :surname, :firstName, :firstName, :officialName, :gender, :dob, :username, '', '', '', 'N', 'Full', 'N', 0, '0', :email, '')";
            $username = strtolower($child['firstName'] . '.' . $child['lastName']);
            $title = ($child['gender'] == 'M') ? 'Master' : 'Miss';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'title' => $title,
                'surname' => $child['lastName'],
                'firstName' => $child['firstName'],
                'officialName' => $child['firstName'] . ' ' . $child['lastName'],
                'gender' => $child['gender'],
                'dob' => $child['dob'],
                'username' => $username,
                'email' => $childEmail,
            ]);
            $childId = $pdo->lastInsertId();
            $childrenIds[] = $childId;

            // Link child to family
            $sql = "INSERT INTO gibbonFamilyChild (gibbonFamilyID, gibbonPersonID, comment)
                    VALUES (:familyID, :personID, '')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['familyID' => $familyId, 'personID' => $childId]);

            // Determine form group based on age
            $ageInMonths = (time() - strtotime($child['dob'])) / (30 * 24 * 60 * 60);
            if ($ageInMonths < 24) {
                $formGroup = 'INF';
            } elseif ($ageInMonths < 48) {
                $formGroup = 'TOD';
            } else {
                $formGroup = 'PRE';
            }

            // Create student enrollment
            $sql = "INSERT INTO gibbonStudentEnrolment (gibbonPersonID, gibbonSchoolYearID, gibbonYearGroupID, gibbonRollGroupID, rollOrder, gibbonSchoolHouseID)
                    VALUES (:personID, :schoolYearID, 0, :rollGroupID, NULL, NULL)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'personID' => $childId,
                'schoolYearID' => $gibbonSchoolYearID,
                'rollGroupID' => $formGroupIds[$formGroup],
            ]);

            output("Created child: {$child['firstName']} {$child['lastName']} ({$formGroup})", $verbose);
        } else {
            $childId = getRecordId($pdo, 'gibbonPerson', 'gibbonPersonID', ['email' => $childEmail]);
            $childrenIds[] = $childId;
            output("Child already exists: {$child['firstName']} {$child['lastName']}", $verbose);
        }
    }

    // Create care records for children
    output("\nCreating care records...", true);
    $firstStaffId = reset($staffIds);
    $mealTypes = ['Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner'];
    $quantities = ['None', 'Little', 'Some', 'Most', 'All'];
    $napQualities = ['Restless', 'Light', 'Sound'];
    $diaperTypes = ['Wet', 'Soiled', 'Both', 'Dry'];
    $incidentTypes = ['Minor Injury', 'Illness', 'Behavioral'];
    $participationLevels = ['Not Interested', 'Observing', 'Participating', 'Leading'];

    foreach ($childrenIds as $childId) {
        // Create records for the last 10 days
        for ($day = 0; $day < CARE_RECORDS_PER_CHILD; $day++) {
            $date = date('Y-m-d', strtotime("-{$day} days"));

            // Attendance record
            if (!recordExists($pdo, 'gibbonCareAttendance', ['gibbonPersonID' => $childId, 'date' => $date])) {
                $checkInTime = '08:' . str_pad(rand(0, 45), 2, '0', STR_PAD_LEFT) . ':00';
                $checkOutTime = '17:' . str_pad(rand(0, 45), 2, '0', STR_PAD_LEFT) . ':00';

                $sql = "INSERT INTO gibbonCareAttendance (gibbonPersonID, gibbonSchoolYearID, date, checkInTime, checkOutTime, checkInByID, checkOutByID, notes)
                        VALUES (:personID, :schoolYearID, :date, :checkInTime, :checkOutTime, :staffID, :staffID, 'Regular attendance')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'personID' => $childId,
                    'schoolYearID' => $gibbonSchoolYearID,
                    'date' => $date,
                    'checkInTime' => $checkInTime,
                    'checkOutTime' => $checkOutTime,
                    'staffID' => $firstStaffId,
                ]);
            }

            // Meal records (2-3 meals per day)
            $numMeals = rand(2, 3);
            for ($m = 0; $m < $numMeals; $m++) {
                $mealType = $mealTypes[$m];
                if (!recordExists($pdo, 'gibbonCareMeal', ['gibbonPersonID' => $childId, 'date' => $date, 'mealType' => $mealType])) {
                    $sql = "INSERT INTO gibbonCareMeal (gibbonPersonID, gibbonSchoolYearID, date, mealType, quantity, notes, recordedByID)
                            VALUES (:personID, :schoolYearID, :date, :mealType, :quantity, '', :staffID)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'personID' => $childId,
                        'schoolYearID' => $gibbonSchoolYearID,
                        'date' => $date,
                        'mealType' => $mealType,
                        'quantity' => $quantities[rand(2, 4)],
                        'staffID' => $firstStaffId,
                    ]);
                }
            }

            // Nap record (50% chance)
            if (rand(0, 1) == 1 && !recordExists($pdo, 'gibbonCareNap', ['gibbonPersonID' => $childId, 'date' => $date])) {
                $startTime = '13:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00';
                $duration = rand(30, 120);
                $endTime = date('H:i:s', strtotime($startTime) + ($duration * 60));

                $sql = "INSERT INTO gibbonCareNap (gibbonPersonID, gibbonSchoolYearID, date, startTime, endTime, quality, notes, recordedByID)
                        VALUES (:personID, :schoolYearID, :date, :startTime, :endTime, :quality, '', :staffID)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'personID' => $childId,
                    'schoolYearID' => $gibbonSchoolYearID,
                    'date' => $date,
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                    'quality' => $napQualities[rand(0, 2)],
                    'staffID' => $firstStaffId,
                ]);
            }

            // Diaper changes (2-4 per day for infants, 30% chance)
            if (rand(0, 100) < 30) {
                $numChanges = rand(2, 4);
                for ($d = 0; $d < $numChanges; $d++) {
                    $time = sprintf('%02d:%02d:00', rand(8, 16), rand(0, 59));
                    if (!recordExists($pdo, 'gibbonCareDiaper', ['gibbonPersonID' => $childId, 'date' => $date, 'time' => $time])) {
                        $sql = "INSERT INTO gibbonCareDiaper (gibbonPersonID, gibbonSchoolYearID, date, time, type, notes, recordedByID)
                                VALUES (:personID, :schoolYearID, :date, :time, :type, '', :staffID)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            'personID' => $childId,
                            'schoolYearID' => $gibbonSchoolYearID,
                            'date' => $date,
                            'time' => $time,
                            'type' => $diaperTypes[rand(0, 3)],
                            'staffID' => $firstStaffId,
                        ]);
                    }
                }
            }

            // Incident (10% chance)
            if ($day < 3 && rand(0, 100) < 10) {
                $time = sprintf('%02d:%02d:00', rand(9, 15), rand(0, 59));
                if (!recordExists($pdo, 'gibbonCareIncident', ['gibbonPersonID' => $childId, 'date' => $date])) {
                    $incidentType = $incidentTypes[rand(0, 2)];
                    $descriptions = [
                        'Minor Injury' => 'Small bump on forehead from falling while playing',
                        'Illness' => 'Complained of upset stomach, monitored closely',
                        'Behavioral' => 'Had difficulty sharing toys with peers, redirected successfully',
                    ];

                    $sql = "INSERT INTO gibbonCareIncident (gibbonPersonID, gibbonSchoolYearID, date, time, type, description, actionTaken, parentNotified, recordedByID)
                            VALUES (:personID, :schoolYearID, :date, :time, :type, :description, :actionTaken, 'Y', :staffID)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'personID' => $childId,
                        'schoolYearID' => $gibbonSchoolYearID,
                        'date' => $date,
                        'time' => $time,
                        'type' => $incidentType,
                        'description' => $descriptions[$incidentType],
                        'actionTaken' => 'Applied ice pack and monitored',
                        'staffID' => $firstStaffId,
                    ]);
                }
            }

            // Activities (2-3 per day)
            $numActivities = rand(2, 3);
            $usedActivityTypes = array_rand($activityTypes, $numActivities);
            if (!is_array($usedActivityTypes)) {
                $usedActivityTypes = [$usedActivityTypes];
            }

            foreach ($usedActivityTypes as $activityType) {
                $activities = $activityTypes[$activityType];
                $activityName = $activities[rand(0, count($activities) - 1)];

                if (!recordExists($pdo, 'gibbonCareActivity', [
                    'gibbonPersonID' => $childId,
                    'date' => $date,
                    'activityName' => $activityName
                ])) {
                    $sql = "INSERT INTO gibbonCareActivity (gibbonPersonID, gibbonSchoolYearID, date, activityName, activityType, duration, participation, notes, recordedByID)
                            VALUES (:personID, :schoolYearID, :date, :activityName, :activityType, :duration, :participation, 'Engaged well', :staffID)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'personID' => $childId,
                        'schoolYearID' => $gibbonSchoolYearID,
                        'date' => $date,
                        'activityName' => $activityName,
                        'activityType' => $activityType,
                        'duration' => rand(15, 60),
                        'participation' => $participationLevels[rand(1, 3)],
                        'staffID' => $firstStaffId,
                    ]);
                }
            }
        }

        output("Created care records for child ID: {$childId}", $verbose);
    }

    // Commit transaction
    $pdo->commit();

    output("\n" . str_repeat('=', 50), true);
    output("Seed data creation complete!", true);
    output(str_repeat('=', 50), true);
    output("\nSummary:", true);
    output("- Form groups: 3", true);
    output("- Staff members: " . count($staffIds), true);
    output("- Families: " . NUM_FAMILIES, true);
    output("- Parents: " . count($parentIds), true);
    output("- Children: " . count($childrenIds), true);
    output("- Care records: ~" . (count($childrenIds) * CARE_RECORDS_PER_CHILD * 6) . " (attendance, meals, naps, diapers, incidents, activities)", true);
    output("\nNext steps:", true);
    output("1. Verify data in Gibbon UI", true);
    output("2. Run AI service seed script: python ai-service/scripts/seed.py", true);
    output("3. Test care tracking features", true);

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    output("\nError: " . $e->getMessage(), true);
    output("Trace: " . $e->getTraceAsString(), $verbose);
    exit(1);
}
