<?php

return [
    'version' => '2026-11-27.1',
    'date' => '27 noiembrie 2026',
    'publication_approved' => env('LEGAL_PUBLICATION_APPROVED', false),
    'operator' => [
        'name' => 'BANA RARES-NICOLAE PERSOANA FIZICA AUTORIZATA',
        'cif' => '33388821',
        'register' => 'F2014000987292',
        'address' => 'Str. Națiunii nr. 65, Ploiești, Prahova, România',
        'email' => 'office@ipontaj.ro',
        'phone' => '0735863399',
        'domain' => 'https://ipontaj.ro',
        'vat_registered' => false,
    ],
    'hosting' => [
        'provider' => 'cyber_Folks',
        'url' => 'https://cyberfolks.ro',
        'services' => 'Găzduire aplicație, bază de date MySQL, email și backupuri zilnice',
        'contract_entity' => env('LEGAL_HOSTING_ENTITY'),
        'data_location' => env('LEGAL_DATA_LOCATION'),
        'backup_retention_days' => (int) env('LEGAL_BACKUP_RETENTION_DAYS', 0),
    ],
    'retention' => [
        'plan_approved' => env('LEGAL_RETENTION_APPROVED', false),
        'log_days' => (int) env('LEGAL_LOG_RETENTION_DAYS', 0),
    ],
    'checks' => [
        'hosting_dpa' => env('LEGAL_HOSTING_DPA_CONFIRMED', false),
        'stripe_dpa' => env('LEGAL_STRIPE_DPA_CONFIRMED', false),
        'caen' => env('LEGAL_CAEN_CONFIRMED', false),
        'invoicing' => env('LEGAL_INVOICING_CONFIRMED', false),
        'operations' => env('LEGAL_OPERATIONS_CONFIRMED', false),
        'mail' => env('LEGAL_MAIL_DELIVERY_CONFIRMED', false),
        'consumer_flow' => env('LEGAL_CONSUMER_FLOW_CONFIRMED', false),
    ],
    'source_archive' => storage_path('app/private/releases/ipontaj-source.zip'),
    'anpc_sal_asset' => 'legal/anpc-sal.png',
];
