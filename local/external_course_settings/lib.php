<?php

// Functie pentru a obtine IP-ul utilizatorului
function get_user_ip() {
    // Verifica daca exista o setare pentru header-ul IP din configuratia pluginului
    $check_userip = get_config('local_External_Course_Settings', 'user_ip');
    
    // Daca exista o configurare valida pentru IP-ul utilizatorului, verifica header-ul specificat
    if (!empty($check_userip) && !empty($_SERVER[$check_userip])) {
        // Obtine IP-ul din header-ul specificat
        $ip_list = explode(',', $_SERVER[$check_userip]);
        return trim($ip_list[0]);  // Primul IP din lista este cel real al utilizatorului
    }
    
    // Daca nu exista configurare pentru IP, sau header-ul nu este prezent, foloseste X-Forwarded-For
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ip_list[0]);  // Primul IP din lista X-Forwarded-For este cel real
    }

    // Daca nu exista niciunul dintre aceste, foloseste REMOTE_ADDR ca fallback
    return $_SERVER['REMOTE_ADDR'];
}

// Functie pentru a verifica daca un IP se afla intr-un interval CIDR
function ip_in_range($ip, $range) {
    list($subnet, $bits) = explode('/', $range);  // Impartim intervalul CIDR in subnet si bitmask

    // Verificam daca $bits este un numar valid
    if (!is_numeric($bits) || $bits < 0 || $bits > 32) {
        return false;  // Daca nu este valid, returnam false
    }

    // Transformam subnetul si IP-ul utilizatorului in long
    $subnet = ip2long($subnet);  // Transformam IP-ul subnetului in long
    $ip = ip2long($ip);  // Transformam IP-ul utilizatorului in long

    // Calculam masca de subretea pe baza numarului de biti
    $mask = -1 << (32 - $bits);  // Masca de subretea

    // Verificam daca IP-ul utilizatorului se afla in intervalul definit de subnet si masca
    return ($ip & $mask) === ($subnet & $mask);  // Comparăm IP-ul utilizatorului cu subnetul
}

// Functia pentru a verifica IP-ul utilizatorului
function check_user_ip() {
    global $DB, $USER, $PAGE;

    // Obtine IP-ul utilizatorului
    $user_ip = get_user_ip();
    
    // Citeste whitelist-ul de IP-uri din baza de date
    $whitelist = $DB->get_record('config_plugins', ['plugin' => 'local_External_Course_Settings', 'name' => 'ipwhitelist']);
    
    if (!$whitelist) {
        error_log("No whitelist found.");  // Logam cazul in care nu exista whitelist
        return;  // Daca nu exista setari pentru whitelist, nu se face nicio verificare
    }

    // Impartim whitelist-ul folosind spatiul ca separator
    $whitelist_ips = preg_split('/\s+/', $whitelist->value);

    // Verifica fiecare valoare
    $is_ip_valid = false;
    foreach ($whitelist_ips as $ip_range) {
        // Verificam daca valoarea contine '/' (pentru CIDR)
        if (strpos($ip_range, '/') !== false) {
            // Este un interval CIDR, verificam daca IP-ul utilizatorului se afla in intervalul respectiv
            if (ip_in_range($user_ip, $ip_range)) {
                $is_ip_valid = true;
                break;  // Daca gasim un interval valid, iesim din bucla
            }
        } else {
            // Este un IP simplu, verificam daca IP-ul utilizatorului se potriveste exact
            if ($user_ip === $ip_range) {
                $is_ip_valid = true;
                break;  // Daca gasim un IP valid, iesim din bucla
            }
        }
    }

    // 2. Preluarea ID-ului campului pe baza shortname-ului 'external_course'
    $customfield_shortname = 'external_course';
    $customfield_ID = $DB->get_record('customfield_field', array('shortname' => $customfield_shortname));

    if ($customfield_ID) {
        // 3. Preluarea valorii din mdl_customfield_data pentru a compara fieldid si instanceid
        $course_id = $PAGE->course->id;  // se presupune ca $PAGE->course->id este ID-ul cursului la care accesezi pagina
        $customdata = $DB->get_record('customfield_data', array(
            'fieldid' => $customfield_ID->id,  // se compara cu fieldid-ul din mdl_customfield_data
            'instanceid' => $course_id       // se compara cu instanceid-ul (ID-ul cursului)
        ));

        if ($customdata) {
            // 4. Verificam daca exista un record valid in mdl_customfield_data si extragem valoarea 'intvalue'
            $intvalue = $customdata->intvalue;

            // Conditii pentru redirectionare:
            if ($is_ip_valid) {
                // Daca IP-ul este in whitelist, nu se face nicio redirectionare
                return;  // Permite accesul
            } else {
                // Daca IP-ul nu este in whitelist
                if ($intvalue == 1) {
                    // Daca intvalue este 1, blocam accesul
                    error_log("Course_ID: {$course_id}, Access_External: {$intvalue} Invalid IP: {$user_ip} - User Details: ID={$USER->id}, Username={$USER->username} - Email={$USER->email} - Redirecting to error page.");
                    redirect(new moodle_url('/local/external_Course_settings/error.php')); // Redirectioneaza catre pagina de eroare
                }
                // Daca intvalue NU este 1, accesul este permis, nu se face nicio redirectionare
            }
        } else {
            // Daca nu se gaseste nicio valoare pentru customfield_data
            error_log("Course_ID: {$course_id}, Nu s-a gasit niciun record in customfield_data pentru acest curs.");
            redirect(new moodle_url('/local/external_Course_settings/error.php')); // Redirectioneaza catre pagina de eroare
        }
    } else {
        // Daca nu se gaseste campul cu shortname-ul 'external_course'
        error_log("Course_ID: {$course_id}, Nu s-a gasit niciun camp cu shortname-ul specificat.");
        redirect(new moodle_url('/local/external_Course_settings/error.php')); // Redirectioneaza catre pagina de eroare
    }
}

