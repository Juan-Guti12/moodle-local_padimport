<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_padimport',
        get_string('pluginname', 'local_padimport'),
        new moodle_url('/local/padimport/index.php')
    ));
}
