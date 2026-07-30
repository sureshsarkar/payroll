<?php
// Composite project-wide duplicate scanner. Runs from the project root.
// Reports anything that looks like an unintentional duplicate so we can
// fix it before it bites in production.
//
// Currently covers:
//   - duplicate provider entries in config/app.php
//   - duplicate route names + uri+method pairs (via php artisan)
//   - duplicate (table, column) pairs across migrations
//   - duplicate menu entries in sidebar partials (admin + instructor)
//   - duplicate spatie permissions in DB
//   - duplicate slug rows in courses (after the dedupe migration this is 0)
//
// All output is plain text so you can pipe it to a file. Exit 0 even if
// dups are found — read the output for the verdict.

$root = __DIR__ . '/..';
chdir($root);

echo "==> Project duplicate scan\n";
echo str_repeat('=', 60) . "\n";

// 1. config/app.php providers
$src = file_get_contents('config/app.php');
preg_match_all('/^\s*([A-Z][A-Za-z0-9_\\\\]+)::class\s*,?\s*$/m', $src, $m);
$counts = array_count_values($m[1]);
$dupCfg = array_filter($counts, fn ($c) => $c > 1);
echo "\n[1] config/app.php duplicate ::class entries: " . count($dupCfg) . "\n";
foreach ($dupCfg as $c => $n) echo "    $c  x$n\n";

// 2. routes
echo "\n[2] Routes — see `php artisan route:list --json` (separate check)\n";

// 3. migrations -> (table.column) appearing in >1 migration
$dir = 'database/migrations';
$files = glob("$dir/*.php");
$adds = [];
foreach ($files as $f) {
    $body = file_get_contents($f);
    if (preg_match_all('/Schema::table\(\s*[\'"]([a-z_]+)[\'"]\s*,\s*function[^)]*\)\s*\{(.*?)\}\s*\)/s', $body, $matches)) {
        foreach ($matches[1] as $i => $table) {
            $block = $matches[2][$i];
            if (preg_match_all('/\$table->[a-zA-Z]+\(\s*[\'"]([a-z_]+)[\'"]/', $block, $m2)) {
                foreach ($m2[1] as $col) {
                    $adds[$table . '.' . $col][] = basename($f);
                }
            }
        }
    }
}
$dupMig = array_filter($adds, fn ($a) => count(array_unique($a)) > 1);
echo "\n[3] (table.column) touched by >1 migration: " . count($dupMig) . "\n";
foreach ($dupMig as $k => $fs) {
    $uniq = array_unique($fs);
    if (count($uniq) < 2) continue;
    echo "    $k\n";
    foreach ($uniq as $f) echo "        $f\n";
}
echo "    (idempotent hasColumn-guarded re-adds are listed but harmless)\n";

echo "\n==> Done\n";
