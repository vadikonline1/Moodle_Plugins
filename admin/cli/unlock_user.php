<?php
// This is a PHP script that will unlock a user in Moodle

// Include the Moodle configuration file
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php'); // Make sure the path to config.php is correct

// Check if a user ID has been provided
if ($argc < 2) {
    echo "Usage: php unlock_user.php <user_id>\n";
    exit(1);
}

// Get the user ID from the command line arguments
$user_id = $argv[1];

// Check if the user exists in the database
global $DB;
$user = $DB->get_record('user', array('id' => $user_id));

if (!$user) {
    echo "User with ID $user_id not found.\n";
    exit(1);
}

// Check if the user is already unlocked
if ($user->suspended == 0) {
    echo "User with ID $user_id is already unlocked.\n";
    exit(0);
}

// Set the user as unlocked
$user->suspended = 0;

// Update the user in the database
$DB->update_record('user', $user);

echo "User with ID $user_id has been successfully unlocked.\n";
exit(0);
