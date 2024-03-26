<?php

require __DIR__ . '/index.php';

$content = file_get_contents(__DIR__ . '/test.md');

echo '<div style="display:flex;flex-direction:row;gap:15px;">';

echo '<div style="background:#fff;border:2px solid #080;color:#000;display:flex;flex-direction:column;font:normal normal 13px/15px monospace;gap:1px;overflow:auto;padding:1px;white-space:pre;">';

foreach (x\markdown__filter\rows\encode($content) as $row) {
    [$block, $status] = $row;
    $block = "" !== $block ? htmlspecialchars($block) : '<br>';
    if (0 === $status) {
        echo '<div style="border:2px solid #800;">' . $block . '</div>';
    } else {
        echo '<div style="border:2px solid #080;">' . $block . '</div>';
    }
}

echo '</div>';

$content = x\markdown__filter\rows($content, static function ($chunk, $status) {
    if (0 === $status) {
        return $chunk;
    }
    if ("" === $chunk) {
        return $chunk;
    }
    $n = strspn($chunk, ' ');
    $dent = substr($chunk, 0, $n);
    $chunk = substr($chunk, $n);
    if ('<' === $chunk[0] && '>' === substr($chunk, -1) && false !== strpos($chunk, ':')) {
        return $dent . "╔══════╗\n" . $dent . "║ TEST ║\n" . $dent . "╚══════╝";
    }
    return $dent . $chunk;
});

echo '<div style="background:#fff;color:#000;display:flex;flex-direction:column;font:normal normal 13px/15px monospace;gap:1px;overflow:auto;white-space:pre;">';

echo htmlspecialchars($content);

echo '</div>';

echo '</div>';

exit;