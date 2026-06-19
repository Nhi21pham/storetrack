<?php

/**
 * Override the bits of maatwebsite/excel that depend on a writable bind-mount
 * path. Everything else falls back to the package's default config (Laravel
 * merges per-key, so we only need to declare what differs).
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Temporary files
    |--------------------------------------------------------------------------
    |
    | By default the package writes processing scratch files under
    | storage/framework/cache/laravel-excel. That path lives in the host
    | bind mount, so it inherits the host user's UID/perms — which clashes
    | with the queue worker running as www-data inside the container.
    |
    | Using /tmp keeps these short-lived intermediate files inside the
    | container's writable tmpfs and makes the setup portable across hosts.
    | The final .xlsx still lands on the `temp` disk (storage/app/temp), as
    | configured in config/filesystems.php.
    |
    */
    'temporary_files' => [
        'local_path'                     => storage_path('framework/cache/laravel-excel'),
        'local_permissions'              => [],
        'remote_disk'                    => null,
        'remote_prefix'                  => null,
        'force_resync_remote'            => null,
    ],

];
