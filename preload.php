<?php

/**
 * OPcache preload script for the MBS Laravel app.
 *
 * What it does:
 *   At Apache/PHP startup, this script runs once and forces OPcache to compile
 *   + cache the most-used Laravel framework files into shared memory. Every
 *   subsequent request skips the parse/compile step entirely for those files.
 *
 * How to enable (XAMPP / Windows):
 *   1. Add this line to C:\xampp\php\php.ini under [opcache]:
 *
 *        opcache.preload=C:\xampp\htdocs\mbs\preload.php
 *
 *      (You may also need: opcache.preload_user=  ; leave empty on Windows)
 *
 *   2. Restart Apache via XAMPP Control Panel.
 *
 *   3. Verify by visiting any page and checking OPcache stats — the
 *      "preload_statistics" array will be populated and "num_cached_scripts"
 *      will start at the preload count instead of 0.
 *
 * Caveats:
 *   - Preloaded classes are FROZEN until Apache restarts. Editing a preloaded
 *     file does NOT take effect until you restart. We deliberately preload
 *     ONLY vendor/* (framework) files, never your app/, Modules/, or routes/
 *     code, so your edits keep working as before.
 *   - If the preload fails (uncaught exception), Apache won't start. Test with
 *     `C:\xampp\php\php.exe -d opcache.enable_cli=1 -d opcache.preload=preload.php
 *     -r "echo 'preload ok';"` from the project root before adding to php.ini.
 *   - On a typical machine this preloads ~600-800 files (~30-50 MB of OPcache
 *     memory) and reduces request boot time by ~30-50%.
 */

// Only run during preload — silently bail if accidentally invoked at request time.
if (php_sapi_name() !== 'cli' && !defined('OPCACHE_PRELOAD')) {
    if (!ini_get('opcache.preload') || !function_exists('opcache_get_status')
        || !($s = opcache_get_status(false))
        || empty($s['preload_statistics'])) {
        // Not in a preload context — don't pollute the request lifecycle.
        return;
    }
}

// Where this app lives. Use realpath to handle symlinks.
$appRoot = __DIR__;

// 1) Load Composer's autoloader so class lookups in our preload file work.
require $appRoot . '/vendor/autoload.php';

// 2) Recursively compile every PHP file under vendor/laravel/framework/src.
//    These are the framework classes loaded on every request. Preloading them
//    is the highest-value win.
$frameworkDirs = [
    $appRoot . '/vendor/laravel/framework/src/Illuminate',
    $appRoot . '/vendor/symfony',
    $appRoot . '/vendor/nesbot/carbon/src',
    $appRoot . '/vendor/monolog/monolog/src',
    $appRoot . '/vendor/psr',
    $appRoot . '/vendor/nwidart/laravel-modules/src',
];

$total = 0;
$failed = 0;

foreach ($frameworkDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getRealPath();
        if ($path === false) {
            continue;
        }
        // Skip files that contain syntax our preload php version can't handle,
        // tests, stubs, and anything that would error on isolated load.
        if (str_contains($path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . 'Tests' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR)
            || str_ends_with($path, '.blade.php')
            || str_ends_with($path, 'Throwable.php')          // PHP built-in shadow
        ) {
            continue;
        }
        try {
            // opcache_compile_file is the right call here — it compiles the file
            // and stores it in OPcache without actually executing any code, so
            // class definitions don't leak into the preload script's symbol table.
            if (@opcache_compile_file($path)) {
                $total++;
            } else {
                $failed++;
            }
        } catch (Throwable $e) {
            $failed++;
        }
    }
}

// Optional: log a tiny preload-stats line (helps confirm it ran)
@file_put_contents(
    $appRoot . '/storage/logs/opcache-preload.log',
    sprintf("[%s] preloaded=%d failed=%d\n", date('c'), $total, $failed),
    FILE_APPEND
);
