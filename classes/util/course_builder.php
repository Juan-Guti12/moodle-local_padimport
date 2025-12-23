<?php
namespace local_padimport\util;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');
require_once($GLOBALS['CFG']->dirroot . '/course/modlib.php');

class course_builder {

    public function apply_model(int $courseid, array $model): void {
        $course = get_course($courseid);

        if (empty($model['tabs'])) {
            return;
        }

        foreach ($model['tabs'] as $tab) {
            $tabname = trim((string)($tab['name'] ?? ''));
            if ($tabname === '') {
                continue;
            }

            // Buscar la sección/pestaña real por NOMBRE (no por contador).
            // Esto evita que REA 1 caiga en "Este es tu CADI".
            $sectioninfo = $this->ensure_section_by_name($courseid, $tabname);
            $sectionnum  = (int)$sectioninfo['num'];

            // Crear items en esa sección
            foreach (($tab['items'] ?? []) as $item) {
                $type    = $item['type'] ?? 'label';
                $title   = (string)($item['title'] ?? 'Contenido');
                $html    = (string)($item['html'] ?? '');
                $daysdue = (int)($item['daysdue'] ?? 14);

                if ($type === 'assign') {
                    $this->create_assign($course, $sectionnum, $title, $html, $daysdue);
                } else if ($type === 'quiz') {
                    $this->create_quiz($course, $sectionnum, $title, $html, $daysdue);
                } else {
                    $this->create_label($course, $sectionnum, $html);
                }
            }
        }

        rebuild_course_cache($courseid, true);
    }

    /**
     * Busca una sección por nombre EXACTO (para formatos con pestañas).
     * Devuelve ['id' => course_sections.id, 'num' => course_sections.section]
     * NO renombra ni crea secciones nuevas (solo usa la plantilla).
     */
    private function ensure_section_by_name(int $courseid, string $name): array {
        global $DB;

        $name = trim($name);

        $sql = "SELECT id, section
                  FROM {course_sections}
                 WHERE course = :course
                   AND " . $DB->sql_compare_text('name') . " = " . $DB->sql_compare_text(':name') . "
              ORDER BY section ASC";

        $sec = $DB->get_record_sql($sql, [
            'course' => $courseid,
            'name'   => $name
        ]);

        if ($sec) {
            return ['id' => (int)$sec->id, 'num' => (int)$sec->section];
        }

        // Si no existe, fallamos explícitamente para que ajustes la plantilla o el parser.
        throw new \moodle_exception("No existe la pestaña/sección '$name' en la plantilla. Verifica que el curso plantilla tenga esa pestaña con ese nombre exacto.");
    }

    private function create_label(\stdClass $course, int $sectionnum, string $html): void {
        global $DB;

        if (trim(strip_tags($html)) === '') {
            return;
        }

        $moddata = new \stdClass();
        $moddata->course   = $course->id;
        $moddata->section  = $sectionnum;

        $moddata->module     = (int)$DB->get_field('modules', 'id', ['name' => 'label'], MUST_EXIST);
        $moddata->modulename = 'label';

        $moddata->visible = 1;
        $moddata->name = '';
        $moddata->intro = $html;
        $moddata->introformat = FORMAT_HTML;

        // Evita warnings en Moodle 4.x
        $moddata->cmidnumber = '';

        add_moduleinfo($moddata, $course);
    }

    private function create_assign(
        \stdClass $course,
        int $sectionnum,
        string $name,
        string $introhtml,
        int $daysdue
    ): void {
        global $DB;

        $moddata = new \stdClass();

        $moddata->course     = $course->id;
        $moddata->section    = $sectionnum;
        $moddata->module     = (int)$DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST);
        $moddata->modulename = 'assign';
        $moddata->visible    = 1;

        $moddata->name        = $name;
        $moddata->intro       = $introhtml;
        $moddata->introformat = FORMAT_HTML;
        $moddata->alwaysshowdescription = 1;

        // Campos obligatorios (NO NULL)
        $moddata->submissiondrafts = 0;
        $moddata->requiresubmissionstatement = 0;
        $moddata->sendnotifications = 0;
        $moddata->sendlatenotifications = 0;
        $moddata->sendstudentnotifications = 0;

        // Fechas
        $now = time();
        $moddata->allowsubmissionsfromdate = $now;
        $moddata->duedate = $now + ($daysdue * 86400);
        $moddata->cutoffdate = 0;
        $moddata->gradingduedate = 0;

        // Calificación
        $moddata->grade = 100;
        $moddata->completionsubmit = 0;

        // Opciones
        $moddata->teamsubmission = 0;
        $moddata->requireallteammemberssubmit = 0;
        $moddata->blindmarking = 0;
        $moddata->attemptreopenmethod = 'none';
        $moddata->markingworkflow = 0;
        $moddata->markingallocation = 0;

        // Submisiones
        $moddata->assignsubmission_file_enabled = 1;
        $moddata->assignsubmission_file_maxfiles = 5;
        $moddata->assignsubmission_file_maxsizebytes = 0;
        $moddata->assignsubmission_onlinetext_enabled = 0;

        // Grupos
        $moddata->groupmode = 0;
        $moddata->groupingid = 0;

        // Evita warnings en Moodle 4.x
        $moddata->cmidnumber = '';

        add_moduleinfo($moddata, $course);
    }

    private function create_quiz(
        \stdClass $course,
        int $sectionnum,
        string $name,
        string $introhtml,
        int $daysdue
    ): void {
        global $DB;

        $moddata = new \stdClass();
        $moddata->course  = $course->id;
        $moddata->section = $sectionnum;

        $moddata->module     = (int)$DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
        $moddata->modulename = 'quiz';

        $moddata->visible = 1;
        $moddata->name = $name;
        $moddata->intro = $introhtml;
        $moddata->introformat = FORMAT_HTML;

        $now = time();
        $moddata->timeclose = $now + ($daysdue * 86400);
        $moddata->grade = 10;
        $moddata->sumgrades = 0;

        // Evita warnings en Moodle 4.x
        $moddata->cmidnumber = '';

        add_moduleinfo($moddata, $course);
    }
}
