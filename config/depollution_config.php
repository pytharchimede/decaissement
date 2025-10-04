<?php
// Configuration centralisée Dépollution (Twilio / Emails / Notifications)
return [
    'twilio' => [
        'sid' => 'ACded19f6cd55b2ba3d18c13f438f1e878',
        'token' => '7f1136b112e6d8cb4a6af94223d0872e',
        'from' => 'whatsapp:+2250711048002'
    ],
    'emails' => [
        'recipients' => [
            'braud@fidest.org' => 'Alex BRAUD',
            'amani_ulrich@outlook.fr' => 'Ulrich AMANI'
        ]
    ],
    'dg_whatsapp' => '+2250544577666',
    'log' => [
        'enable_db_log' => true
    ]
];
