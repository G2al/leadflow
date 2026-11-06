<?php

return [

    /*
    | Timestamps are stored in UTC and shown to panel users in this timezone.
    */
    'display_timezone' => env('LEADFLOW_DISPLAY_TIMEZONE', 'Europe/Rome'),

    /*
    | Privacy policy linked from the public embedded form. When empty, the form
    | shows a neutral placeholder.
    */
    'privacy_url' => env('LEADFLOW_PRIVACY_URL'),

];
