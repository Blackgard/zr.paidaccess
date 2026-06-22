<?php

$moduleRoot = dirname(__DIR__);
$content = file_get_contents($moduleRoot . '/include.php');
preg_match_all("/'([^']+)'\\s*=>\\s*'([^']+\\.php)'/", $content, $matches, PREG_SET_ORDER);

$map = [];
foreach ($matches as $match) {
    $map[$match[1]] = $match[2];
}

$export = var_export($map, true);
file_put_contents($moduleRoot . '/autoload.production.map.php', "<?php\n\nreturn " . $export . ";\n");

echo count($map) . " classes exported\n";
