<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact details
    |--------------------------------------------------------------------------
    |
    | Facts the public site shows (resources/views/home.blade.php). Values in
    | [brackets] are placeholders from the landing page design: replace them.
    |
    */

    'phone' => [
        'display' => '[000 000 000]',
        'dial' => '+34000000000',
    ],

    'address' => ['[Street and number]', '[Postcode, city]'],

    'legal_name' => '[Legal name of the pharmacy]',

    'licence' => '[000000]',

    // Where "See tonight's on-duty pharmacy" points
    'on_duty_url' => '#visit',

    /*
    |--------------------------------------------------------------------------
    | Opening hours
    |--------------------------------------------------------------------------
    |
    | By ISO weekday (1 = Monday): [opens, closes] as "HH:MM", or null when
    | closed all day. "Open now" is worked out from these in the timezone
    | below. They are the design's placeholder hours: confirm them.
    |
    */

    'timezone' => 'Europe/Madrid',

    'hours' => [
        1 => ['09:00', '21:00'],
        2 => ['09:00', '21:00'],
        3 => ['09:00', '21:00'],
        4 => ['09:00', '21:00'],
        5 => ['09:00', '21:00'],
        6 => ['09:30', '14:00'],
        7 => null,
    ],

];
