<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/external_course_settings/lib.php');


if ($hassiteconfig) {
    $settings = new admin_settingpage('local_external_course_settings', get_string('pluginname', 'local_external_course_settings'));
	$enabled = get_config('local_external_course_settings', 'enable');
	
    $settings->add(new admin_setting_configcheckbox(
        'local_external_course_settings/enable',
        get_string('enable', 'local_external_course_settings'),
        get_string('enable_desc', 'local_external_course_settings'),
        0
    ));
	
	$settings->add(new admin_setting_confightmleditor(
        'local_external_course_settings/message',
        get_string('message', 'local_external_course_settings'),
        get_string('message_desc', 'local_external_course_settings'),
        ''
    ));
	
	$settings->add(new admin_setting_configtext(
        'local_external_course_settings/user_ip',
        new lang_string('user_ip', 'local_external_course_settings'),
        new lang_string('user_ip_desc', 'local_external_course_settings'),
        'HTTP_X_FORWARDER_FOR'
    ));

	$settings->add(new admin_setting_configtextarea(
        'local_external_course_settings/ipwhitelist',
        new lang_string('ipwhitelist', 'local_external_course_settings'),
        new lang_string('ipwhitelistdesc', 'local_external_course_settings'),
        '',
        PARAM_RAW,
        '50',
        '10'
    ));

	if ($enabled) {
		local_external_course_settings_activate();
	} else {
		local_external_course_settings_deactivate();
	}

    $ADMIN->add('localplugins', $settings);
}
