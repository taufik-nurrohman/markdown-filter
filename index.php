<?php

namespace x {
    function markdown_filter(?string $content, callable $fn): ?string {

    }
}

namespace x\markdown_filter {
    function row(?string $content, callable $fn): ?string {
        if ("" === ($content = (string) $content)) {
            $content = row\decode([["", 1]], $fn);
            return "" !== $content ? $content : null;
        }
        return row\decode(row\encode($content), $fn);
    }
    function rows(?string $content, callable $fn): ?string {
        if ("" === ($content = (string) $content)) {
            $content = rows\decode([["", 1]], $fn);
            return "" !== $content ? $content : null;
        }
        return rows\decode(rows\encode($content), $fn);
    }
}

namespace x\markdown_filter\row {
    function decode(array $chunks, callable $fn) {
        foreach ($chunks as &$chunk) {
            [$v, $status] = $chunk;
            $chunk = \call_user_func($fn, $v, $status);
        }
        unset($chunk);
        return \implode("", $chunks);
    }
    function encode(string $content) {
        $chunks = [];
        while (false !== ($chop = \strpbrk($content, '`'))) {
            if ("" !== ($v = \substr($content, 0, \strlen($content) - \strlen($chop)))) {
                $content = \substr($content, \strlen($v));
                $chunks[] = [$v, 1];
            }
            if (0 === \strpos($chop, '`') && \preg_match('/(`+)[^`]+\1(?!`)/', $chop, $m)) {
                $content = \substr($content, \strlen($m[0]));
                $chunks[] = [$m[0], 0];
                continue;
            }
            $content = \substr($content, \strlen($chop));
            $chunks[] = [$chop, 1];
        }
        if ("" !== $content) {
            $chunks[] = [$content, 1];
        }
        return $chunks;
    }
}

