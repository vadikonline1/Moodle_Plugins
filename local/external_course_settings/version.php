<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_external_course_settings';
$plugin->version = 2024122600;  // Data versiunii: 2024-12-26
$plugin->requires = 2020110300;  // Versiunea Moodle necesară
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0';
$plugin->dependencies = array(
    'core' => 2019111800, // Dependență față de Moodle core
);