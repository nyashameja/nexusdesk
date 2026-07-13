<?php

declare(strict_types=1);

/**
 * Enabled modules. Core help-desk modules ship enabled; extension modules
 * (CRM, Projects, Live Chat, ...) register here later without touching core.
 * The Kernel boots each module's ModuleInterface during startup.
 */
return [
    'core'          => true,   // auth, users, departments, settings (always on)
    'tickets'       => true,
    'knowledge_base' => true,
    'portal'        => true,
    'reporting'     => true,
    'zoho'          => true,
    'api'           => true,
    'ai'            => true,

    // Future extension modules (disabled until built):
    'crm'           => false,
    'projects'      => false,
    'assets'        => false,
    'live_chat'     => false,
];
