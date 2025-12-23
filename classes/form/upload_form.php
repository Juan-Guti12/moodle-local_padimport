<?php
namespace local_padimport\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class upload_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'h', get_string('importpad', 'local_padimport'));

        $mform->addElement('course', 'basecourseid', get_string('basecourse', 'local_padimport'));
        $mform->addHelpButton('basecourseid', 'basecourse', 'local_padimport');
        $mform->setType('basecourseid', PARAM_INT);
        $mform->addRule('basecourseid', null, 'required', null, 'client');

        $mform->addElement('text', 'fullname', get_string('fullname', 'local_padimport'), ['size' => 80]);
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_padimport'), ['size' => 40]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');


        $mform->addElement('filemanager', 'excel', get_string('excel', 'local_padimport'), null, [
            'maxfiles' => 1,
            'accepted_types' => ['.xlsx', '.xlsm', '.xls'],
        ]);
        $mform->addRule('excel', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('import', 'local_padimport'));
    }
}
