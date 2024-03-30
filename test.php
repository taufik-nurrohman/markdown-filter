<?php

require __DIR__ . '/index.php';

$content = file_get_contents(__DIR__ . '/test.md');

// Add attributes to the HTML elements in the Markdown string to see if attributes break the filter functionality
if ($attributes = !empty($_GET['attributes'])) {
    $content = strtr($content, [
        '<asdf>' => '<asdf asdf="asdf">',
        '<div>' => '<div asdf="asdf">'
    ]);
}

// Add indent level to the Markdown string to see if indent level less than 4 breaks the filter functionality
if ($dent = (int) ($_GET['dent'] ?? 0)) {
    $content = str_repeat(' ', $dent) . strtr($content, ["\n" => "\n" . str_repeat(' ', $dent)]);
}

$out = '<!DOCTYPE html>';
$out .= '<html dir="ltr">';
$out .= '<head>';
$out .= '<meta charset="utf-8">';
$out .= '<title>';
$out .= 'Markdown Filter';
$out .= '</title>';
$out .= '</head>';
$out .= '<body>';

$out .= '<form method="get">';
$out .= '<p>Add indent level: <input max="10" min="0" name="dent" onfocus="this.select();" step="1" style="background:0 0;border:0;font:inherit;margin:0;padding:0;vertical-align:middle;width:2.5em;" placeholder="0" type="number" value="' . $dent . '"></p>';
$out .= '<p>Add attributes to the HTML elements: <input' . ($attributes ? ' checked' : "") . ' name="attributes" type="checkbox" value="1" style="display:inline-block;margin:0;padding:0;vertical-align:middle;"></p>';
$out .= '<p>';
$out .= '<button type="submit">';
$out .= 'Run Test';
$out .= '</button>';
$out .= '</p>';
$out .= '</form>';

$out .= '<div style="display:flex;flex-direction:row;gap:15px;">';
$out .= '<div style="background:#fff;border:2px solid #080;color:#000;display:flex;flex:1;flex-direction:column;font:normal normal 13px/15px monospace;gap:1px;overflow:auto;padding:1px;white-space:pre;">';

foreach (x\markdown_filter\rows\split($content) as $row) {
    [$part, $status] = $row;
    $part = "" !== trim($part, " \t") ? htmlspecialchars($part) : '<br>';
    $part = preg_replace_callback('/^[ \t]+|[ \t]+$/m', static function ($m) {
        return strtr($m[0], [
            ' ' => '<span style="opacity:0.25;">·</span>'
        ]);
    }, $part);
    if (0 === $status) {
        $out .= '<div style="border:2px solid #800;">' . $part . '</div>';
    } else if (1 === $status) {
        $out .= '<div style="border:2px solid #080;">';
        foreach (x\markdown_filter\row\split($part) as $v) {
            $out .= '<span style="color:#' . (0 === $v[1] ? '800' : '000') . ';">' . $v[0] . '</span>';
        }
        $out .= '</div>';
    } else {
        $out .= '<div style="border:2px solid #080;">' . $part . '</div>';
    }
}

$out .= '</div>';

$content = x\markdown_filter\rows($content, static function ($part, $status) {
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

$out .= '<div style="background:#fff;color:#000;flex:1;font:normal normal 13px/15px monospace;overflow:auto;white-space:pre;">';

$out .= preg_replace_callback('/^[ \t]+|[ \t]+$/m', static function ($m) {
    return strtr($m[0], [
        ' ' => '<span style="opacity:0.25;">·</span>'
    ]);
}, htmlspecialchars($content));

$out .= '</div>';
$out .= '</div>';
$out .= '</body>';
$out .= '</html>';

echo $out;