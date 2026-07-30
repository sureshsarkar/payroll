<?php
// Quick duplicate-column scanner for migration files.
// Reports any (table, column) pair that appears in more than one migration
// (could indicate redundant adds, drops, or modifies).
$dir = "C:/xampp/htdocs/MBSGuru1/database/migrations";
$files = glob("$dir/*.php");
$addsByFile = [];
foreach ($files as $f) {
    $body = file_get_contents($f);
    if (preg_match_all('/Schema::table\(\s*[\'"]([a-z_]+)[\'"]\s*,\s*function[^)]*\)\s*\{(.*?)\}\s*\)/s', $body, $matches)) {
        foreach ($matches[1] as $i => $table) {
            $block = $matches[2][$i];
            if (preg_match_all('/\$table->[a-zA-Z]+\(\s*[\'"]([a-z_]+)[\'"]/', $block, $m2)) {
                foreach ($m2[1] as $col) {
                    $key = $table . '.' . $col;
                    $addsByFile[$key][] = basename($f);
                }
            }
        }
    }
}
$dups = array_filter($addsByFile, fn ($a) => count(array_unique($a)) > 1);
if (empty($dups)) {
    echo "No (table.column) appears in more than one migration.\n";
    exit;
}
echo "Columns touched by multiple migrations (could be add + drop, or two adds):\n\n";
foreach ($dups as $k => $files) {
    $uniq = array_unique($files);
    if (count($uniq) < 2) continue;
    echo "  $k\n";
    foreach ($uniq as $f) echo "      $f\n";
    echo "\n";
}