// Functia care va fi apelata la accesarea unui curs
function local_external_course_settings_extend_navigation_course($navref, $course, $context) {
    // Verifica IP-ul utilizatorului la accesarea unui curs
    check_user_ip();
}

// Functia care va fi apelata cand un utilizator se logheaza
function local_external_course_settings_user_login($user) {
    // Verifica IP-ul utilizatorului la logare
    check_user_ip();
}

// Functia de activare a pluginului
function local_external_course_settings_activate() {
    global $DB;

    $existing_category = $DB->get_record('customfield_category', array('name' => 'External_Course_Settings'));
    $existing_field = $DB->get_record('customfield_field', array('shortname' => 'external_course'));
    

    if (!$existing_category) {
        $record = new stdClass();
        $record->name = 'External_Course_Settings';
        $record->description = 'Settings for external courses. No - access denied, Yes - access permitted';
        $record->contextlevel = CONTEXT_COURSE;
        $record->descriptionformat = 0; // Descrierea in format text (plain)
        $record->sortorder = 0;         // Ordinea de sortare
        $record->timecreated = time();
        $record->timemodified = time();
        $record->component = 'core_course'; // Componenta asociata
        $record->area = 'course';           // Zona asociata
        $record->contextid = 1; // Contextul pentru sistem

        $DB->insert_record('customfield_category', $record);
    }
    
	$category_record = $DB->get_record('customfield_category', array('name' => 'External_Course_Settings'));
    $id_customfield_category = $category_record->id;
	
    if (!$existing_field) {
        $field_record = new stdClass();
        $field_record->shortname = 'external_course';
        $field_record->name = 'Access External Course';
        $field_record->type = 'select';
        $field_record->description = 'Settings for external courses. No - access denied, Yes - access permitted';
        $field_record->descriptionformat = 1; // Descrierea in format text (plain)
        $field_record->sortorder = 0;         // Ordinea de sortare
        $field_record->categoryid = $id_customfield_category;
        $field_record->configdata = json_encode([
            'required' => 1,
            'uniquevalues' => 0,
            'options' => "No\r\nYes", // Optiuni pentru campul de tip select
            'defaultvalue' => 'No',
            'locked' => 0,
            'visibility' => 2,
        ]);
        $field_record->timecreated = time();
        $field_record->timemodified = time();

        $DB->insert_record('customfield_field', $field_record);
    }
}

// Functia de dezactivare a pluginului
function local_external_course_settings_deactivate() {
    global $DB;

    $existing_category = $DB->get_record('customfield_category', array('name' => 'External_Course_Settings'));
    if ($existing_category) {
        $DB->delete_records('customfield_field', array('shortname' => 'external_course'));
        $DB->delete_records('customfield_category', array('name' => 'External_Course_Settings'));
    }

    if ($DB->get_manager()->field_exists('course', 'restriction_course')) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('restriction_course');
        $DB->get_manager()->drop_field($table, $field);
    }

    return true;
}

// Functie pentru a adauga campul 'restriction_course' in setarile cursului
function local_external_course_settings_extend_course_settings_navigation($settingsnav, $course, $context) {
    global $PAGE;

    // Adaugam un nou camp in setarile cursului
    $url = new moodle_url('/local/external_Course_settings/edit.php', array('id' => $course->id));
    $settingsnav->add('External Course Settings', $url);
}

// Functie pentru a salva valoarea campului 'restriction_course'
function local_external_course_settings_save_restriction($courseid, $restriction) {
    global $DB;

    // Actualizam campul 'restriction_course' in tabelul 'course'
    $DB->set_field('course', 'restriction_course', $restriction, array('id' => $courseid));
}
