<?php
namespace local_padimport\service;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/externallib.php');

use local_padimport\util\pad_parser;
use local_padimport\util\course_builder;

class importer {

    public function import(
        int $basecourseid,
        int $categoryid,
        string $newfullname,
        string $newshortname,
        string $excelfilepath
    ): int {
        global $USER;

        // 1) Duplicar curso plantilla (sin logs / sin usuarios).
        $newcourseid = $this->clone_course(
            $basecourseid,
            $categoryid,
            $newfullname,
            $newshortname,
            (int)$USER->id
        );

        // 2) Parsear Excel a modelo.
        $parser = new \local_padimport\util\pad_parser();
        $model  = $parser->parse_blocks_v19($excelfilepath);

        // 3) Crear actividades/recursos definidos en el modelo.
        $builder = new course_builder();
        $builder->apply_model($newcourseid, $model);

        return $newcourseid;
    }

    private function clone_course(
        int $templatecourseid,
        int $categoryid,
        string $fullname,
        string $shortname,
        int $userid
    ): int {
        // Duplicar el curso (Moodle 4.2) evitando logs para que no ensucie la salida.
        $result = \core_course_external::duplicate_course(
            $templatecourseid,
            $fullname,
            $shortname,
            $categoryid,
            1, // visible
            [
                ['name' => 'logs', 'value' => 0],
                ['name' => 'users', 'value' => 0],
            ]
        );

        $newcourseid = (int)($result['id'] ?? 0);
        if (!$newcourseid) {
            throw new \moodle_exception('No se pudo duplicar el curso: respuesta sin id.');
        }

        rebuild_course_cache($newcourseid, true);
        return $newcourseid;
    }
}
