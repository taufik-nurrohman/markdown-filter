<?php

if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    exit;
}

error_reporting(E_ALL | E_STRICT);

ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('html_errors', 1);

require __DIR__ . '/index.php';

$content = file_get_contents(__DIR__ . '/test.md');

// Add attributes to the HTML elements in the Markdown string to see if attributes break the filter functionality
if ($attributes = !empty($_GET['attributes'])) {
    $content = strtr($content, [
        '<asdf>' => '<asdf asdf="asdf">',
        '<div>' => '<div asdf="asdf">',
        '<pre>' => '<pre asdf="asdf">'
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
$out .= '<style>*{box-sizing:border-box;margin:0;padding:0}body{background:#fff;color:#000}button{cursor:pointer;padding:0 2px}p{margin:16px 0 0;padding:0 16px}</style>';
$out .= '</head>';
$out .= '<body>';

$out .= '<form method="get">';
$out .= '<p>Add indent level: <input max="10" min="0" name="dent" onfocus="this.select();" step="1" style="background:0 0;border:0;font:inherit;vertical-align:middle;width:2.5em;" placeholder="0" type="number" value="' . $dent . '"></p>';
$out .= '<p>Add attributes to the HTML elements: <input' . ($attributes ? ' checked' : "") . ' name="attributes" type="checkbox" value="1" style="display:inline-block;margin:0;padding:0;vertical-align:middle;"></p>';
$out .= '<p>';
$out .= '<button type="submit">';
$out .= 'Test';
$out .= '</button>';
$out .= '</p>';
$out .= '</form>';

$out .= '<div style="background:#0e0;border:8px solid #0e0;display:flex;flex-direction:row;font:normal normal 13px/15px monospace;gap:8px;margin:16px 0 0;">';
$out .= '<div style="background:#fff;border:2px solid #080;color:#000;display:flex;flex:1;flex-direction:column;gap:1px;overflow:auto;padding:1px;white-space:pre;">';

// For demonstration purpose
foreach (x\markdown_filter\rows\split($content) as $row) {
    [$part, $status] = $row;
    $part = htmlspecialchars($chunk = $part);
    if (0 === $status) {
        $out .= '<div style="background:#f99;border:2px solid #800;">' . $part . '</div>';
    } else if (1 === $status) {
        $out .= '<div style="border:2px solid #080;">';
        if ("" === $chunk) {
            $out .= '<br>';
        } else {
            foreach (x\markdown_filter\row\split($chunk) as $v) {
                $v[0] = htmlspecialchars($v[0]);
                if (1 === $v[1]) {
                    $out .= $v[0];
                    continue;
                }
                $out .= '<span style="background:#f99;">' . $v[0] . '</span>';
            }
        }
        $out .= '</div>';
    } else {
        $out .= '<div style="border:2px solid #080;">' . $part . '</div>';
    }
}

$out .= '</div>';

// Actual usage
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
    $part = x\markdown_filter\row($part, static function ($v, $status) {
        if (0 === $status) {
            return $v;
        }
        return strtr($v, [':)' => '😊']);
    });
    return $prefix . $part;
});

$out .= '<div style="background:#fff;color:#000;flex:1;overflow:auto;white-space:pre;">';

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