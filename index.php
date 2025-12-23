<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_padimport');

require_login();
require_capability('moodle/site:config', context_system::instance());


$PAGE->set_url(new moodle_url('/local/padimport/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('importpad', 'local_padimport'));
$PAGE->set_heading(get_string('importpad', 'local_padimport'));

require_once(__DIR__ . '/classes/form/upload_form.php');

$mform = new \local_padimport\form\upload_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/search.php'));
} else if ($data = $mform->get_data()) {
    $draftid = $data->excel;
    $usercontext = context_user::instance($USER->id);

    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id DESC', false);
    if (empty($files)) {
        throw new moodle_exception('nofile');
    }
$file = reset($files);

$tempdir = make_temp_directory('local_padimport');
$tempfile = $tempdir . '/' . clean_param($file->get_filename(), PARAM_FILE);
$file->copy_content_to($tempfile);

// Tomar categoría del curso base automáticamente.
$basecourse = get_course((int)$data->basecourseid);
$categoryid = (int)$basecourse->category;

$importer = new \local_padimport\service\importer();
$newcourseid = $importer->import(
    (int)$data->basecourseid,
    $categoryid,
    (string)$data->fullname,
    (string)$data->shortname,
    $tempfile
);

redirect(
    new moodle_url('/course/view.php', ['id' => $newcourseid]),
    get_string('success', 'local_padimport', format_string($data->fullname))
);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importpad', 'local_padimport'));
$mform->display();
echo $OUTPUT->footer();
