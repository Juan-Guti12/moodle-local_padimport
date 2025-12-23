<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/padimport:import' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_PREVENT,
        ],
    ],
];
