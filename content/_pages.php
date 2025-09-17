<?php
declare(strict_types=1);

return [
    'start' => [
        'title' => 'Start',
        'description' => 'Willkommen bei der Seepferdchen‑Garde.',
        'cms' => false,
        'nav' => false,
        'nav_label' => 'Start',
        'nav_order' => 10,
        'canonical' => '/',
    ],
    'schwimmkurse' => [
        'title' => 'Schwimmkurse',
        'description' => 'Informationen zu unseren Schwimmkursen.',
        'cms' => true,
        'nav' => true,
        'nav_label' => 'Schwimmkurse',
        'nav_order' => 20,
    ],
    'ueber-mich' => [
        'title' => 'Über mich',
        'description' => 'Über den Trainer.',
        'cms' => true,
        'nav' => true,
        'nav_label' => 'Über mich',
        'nav_order' => 30,
    ],
    'kontakt' => [
        'title' => 'Kontakt',
        'description' => 'Kontaktinformationen und Formular.',
        'cms' => false,
        'nav' => true,
        'nav_label' => 'Kontakt',
        'nav_order' => 40,
    ],
    'anmeldung' => [
        'title' => 'Anmeldung',
        'description' => 'Anmeldung für Kurse.',
        'cms' => true,
        'nav' => true,
        'nav_label' => 'Anmeldung',
        'nav_order' => 50,
    ],
    'impressum' => [
        'title' => 'Impressum',
        'description' => 'Rechtliche Anbieterkennzeichnung.',
        'cms' => true,
        'nav' => true,
        'nav_label' => 'Impressum',
        'nav_order' => 90,
    ],
    'datenschutz' => [
        'title' => 'Datenschutzerklärung',
        'description' => 'Hinweise zum Datenschutz.',
        'cms' => true,
        'nav' => true,
        'nav_label' => 'Datenschutz',
        'nav_order' => 100,
    ],
];