namespace x\markdown_filter\rows {
    function decode(array $blocks, callable $fn) {
        $tags = ',address,article,aside,base,basefont,blockquote,body,caption,center,col,colgroup,dd,details,dialog,dir,div,dl,dt,fieldset,figcaption,figure,footer,form,frame,frameset,h1,h2,h3,h4,h5,h6,head,header,hr,html,iframe,legend,li,link,main,menu,menuitem,nav,noframes,ol,optgroup,option,p,pre,param,script,search,section,source,style,summary,table,tbody,td,textarea,tfoot,th,thead,title,tr,track,ul,';
        foreach ($blocks as &$block) {
            [$row, $status] = $block;
            if ("" === $row || 0 === $status) {
                $block = \call_user_func($fn, $row, $status);
                continue;
            }
            if ('>' === $row[0]) {
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    if ('>' === $v[0]) {
                        if (' ' === $v[1] ?? "") {
                            $parts[$k] = \substr($v, 2);
                            continue;
                        }
                        $parts[$k] = \substr($v, 1);
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
            $prefix = "";
            if ($dent < 4) {
                $prefix = \substr($row, 0, $dent);
                $row = \substr($row, $dent);
            }
            if ("" !== \trim($prev = $blocks[$block][0] ?? "")) {
                if (($d = \strspn($prev, ' ')) < 4) {
                    $prev = \substr($prev, $d);
                }
                // Is in a code block?
                if (false !== \strpos('`~', $prev[0]) && ($n = \strspn($prev, $prev[0])) >= 3) {
                    $test = \strstr($prev, "\n", true) ?: $prev;
                    // Character “`” cannot exist in the info string if code block fence uses “`” character(s)
                    if ('`' === $prev[0] && false !== \strpos(\substr($test, $n), '`')) {
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    // End of the code block?
                    if ($row === \str_repeat($prev[0], $n)) {
                        $blocks[$block++][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    // Continue the code block…
                    $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Is in a HTML comment block?
                if (0 === \strpos($prev, '<!--')) {
                    // End of the HTML comment block?
                    if (false !== \strpos($row, '-->')) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        $blocks[$block][1] = 0;
                        $block += 1;
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Is in a character data block?
                if (0 === \strpos($prev, '<![CDATA[')) {
                    // End of the character data block?
                    if (false !== \strpos($row, ']]>')) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        $blocks[$block][1] = 0;
                        $block += 1;
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Is in a processing instruction block?
                if (0 === \strpos($prev, '<?')) {
                    // End of the character data block?
                    if (false !== \strpos($row, '?>')) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        $blocks[$block][1] = 0;
                        $block += 1;
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Previous block is an element block?
                if ('<' === $prev[0]) {
                    if ("" === $row) {
                        $blocks[++$block] = [$prefix, 1];
                        continue;
                    }
                    $t = \substr(\strtok($prev, " \n\r\t>"), 1);
                    if (false !== \strpos($t, ':') || false !== \strpos($t, '@')) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    $test = \strstr($prev, "\n", true) ?: $prev;
                    if (\trim($test, " \t") === '<' . $t . '>' || \preg_match('/^\s*<' . $t . '(?>\s(?>"[^"]*"|\'[^\']*\'|[^>])*)?>\s*/', $test)) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        $blocks[$block][1] = 0;
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                $n = \strspn($prev, '#');
                // Previous block is a header block?
                if ($n > 0 && $n < 7 && false !== \strpos(" \t", \substr($prev . ' ', $n, 1))) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    continue;
                }
                // Previous block is a quote block and current block is also a quote block?
                if ($row && '>' === $row[0] && '>' === $prev[0]) {
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                // Previous block is a list block?
                if (false !== \strpos('*+-', $prev[0])) {
                    if ('-' === $prev || false !== \strpos(" \t", $prev[1])) {
                        $blocks[$block][1] = 2;
                        if ($dent >= 2 + $d) {
                            $blocks[$block][0] .= "\n" . $prefix . $row;
                            continue;
                        }
                        if ("" === $row) {
                            $blocks[$block][0] .= "\n" . $prefix;
                            continue;
                        }
                        if ("\n" === \substr($v = \rtrim($prev, " \t"), -1)) {
                            $blocks[$block][0] = \substr($v, 0, -1);
                            $blocks[++$block] = ["", 1]; // End of the list block
                        }
                        $blocks[++$block] = [$prefix . $row, 1]; // End of the list block
                        continue;
                    }
                    // Previous block is a horizontal rule?
                    $test = \strtr($prev, [
                        "\t" => "",
                        ' ' => ""
                    ]);
                    if (\strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    // Continue the list block…
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                // Previous block is a horizontal rule?
                if ('_' === $prev[0]) {
                    $test = \strtr($prev, [
                        "\t" => "",
                        ' ' => ""
                    ]);
                    if (\strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    // Previous block is not a horizontal rule…
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                // Previous block is a list block?
                $n = \strspn($prev, '0123456789');
                if ($n > 0 && $n < 10 && false !== \strpos(').', \substr($prev, $n, 1))) {
                    $blocks[$block][1] = 2;
                    if ($n + 1 === \strlen($prev) || false !== \strpos(" \t", \substr($prev, $n + 1, 1))) {
                        if ($dent >= $n + 2 + $d) {
                            $blocks[$block][0] .= "\n" . $prefix . $row;
                            continue;
                        }
                        if ("" === $row) {
                            $blocks[$block][0] .= "\n" . $prefix;
                            continue;
                        }
                        if ("\n" === \substr($v = \rtrim($prev, " \t"), -1)) {
                            $blocks[$block][0] = \substr($v, 0, -1);
                            $blocks[++$block] = ["", 1]; // End of the list block
                        }
                        $blocks[++$block] = [$prefix . $row, 1]; // End of the list block
                        continue;
                    }
                    // Continue the list block…
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                // Is in code block?
                if (\strspn($prev, ' ') >= 4) {
                    // End of the code block?
                    if ("" !== $row && $dent < 4) {
                        if ("\n" === \substr($v = \rtrim($prev, " \t"), -1)) {
                            $blocks[$block][0] = \substr($v, 0, -1);
                            $blocks[++$block] = ["", 1];
                        }
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    // Continue the code block…
                    $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                    $blocks[$block][1] = 0;
                    continue;
                }
                // Current block is a blank line…
                if ("" === $row) {
                    $blocks[++$block] = [$prefix, 1];
                    continue;
                }
                // Start of a tight header block?
                $n = \strspn($row, '#');
                if ($n > 0 && $n < 7 && false !== \strpos(" \t", \substr($row . ' ', $n, 1))) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    continue;
                }
                // Start of a tight element block end?
                if (0 === \strpos($row, '</') && \preg_match('/^<\/([a-z][a-z\d-]*)>/i', $row, $m)) {
                    if ("" !== \substr($row, \strlen($m[0]))) {
                        // <https://spec.commonmark.org/0.30#html-blocks>
                        if (false !== \strpos($tags, ',' . $m[1] . ',')) {
                            $blocks[++$block] = [$prefix . $row, 0];
                            continue;
                        }
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, 0];
                    continue;
                }
                // Start of a tight element block start?
                if ('<' === $row[0] && \preg_match('/^<([a-z][a-z\d-]*)(\s(?>"[^"]*"|\'[^\']*\'|[^>])*)?>/i', $row, $m)) {
                    if ("" !== \substr($row, \strlen($m[0]))) {
                        // <https://spec.commonmark.org/0.30#html-blocks>
                        if (false !== \strpos($tags, ',' . $m[1] . ',')) {
                            $blocks[++$block] = [$prefix . $row, 0];
                            continue;
                        }
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, 0];
                    continue;
                }
                // Start of a tight quote block?
                if ('>' === $row[0]) {
                    $blocks[++$block] = [$prefix . $row, 2];
                    continue;
                }
                // Start of a tight code block?
                if (false !== \strpos('`~', $row[0]) && ($n = \strspn($row, $row[0])) > 2) {
                    // Character “`” cannot exist in the info string if code block fence uses “`” character(s)
                    if ('`' === $row[0] && false !== \strpos(\substr($row, $n), '`')) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, 1];
                    continue;
                }
                // End of a header block?
                if ('-' === $row[0] && \strlen($row) === ($n = \strspn($row, $row[0])) && "\n" !== \substr(\rtrim($prev, " \t"), -1)) {
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                // Start of a tight horizontal rule?
                if ('_' === $row[0]) {
                    $test = \strtr($row, [
                        "\t" => "",
                        ' ' => ""
                    ]);
                    if (\strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    // Not a tight horizontal rule…
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                // Start of a tight list block?
                if (false !== \strpos('*+-', $row[0])) {
                    if (1 === \strlen($row) || false !== \strpos(" \t", $row[1])) {
                        $blocks[++$block] = [$prefix . $row, 2];
                        continue;
                    }
                    // Start of a tight horizontal rule?
                    $test = \strtr($row, [
                        "\t" => "",
                        ' ' => ""
                    ]);
                    if (\strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    // Not a tight horizontal rule…
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                // Start of a tight list block?
                $n = \strspn($row, '0123456789');
                if (false !== \strpos(').', \substr($row, $n, 1))) {
                    if ($n + 1 === \strlen($row) || false !== \strpos(" \t", \substr($row, $n + 1, 1))) {
                        $blocks[++$block] = [$prefix . $row, 2];
                        continue;
                    }
                }
                // Continue the current block…
                $blocks[$block][0] .= "\n" . $prefix . $row;
                continue;
            }
            // Start a new block…
            $blocks[++$block] = [$prefix . $row, 1];
        }
        return $blocks;
    }
}