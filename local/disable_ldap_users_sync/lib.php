<?php
defined('MOODLE_INTERNAL') || die();

// The function that will be called to run the code for each user
function local_disable_ldap_users_sync_execute_for_all_users() {
    global $DB;

    // Get all users who are not deleted
    $users = $DB->get_records('user', array('deleted' => 0));

    foreach ($users as $user) {
        // Call the function that checks the status in AD for each user
        $status = check_ad_status($user->username, $user);

        // Add a log message to the custom log file
        log_user_modification($user, $status);
    }
}

// The function that will check the status of a user in Active Directory (AD)
function check_ad_status($username, $user) {
    // Check if the user is already suspended in Moodle
    if ($user->suspended == 1) {
        $status = $user->username . ' - Account already suspended in Moodle. Proceeding to the next user.';
        mtrace($status);
        return $status;
    }

    // LDAP (Active Directory) connection details
    $ldap_host = get_config('auth_ldap', 'host_url');
    $ldap_dn = get_config('auth_ldap', 'contexts');
    $ldap_user = get_config('auth_ldap', 'bind_dn');
    $ldap_password = get_config('auth_ldap', 'bind_pw');

    // Connect to the LDAP server
    $ldap_conn = ldap_connect($ldap_host);
    if (!$ldap_conn) {
        return 'Error connecting to LDAP server: ' . ldap_error($ldap_conn);
    }

    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    $bind = ldap_bind($ldap_conn, $ldap_user, $ldap_password);
    if (!$bind) {
        ldap_close($ldap_conn);
        return 'LDAP authentication error: ' . ldap_error($ldap_conn);
    }

    $filter = "(sAMAccountName=$username)";
    $search = ldap_search($ldap_conn, $ldap_dn, $filter);

    if (!$search) {
        ldap_close($ldap_conn);
        return 'Error performing LDAP search: ' . ldap_error($ldap_conn);
    }

    $entries = ldap_get_entries($ldap_conn, $search);

    if ($entries['count'] > 0) {
        $account_disabled = $entries[0]['useraccountcontrol'][0];
        if ($account_disabled & 2) {
            suspend_user_in_moodle($user);
            ldap_close($ldap_conn);
            $status = $user->username . ' - The Active Directory account is disabled. The user has been suspended in Moodle.';
            mtrace($status);
            log_user_modification($user, $status);  // Log only if the user was suspended
            return $status;
        } else {
            ldap_close($ldap_conn);
            $status = $user->username . ' - The Active Directory account is active.';
            mtrace($status);
            return $status;
        }
    } else {
        suspend_user_in_moodle($user);
        ldap_close($ldap_conn);
        $status = $user->username . ' - The user was not found in Active Directory. The user has been suspended in Moodle.';
        mtrace($status);
        log_user_modification($user, $status);  // Log only if the user was not found
        return $status;
    }
}

// The function that logs modifications in a custom log file
function log_user_modification($user, $status) {
    // Get the log file path from the Moodle configuration
    $logfile_dir = get_config('local_disable_ldap_users_sync', 'logfile');
    
    // Format the current date in "YYYY_MM_DD" format
    $date_file = date('Y_m_d');
    
    // If no location is defined in the configuration, use the default location
    if (empty($logfile_dir)) {
        // Set the default location (current directory or a custom location)
        $logfile_dir = __DIR__;  // Or, for example, 'L:\Logs\moodle.md\'  
    }
    
    $logfile = $logfile_dir . DIRECTORY_SEPARATOR . $date_file . '_disable_ldap_users.log';
    // Check if the log directory exists, if not, create it
    $logfile_dir = dirname($logfile);
    if (!file_exists($logfile_dir)) {
        if (!mkdir($logfile_dir, 0777, true)) {
            mtrace("Error creating log directory: " . $logfile_dir);
            return;
        }
    }

    // Check if the log file is writable
    if (!is_writable($logfile_dir)) {
        mtrace("Log directory is not writable: " . $logfile_dir);
        return;
    }

    // Open the log file to add an entry
    $log = fopen($logfile, 'a');

    if ($log) {
        // Format the log message (includes date, user, and modification status)
        $log_message = date('Y-m-d H:i:s') . ' - User: ' . $status . "\n";

        // Write the message to the file
        fwrite($log, $log_message);

        // Close the file
        fclose($log);
    } else {
        // If the file cannot be opened, log the error
        mtrace("Error opening log file: " . $logfile);
    }
}

// Suspend the user in Moodle
function suspend_user_in_moodle($user) {
    global $DB;

    // Set the user's status to "suspended"
    $user->suspended = 1;

    // Update the user's record in the database
    $DB->update_record('user', $user);

    // Log that the user has been suspended
    mtrace($user->username . ' - User suspended in Moodle.');
}
