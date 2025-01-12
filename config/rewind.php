<?php

// config for Avocet Shores/LaravelRewind
return [

    /*
    |--------------------------------------------------------------------------
    | Record Rewinds
    |--------------------------------------------------------------------------
    |
    | By default, we will not store rewinds events (e.g. undo or redo) in the
    | versions table. If you would like to store these events, you may
    | enable this option. This can be overridden on a per-model basis using
    | the shouldRecordRewinds method.
    */

    'record_rewinds' => false,

    /*
    |--------------------------------------------------------------------------
    | Rewind Versions Table Name
    |--------------------------------------------------------------------------
    |
    | Here you may define the name of the table that stores the versions.
    | By default, it is set to "rewind_versions". You may override it
    | via an environment variable or update this value directly.
    |
    */

    'table_name' => env('LARAVEL_REWIND_TABLE', 'rewind_versions'),

    /*
    |--------------------------------------------------------------------------
    | Rewind Versions Table User ID Column
    |--------------------------------------------------------------------------
    |
    | Here you may define the name of the column that stores the user ID.
    | By default, it is set to "user_id". You may override it via an
    | environment variable or update this value directly.
    |
    */

    'user_id_column' => env('LARAVEL_REWIND_USER_ID_COLUMN', 'user_id'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Here you may define the model that represents the user table.
    | By default, it is set to "App\Models\User". You may override it
    | via an environment variable or update this value directly.
    |
    */

    'user_model' => env('LARAVEL_REWIND_USER_MODEL', App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Rewind Versions Table Model
    |--------------------------------------------------------------------------
    |
    | Here you may define the model that represents the versions table.
    | By default, it is set to "AvocetShores\LaravelRewind\Models\RewindVersion".
    | You may override it via an environment variable or update this value directly.
    |
    */

    'rewind_version_model' => env('LARAVEL_REWIND_VERSION_MODEL', AvocetShores\LaravelRewind\Models\RewindVersion::class),

    /*
    |--------------------------------------------------------------------------
    | Rewind Versions Table Connection
    |--------------------------------------------------------------------------
    |
    | Here you may define the connection that the versions table uses.
    | By default, it is set to "null" which uses the default connection.
    | You may override it via an environment variable or update this value directly.
    |
    */

    'database_connection' => env('LARAVEL_REWIND_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Track Authenticated User
    |--------------------------------------------------------------------------
    |
    | If true, the package will automatically store the currently authenticated
    | user's ID in the versions table (when available). If your application
    | doesn't track or need user IDs, set this value to false.
    |
    */

    'track_user' => true,

    /*
    |--------------------------------------------------------------------------
    | Default to Tracking All Attributes
    |--------------------------------------------------------------------------
    |
    | If this is set to true, any model using the Rewindable trait will track
    | all of its attributes by default. You can still override by specifying
    | $rewindable or $rewindAll on individual models.
    |
    */

    'tracks_all_by_default' => false,
];
