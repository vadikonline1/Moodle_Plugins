<?php
// Înregistrează evenimentele relevante pentru plugin
defined('MOODLE_INTERNAL') || die();

$observers = array(
    // Observer pentru evenimentul de logare al utilizatorului
    array(
        'eventname'   => '\core\event\user_loggedin',
        'callback'    => 'local_external_course_settings_user_login',
    ),
    
    // Observer pentru vizualizarea unui curs
    array(
        'eventname'   => '\core\event\course_viewed',
        'callback'    => 'local_external_course_settings_extend_navigation_course',
    ),
);
