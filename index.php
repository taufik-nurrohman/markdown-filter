<?php

namespace x\markdown__filter {
    function row(?string $content, callable $fn): ?string {
        if ("" === ($content = (string) $content)) {
            return "";
        }
        return $content;
    }
    function rows(?string $content, callable $fn): ?string {
        if ("" === ($content = (string) $content)) {
            $content = rows\decode([["", 1]], $fn);
            return "" !== $content ? $content : null;
        }
        return rows\decode(rows\encode($content), $fn);
    }
}

namespace x\markdown__filter\row {
    function decode(array $blocks, callable $fn) {}
    function encode(string $content) {}
}

namespace x\markdown__filter\rows {
    function decode(array $blocks, callable $fn) {
        foreach ($blocks as &$block) {
            [$row, $status] = $block;
            if ("" === $row || 0 === $status) {
                $block = \call_user_func($fn, $row, $status);
                continue;
            }
            if ('>' === $row[0]) {
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    if ('> ' === \substr($v, 0, 2)) {
                        $parts[$k] = \substr($v, 2);
                        continue;
                    }
                    if ('>' === $v[0]) {
                        $parts[$k] = \substr($v, 1);
                        continue;
                    }
                }
                $row = decode(encode(\implode("\n", $parts)), $fn);
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    $parts[$k] = ("" === $v ? '>' : '> ' . $v);
                }
                $block = \call_user_func($fn, \implode("\n", $parts), $status);
                continue;
            }
            if (false !== \strpos('*+-', $row[0]) && false !== \strpos(" \t", $row[1] ?? "")) {
                $parts = \explode("\n", $row);
                $n = 1 + \strspn(\substr($row, 1), " \t");
                $fix = \substr($row, 0, $n);
                foreach ($parts as $k => $v) {
                    if (0 === $k || \strspn($v, " \t") >= $n) {
                        $parts[$k] = \substr($v, $n);
                        continue;
                    }
                }
                $row = decode(encode(\implode("\n", $parts)), $fn);
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    if (0 === $k) {
                        $parts[$k] = $fix . $v;
                        continue;
                    }
                    $parts[$k] = ("" === $v ? "" : \str_repeat(' ', $n) . $v);
                }
                $block = \call_user_func($fn, \implode("\n", $parts), $status);
                continue;
            }
            $n = \strspn($row, '0123456789');
            if ($n <= 9 && false !== \strpos(').', \substr($row, $n, 1)) && false !== \strpos(" \t", \substr($row, $n + 1, 1))) {
                $parts = \explode("\n", $row);
                $n = $n + 1 + \strspn(\substr($row, $n + 1), " \t");
                $fix = \substr($row, 0, $n);
                foreach ($parts as $k => $v) {
                    if (0 === $k || \strspn($v, " \t") >= $n) {
                        $parts[$k] = \substr($v, $n);
                        continue;
                    }
                }
                $row = decode(encode(\implode("\n", $parts)), $fn);
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    if (0 === $k) {
                        $parts[$k] = $fix . $v;
                        continue;
                    }
                    $parts[$k] = ("" === $v ? "" : \str_repeat(' ', $n) . $v);
                }
                $block = \call_user_func($fn, \implode("\n", $parts), $status);
                continue;
            }
            $block = \call_user_func($fn, $row, $status);
        }
        unset($block);
        return \implode("\n", $blocks);
    }
    function encode(string $content) {
        $block = -1;
        $blocks = [];
        $rows = \explode("\n", $content);
        foreach ($rows as $row) {
            // TODO: Keep the tab character(s) as-is!
            while (false !== ($before = \strstr($row, "\t", true))) {
                $v = \strlen($before);
                $row = $before . \str_repeat(' ', 4 - $v % 4) . \substr($row, $v + 1);
            }
            $dent = \strspn($row, ' ');
            if ($prev = $blocks[$block][0] ?? 0) {
                // Is in a code block?
                if (false !== \strpos('`~', $prev[0]) && ($n = \strspn($prev, $prev[0])) >= 3) {
                    $test = \strstr($prev, "\n", true) ?: $prev;
                    // Character ‘`’ cannot exist in the info string if code block fence uses ‘`’ character(s)
                    if ('`' === $prev[0] && false !== \strpos(\substr($test, $n), '`')) {
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                    // End of the code block?
                    if ($row === \str_repeat($prev[0], $n)) {
                        $blocks[$block++][0] .= "\n" . $row;
                        continue;
                    }
                    // Continue the code block…
                    $blocks[$block][0] .= "\n" . $row;
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Is in a HTML comment block?
                if (0 === \strpos($prev, '<!--')) {
                    // End of the HTML comment block?
                    if (false !== \strpos($row, '-->')) {
                        $blocks[$block][0] .= "\n" . $row;
                        $blocks[$block][1] = 0;
                        $block += 1;
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . $row;
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Is in a character data block?
                if (0 === \strpos($prev, '<![CDATA[')) {
                    // End of the character data block?
                    if (false !== \strpos($row, ']]>')) {
                        $blocks[$block][0] .= "\n" . $row;
                        $blocks[$block][1] = 0;
                        $block += 1;
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . $row;
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Is in a processing instruction block?
                if (0 === \strpos($prev, '<?')) {
                    // End of the character data block?
                    if (false !== \strpos($row, '?>')) {
                        $blocks[$block][0] .= "\n" . $row;
                        $blocks[$block][1] = 0;
                        $block += 1;
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . $row;
                    $blocks[$block][1] = 0;
                    continue;
                }
                $n = \strspn($prev, '#');
                // Previous block is a header block?
                if ($n > 0 && $n < 7 && false !== \strpos(" \t", \substr($prev . ' ', $n, 1))) {
                    $blocks[++$block] = [$row, 1];
                    continue;
                }
                // Previous block is an element block?
                if ($row && '<' === $row[0]) {
                    if (\preg_match('/^<[a-z][a-z\d-]*(\s(?>"[^"]*"|\'[^\']*\'|[^>])*)?>(\n|$)/i', $prev)) {
                        $blocks[$block][0] .= "\n" . $row;
                        $blocks[$block][1] = 0;
                        continue;
                    }
                    $blocks[++$block] = [$row, 1];
                    continue;
                }
                // Previous block is a quote block and current block is also a quote block?
                if ($row && '>' === $row[0] && '>' === $prev[0]) {
                    $blocks[$block][0] .= "\n" . $row;
                    continue;
                }
                // Previous block is a list block?
                if (false !== \strpos('*+-', $prev[0])) {
                    // End of the list block?
                    if ('-' === $prev || false !== \strpos(" \t", $prev[1]) && "" !== $row && $dent < 2) {
                        if ("\n" === \substr($prev, -1)) {
                            $blocks[$block][0] = \substr($blocks[$block][0], 0, -1);
                            $blocks[++$block] = ["", 1];
                        }
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                    // Previous block is a horizontal rule?
                    $test = \strtr($prev, [
                        "\t" => "",
                        ' ' => ""
                    ]);
                    if (\strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                    // Continue the list block…
                    $blocks[$block][0] .= "\n" . $row;
                    continue;
                }
                // Previous block is a horizontal rule?
                if ('_' === $prev[0]) {
                    $test = \strtr($prev, [
                        "\t" => "",
                        ' ' => ""
                    ]);
                    if (\strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                    // Previous block is not a horizontal rule…
                    $blocks[$block][0] .= "\n" . $row;
                    continue;
                }
                // Previous block is a list block?
                $n = \strspn($prev, '0123456789');
                if ($n > 0 && $n < 10 && false !== \strpos(').', \substr($prev, $n, 1))) {
                    if ($n + 1 === \strlen($prev) || false !== \strpos(" \t", \substr($prev, $n + 1, 1))) {
                        // End of the list block?
                        if ("" !== $row && $dent < $n + 2) {
                            if ("\n" === \substr($prev, -1)) {
                                $blocks[$block][0] = \substr($blocks[$block][0], 0, -1);
                                $blocks[++$block] = ["", 1];
                            }
                            $blocks[++$block] = [$row, 1];
                            continue;
                        }
                    }
                    // Continue the list block…
                    $blocks[$block][0] .= "\n" . $row;
                    continue;
                }
                // Is in code block?
                if (\strspn($prev, ' ') >= 4) {
                    // End of the code block?
                    if ("" !== $row && $dent < 4) {
                        if ("\n" === \substr($prev, -1)) {
                            $blocks[$block][0] = \substr($blocks[$block][0], 0, -1);
                            $blocks[++$block] = ["", 1];
                        }
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                    // Continue the code block…
                    $blocks[$block][0] .= "\n" . $row;
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Current block is a blank line…
                if ("" === $row) {
                    $blocks[++$block] = ["", 1];
                    continue;
                }
                // Start of a tight header block?
                $n = \strspn($row, '#');
                if ($n > 0 && $n < 7 && false !== \strpos(" \t", \substr($row . ' ', $n, 1))) {
                    $blocks[++$block] = [$row, 1];
                    continue;
                }
                // Start of a tight element block?
                if ('<' === $row[0]) {}
                // Start of a tight quote block?
                if ('>' === $row[0]) {
                    $blocks[++$block] = [$row, 1];
                    continue;
                }
                // Start of a tight code block?
                if (false !== \strpos('`~', $row[0]) && ($n = \strspn($row, $row[0])) > 2) {
                    // Character ‘`’ cannot exist in the info string if code block fence uses ‘`’ character(s)
                    if ('`' === $row[0] && false !== \strpos(\substr($row, $n), '`')) {
                        $blocks[$block][0] .= "\n" . $row;
                        continue;
                    }
                    $blocks[++$block] = [$row, 1];
                    continue;
                }
                // End of a header block?
                if ('-' === $row[0] && \strlen($row) === ($n = \strspn($row, $row[0])) && "\n" !== \substr($prev, -1)) {
                    $blocks[$block][0] .= "\n" . $row;
                    continue;
                }
                // Start of a tight horizontal rule?
                if ('_' === $row[0]) {
                    $test = \strtr($row, [
                        "\t" => "",
                        ' ' => ""
                    ]);
                    if (\strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                    // Not a tight horizontal rule…
                    $blocks[$block][0] .= "\n" . $row;
                    continue;
                }
                // Start of a tight list block?
                if (false !== \strpos('*+-', $row[0])) {
                    if (1 === \strlen($row) || false !== \strpos(" \t", $row[1])) {
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                    // Start of a tight horizontal rule?
                    $test = \strtr($row, [
                        "\t" => "",
                        ' ' => ""
                    ]);
                    if (\strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                    // Not a tight horizontal rule…
                    $blocks[$block][0] .= "\n" . $row;
                    continue;
                }
                // Start of a tight list block?
                $n = \strspn($row, '0123456789');
                if (false !== \strpos(').', \substr($row, $n, 1))) {
                    if ($n + 1 === \strlen($row) || false !== \strpos(" \t", \substr($row, $n + 1, 1))) {
                        $blocks[++$block] = [$row, 1];
                        continue;
                    }
                }
                // Continue the current block…
                $blocks[$block][0] .= "\n" . $row;
                continue;
            }
            // Start a new block…
            $blocks[++$block] = [$row, 1];
        }
        return $blocks;
    }
}