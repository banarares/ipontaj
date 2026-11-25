<?php

$gtmId = strtoupper(trim((string) env('GOOGLE_TAG_MANAGER_ID', '')));

return [
    // Loaded only after optional analytics consent; malformed IDs are ignored.
    'google_tag_manager_id' => preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtmId) === 1 ? $gtmId : null,
];
