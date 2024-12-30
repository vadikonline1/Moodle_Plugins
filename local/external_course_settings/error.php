<?php
// local/external_course_settings/error.php

require_once('../../config.php'); // Încarcă config-ul Moodle

// Asigură-te că pagina este accesibilă doar pentru utilizatori autentificați
require_login();

// Obține mesajul de eroare din baza de date
global $DB;
$message_record = $DB->get_record('config_plugins', ['plugin' => 'local_external_course_settings', 'name' => 'message']);
$message = $message_record ? $message_record->value : 'Accesul la acest curs nu este permis din locația curentă.';

// Setează URL-ul, contextul și titlul paginii
$PAGE->set_url(new moodle_url('/local/external_course_settings/error.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Access Denied');

// Afișează header-ul
echo $OUTPUT->header();

// Afișează mesajul de eroare
echo html_writer::tag('div', 'Access Denied', ['class' => 'alert alert-danger text-center']);
echo html_writer::tag('div', $message, ['class' => 'alert alert-info']);
// Afișează butonul "My Courses"
echo '<div style="text-align: center;"><a href="' . $CFG->wwwroot . '/my/courses.php" class="btn btn-primary">' . get_string('mycourses', 'moodle') . '</a></div>';
// Afișează footer-ul
echo $OUTPUT->footer();
