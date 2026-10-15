<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded owner
    |--------------------------------------------------------------------------
    |
    | The first operator is created by OwnerSeeder. Password stays in the
    | environment and is never committed.
    |
    */

    'seed_user_name' => env('SEED_USER_NAME', 'Nelson Mboya'),

    'seed_user_email' => env('SEED_USER_EMAIL'),

    'seed_user_password' => env('SEED_USER_PASSWORD'),

];
