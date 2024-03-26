<?php

require __DIR__ . '/index.php';

$content = file_get_contents(__DIR__ . '/test.md');

// Add indent level to the Markdown string to see if indent level less than 4 breaks the filter functionality
if ($dent = (int) ($_GET['dent'] ?? 0)) {
    $content = str_repeat(' ', $dent) . strtr($content, ["\n" => "\n" . str_repeat(' ', $dent)]);
}

echo '<form method="get">';
echo '<p>Add indent level: <input max="10" min="0" name="dent" onfocus="this.select();" step="1" style="background:0 0;border:0;font:inherit;margin:0;padding:0;" placeholder="0" type="number" value="' . $dent . '"></p>';
echo '<button type="submit">';
echo 'Run Test';
echo '</button>';
echo '</form>';

echo '<div style="display:flex;flex-direction:row;gap:15px;">';

echo '<div style="background:#fff;border:2px solid #080;color:#000;display:flex;flex-direction:column;font:normal normal 13px/15px monospace;gap:1px;overflow:auto;padding:1px;white-space:pre;">';

foreach (x\markdown__filter\rows\encode($content) as $row) {
    [$part, $status] = $row;
    $part = "" !== trim($part, " \t") ? htmlspecialchars($part) : '<br>';
    $part = preg_replace_callback('/^[ \t]+|[ \t]+$/m', static function ($m) {
        return strtr($m[0], [
            ' ' => '<span style="opacity:0.25;">·</span>'
        ]);
    }, $part);
    if (0 === $status) {
        echo '<div style="border:2px solid #800;">' . $part . '</div>';
    } else {
        echo '<div style="border:2px solid #080;">' . $part . '</div>';
    }
}

echo '</div>';

$content = x\markdown__filter\rows($content, static function ($part, $status) {
    if (0 === $status) {
        return $part;
    }
    if ("" === $part) {
        return $part;
    }
    $n = strspn($part, ' ');
    $prefix = substr($part, 0, $n);
    $part = substr($part, $n);
    if ("" === $part) {
        return $part;
    }
    if ('<' === $part[0] && '>' === substr($part, -1) && false !== strpos($part, ':') && false === strpos($part, "\n")) {
        return $prefix . "╔══════╗\n" . $prefix . "║ TEST ║\n" . $prefix . "╚══════╝";
    }
    return $prefix . $part;
});

echo '<div style="background:#fff;color:#000;font:normal normal 13px/15px monospace;gap:1px;overflow:auto;white-space:pre;">';

echo preg_replace_callback('/^[ \t]+|[ \t]+$/m', static function ($m) {
    return strtr($m[0], [
        ' ' => '<span style="opacity:0.25;">·</span>'
    ]);
}, htmlspecialchars($content));

echo '</div>';

echo '</div>';

exit;