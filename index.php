<?php

namespace x {
    function markdown_filter(?string $content, callable $fn): ?string {

    }
}

namespace x\markdown_filter {
    function row(?string $content, callable $fn): ?string {
        if ("" === ($content = (string) $content)) {
            $content = row\join([["", 1]], $fn);
            return "" !== $content ? $content : null;
        }
        return row\join(row\split($content), $fn);
    }
    function rows(?string $content, callable $fn): ?string {
        if ("" === ($content = (string) $content)) {
            $content = rows\join([["", 1]], $fn);
            return "" !== $content ? $content : null;
        }
        return rows\join(rows\split($content), $fn);
    }
}

namespace x\markdown_filter\row {
    function join(array $chunks, callable $fn) {
        foreach ($chunks as &$chunk) {
            [$v, $status] = $chunk;
            $chunk = \call_user_func($fn, $v, $status);
        }
        unset($chunk);
        return \implode("", $chunks);
    }
    function split(string $content) {
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
    function join(array $blocks, callable $fn) {
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
                $row = join(split(\implode("\n", $parts)), $fn);
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
                $row = join(split(\implode("\n", $parts)), $fn);
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
                $row = join(split(\implode("\n", $parts)), $fn);
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
    function split(string $content) {
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
                if (($dent_prev = \strspn($prev, ' ')) < 4) {
                    $prev = \substr($prev, $dent_prev);
                }
                // Is in a code block?
                if ($dent_prev >= 4 && 0 === $blocks[$block][1]) {
                    // End of the code block?
                    if ("" !== $row && $dent < 4) {
                        if ("\n" === \substr($prev, -1)) {
                            $blocks[$block][0] = \substr($blocks[$block][0], 0, -1);
                            $blocks[++$block] = ["", 1];
                        }
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                    continue;
                }
                // Is in a code block?
                if (false !== \strpos('`~', $prev[0]) && ($n = \strspn($prev, $prev[0])) >= 3 && 0 === $blocks[$block][1]) {
                    // End of the code block?
                    if ($row === \str_repeat($prev[0], $n)) {
                        $blocks[$block++][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    // Continue the code block…
                    $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                    continue;
                }
                // Is in a HTML block?
                if ('<' === $prev[0] && 0 === $blocks[$block][1]) {
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
                    if ("" !== $row) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, 1]; // End of the HTML block
                    continue;
                }
                // Is in a list block?
                if (false !== \strpos('*+-', $prev[0]) && 2 === $blocks[$block][1]) {
                    // TODO
                }
                // Is in a list block?
                $n = \strspn($prev, '0123456789');
                if ($n > 0 && $n < 10 && 2 === $blocks[$block][1]) {
                    // TODO
                }
                // Current block is a blank line…
                if ("" === $row) {
                    $blocks[++$block] = [$prefix, 1];
                    continue;
                }
                // Start of a tight code block
                if (false !== \strpos('`~', $row[0]) && ($n = \strspn($row, $row[0])) >= 3) {
                    $test = \strstr($row, "\n", true) ?: $row;
                    // Character “`” cannot exist in the info string if code block fence uses “`” character(s)
                    if ('`' === $row[0] && false !== \strpos(\substr($test, $n), '`')) {
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, 0];
                    continue;
                }
                // Start of a tight quote block
                if ('>' === $row[0] && '>' !== $prev[0]) {
                    $blocks[++$block] = [$prefix . $row, 2];
                    continue;
                }
                // Start of a tight HTML block
                if ('<' === $row[0]) {
                    $t = \substr(\strtok($row, " \n\r\t>"), 1);
                    if (false !== \strpos($t, ':') || false !== \strpos($t, '@')) {
                        $blocks[++$block] = [$prefix . $row, 1];
                        continue;
                    }
                    if (false !== \strpos('!?', $t[0])) {
                        $blocks[++$block] = [$prefix . $row, 0];
                        continue;
                    }
                    // <https://spec.commonmark.org/0.31.2#html-blocks>
                    if (false !== \strpos(',address,article,aside,base,basefont,blockquote,body,caption,center,col,colgroup,dd,details,dialog,dir,div,dl,dt,fieldset,figcaption,figure,footer,form,frame,frameset,h1,h2,h3,h4,h5,h6,head,header,hr,html,iframe,legend,li,link,main,menu,menuitem,nav,noframes,ol,optgroup,option,p,pre,param,script,search,section,source,style,summary,table,tbody,td,textarea,tfoot,th,thead,title,tr,track,ul,', ',' . \trim($t, '/') . ',')) {
                        $blocks[++$block] = [$prefix . $row, 0];
                        continue;
                    }
                    $test = \trim($row);
                    if ('<' . $t . '>' === $test || '>' === \substr($test, -1) && \preg_match('/^<' . \trim($t, '/') . '(\s(?>"[^"]*"|\'[^\']*\'|[^>])*)?>$/', $test)) {
                        $blocks[++$block] = [$prefix . $row, 0];
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, 1];
                    continue;
                }
                // Start of a tight horizontal rule
                $test = \strtr($row, [
                    "\t" => "",
                    ' ' => ""
                ]);
                if (false !== \strpos('*_', $row[0]) && \strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    $block += 1; // Force a new block after it
                    continue;
                }
                // Start of a tight header block?
                $n = \strspn($row, '#');
                if ($n > 0 && $n < 7 && false !== \strpos(" \t", \substr($row . ' ', $n, 1))) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    $block += 1; // Force a new block after it
                    continue;
                }
                // Start of a tight list block
                if (false !== \strpos('*+-', $row[0])) {
                    if (1 === \strlen($row) || false !== \strpos(" \t", $row[1])) {
                        $blocks[++$block] = [$prefix . $row, 2];
                        continue;
                    }
                    $blocks[$block][0] .= "\n" . $prefix . $row;
                    continue;
                }
                // Start of a tight list block
                $n = \strspn($row, '0123456789');
                if (false !== \strpos(').', \substr($row, $n, 1))) {
                    // <https://spec.commonmark.org/0.31.2#example-304>
                    if (1 !== (int) \substr($row, 0, $n)) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
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
            if ("" === $row) {
                $blocks[++$block] = [$prefix, 1];
                continue;
            }
            // Start of a code block
            if (false !== \strpos('`~', $row[0]) && ($n = \strspn($row, $row[0])) >= 3) {
                $test = \strstr($row, "\n", true) ?: $row;
                // Character “`” cannot exist in the info string if code block fence uses “`” character(s)
                if ('`' === $row[0] && false !== \strpos(\substr($test, $n), '`')) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    continue;
                }
                $blocks[++$block] = [$prefix . $row, 0];
                continue;
            }
            // Start of a code block
            if ($dent >= 4) {
                $blocks[++$block] = [$prefix . $row, 0];
                continue;
            }
            // Start of a quote block
            if ('>' === $row[0]) {
                $blocks[++$block] = [$prefix . $row, 2];
                continue;
            }
            // Start of a HTML block
            if ('<' === $row[0]) {
                $t = \substr(\strtok($row, " \n\r\t>"), 1);
                if (false !== \strpos($t, ':') || false !== \strpos($t, '@')) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    continue;
                }
                if (false !== \strpos('!?', $t[0])) {
                    $blocks[++$block] = [$prefix . $row, 0];
                    continue;
                }
                // <https://spec.commonmark.org/0.31.2#html-blocks>
                if (false !== \strpos(',address,article,aside,base,basefont,blockquote,body,caption,center,col,colgroup,dd,details,dialog,dir,div,dl,dt,fieldset,figcaption,figure,footer,form,frame,frameset,h1,h2,h3,h4,h5,h6,head,header,hr,html,iframe,legend,li,link,main,menu,menuitem,nav,noframes,ol,optgroup,option,p,pre,param,script,search,section,source,style,summary,table,tbody,td,textarea,tfoot,th,thead,title,tr,track,ul,', ',' . \trim($t, '/') . ',')) {
                    $blocks[++$block] = [$prefix . $row, 0];
                    continue;
                }
                $test = \trim($row);
                if ('<' . $t . '>' === $test || '>' === \substr($test, -1) && \preg_match('/^<' . \trim($t, '/') . '(\s(?>"[^"]*"|\'[^\']*\'|[^>])*)?>$/', $test)) {
                    $blocks[++$block] = [$prefix . $row, 0];
                    continue;
                }
                $blocks[++$block] = [$prefix . $row, 1];
                continue;
            }
            // Start of a horizontal rule
            $test = \strtr($row, [
                "\t" => "",
                ' ' => ""
            ]);
            if (false !== \strpos('*-_', $row[0]) && \strlen($test) === ($n = \strspn($test, $test[0])) && $n > 2) {
                $blocks[++$block] = [$prefix . $row, 1];
                $block += 1; // Force a new block after it
                continue;
            }
            // Start of a header block?
            $n = \strspn($row, '#');
            if ($n > 0 && $n < 7 && false !== \strpos(" \t", \substr($row . ' ', $n, 1))) {
                $blocks[++$block] = [$prefix . $row, 1];
                $block += 1; // Force a new block after it
                continue;
            }
            // Start of a list block
            if (false !== \strpos('*+-', $row[0])) {
                if (1 === \strlen($row) || false !== \strpos(" \t", $row[1])) {
                    $blocks[++$block] = [$prefix . $row, 2];
                    continue;
                }
                $blocks[$block][0] .= "\n" . $prefix . $row;
                continue;
            }
            // Start of a list block
            $n = \strspn($row, '0123456789');
            if (false !== \strpos(').', \substr($row, $n, 1))) {
                if ($n + 1 === \strlen($row) || false !== \strpos(" \t", \substr($row, $n + 1, 1))) {
                    $blocks[++$block] = [$prefix . $row, 2];
                    continue;
                }
            }
            // Default is to start a new block…
            $blocks[++$block] = [$prefix . $row, 1];
        }
        return $blocks;
    }
}