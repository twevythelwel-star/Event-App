<?php

/*
 * Router for PHP's built-in server when it is started directly (instead of `php artisan serve`) so that
 * upload settings can be passed with -d, e.g. from the project root:
 *
 *   php -d upload_tmp_dir=backend/storage/uploads-tmp -d upload_max_filesize=6M -d post_max_size=8M \
 *       -S 127.0.0.1:8000 -t backend/public backend/dev-server.php
 *
 * Laravel's own router resolves the public directory from the working directory, so switch to it first.
 */
chdir(__DIR__ . '/public');

return require __DIR__ . '/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
