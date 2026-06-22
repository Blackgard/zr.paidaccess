<?php

$moduleRoot = dirname(__DIR__);
$production = require $moduleRoot . '/autoload.production.map.php';

preg_match_all(
    "/'([^']+)'\\s*=>\\s*'([^']+\\.php)'/",
    (string)file_get_contents($moduleRoot . '/tests/Support/ModuleClassLoader.php'),
    $matches,
    PREG_SET_ORDER
);

$testMap = [];
foreach ($matches as $match) {
    $testMap[$match[1]] = $match[2];
}

$extra = [];
foreach ($testMap as $class => $path) {
    if (!isset($production[$class])) {
        $extra[$class] = $path;
        continue;
    }
    if ($production[$class] !== $path) {
        fwrite(STDERR, "PATH MISMATCH: $class\n  production: {$production[$class]}\n  test: $path\n");
    }
}

$export = var_export($extra, true);
file_put_contents($moduleRoot . '/autoload.test-extra.map.php', "<?php\n\nreturn " . $export . ";\n");

echo count($extra) . " test-extra classes\n";
