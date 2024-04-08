<?php

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
        $chops = [];
        while (false !== ($chop = \strpbrk($content, '<&`'))) {
            if ("" !== ($v = \substr($content, 0, \strlen($content) - \strlen($chop)))) {
                $content = \substr($content, \strlen($v));
                $chops[] = [$v, 1];
            }
            if (0 === \strpos($chop, '<')) {
                // <https://spec.commonmark.org/0.31.2#html-comment>
                if (0 === \strpos($chop, '<!--') && ($n = \strpos($chop, '-->')) > 1) {
                    $content = \substr($content, $n += 3);
                    $chops[] = [\substr($chop, 0, $n), 0];
                    continue;
                }
                if (0 === \strpos($chop, '<![CDATA[') && ($n = \strpos($chop, ']]>')) > 8) {
                    $content = \substr($content, $n += 3);
                    $chops[] = [\substr($chop, 0, $n), 0];
                    continue;
                }
                if (0 === \strpos($chop, '<!') && \strpos($chop, '>') > 2 && \preg_match('/^<![a-z](?>"[^"]*"|\'[^\']*\'|[^>])+>/i', $chop, $m)) {
                    $content = \substr($content, \strlen($m[0]));
                    $chops[] = [$m[0], 0];
                    continue;
                }
                if (0 === \strpos($chop, '<' . '?') && \strpos($chop, '?' . '>') > 1 && \preg_match('/^<\?(?>"[^"]*"|\'[^\']*\'|[^>])+\?>/', $chop, $m)) {
                    $content = \substr($content, \strlen($m[0]));
                    $chops[] = [$m[0], 0];
                    continue;
                }
                // <https://spec.commonmark.org/0.31.2#raw-html>
                if (\preg_match('/^<\/[a-z][a-z\d-]*\s*>/i', $chop, $m)) {
                    $content = \substr($content, \strlen($m[0]));
                    $chops[] = [$m[0], 0];
                    continue;
                }
                // <https://spec.commonmark.org/0.31.2#raw-html>
                if (\preg_match('/^<[a-z][a-z\d-]*(?>\s+[a-z:_][\w.:-]*(?>\s*=\s*(?>"[^"]*"|\'[^\']*\'|[^\s"\'<=>`]+)?)?)*\s*\/?>/i', $chop, $m)) {
                    $content = \substr($content, \strlen($m[0]));
                    $chops[] = [$m[0], 0];
                    continue;
                }
                $content = \substr($content, 1);
                $chops[] = ['<', 1];
                continue;
            }
            if (0 === \strpos($chop, '&') && \strpos($chop, ';') > 1 && !\preg_match('/^&(?>#x[a-f\d]{1,6}|#\d{1,7}|[a-z][a-z\d]{1,31});/i', $chop, $m)) {
                $content = \substr($content, \strlen($m[0]));
                $chops[] = [$m[0], 0];
                continue;
            }
            if (0 === \strpos($chop, '`') && \preg_match('/^(`+)[^`]+\1(?!`)/', $chop, $m)) {
                $content = \substr($content, \strlen($m[0]));
                $chops[] = [$m[0], 0];
                continue;
            }
            $content = \substr($content, \strlen($chop));
            $chops[] = [$chop, 1];
        }
        if ("" !== $content) {
            $chops[] = [$content, 1];
        }
        return $chops;
    }
}

namespace x\markdown_filter\rows {
    function _code(string $v) {
        return _code_a($v) || _code_b($v);
    }
    function _code_a(string $v) {
        return \strspn($v, ' ') >= 4;
    }
    function _code_b(string $v) {
        if ($v && false !== \strpos('`~', $v[0]) && ($n = \strspn($v, $v[0])) >= 3) {
            $test = \strstr($v, "\n", true) ?: $v;
            // Character “`” cannot exist in the info string if code block fence uses “`” character(s)
            if ('`' === $v[0] && false !== \strpos(\substr($test, $n), '`')) {
                return false;
            }
            return true;
        }
        return false;
    }
    function _header(string $v) {
        $n = \strspn($v, '#');
        if (0 === $n || $n > 6) {
            return false;
        }
        if ($n === \strlen($v) || false !== \strpos(" \t", \substr($v, $n, 1))) {
            return true;
        }
        return false;
    }
    function _list(string $v) {
        return _list_a($v) || _list_b($v);
    }
    function _list_a(string $v) {
        if ($v && false !== \strpos('*+-', $v[0])) {
            if (_rule($v)) {
                return false;
            }
            if (1 === \strlen($v) || false !== \strpos(" \t", $v[1])) {
                return true;
            }
        }
        return false;
    }
    function _list_b(string $v) {
        $n = \strspn($v, '0123456789');
        // <https://spec.commonmark.org/0.31.2#example-266>
        if ($n > 9) {
            return false;
        }
        if (false !== \strpos(').', \substr($v, $n, 1))) {
            if ($n + 1 === \strlen($v) || false !== \strpos(" \t", \substr($v, $n + 1, 1))) {
                return true;
            }
        }
        return false;
    }
    function _note(string $v) {
        return 0 === \strpos($v, '[^') && \preg_match('/^\[\^[^]]+\]:(\s|$)/', $v);
    }
    function _quote(string $v) {
        return $v && '>' === $v[0];
    }
    function _raw(string $v) {
        if (!$v || '<' !== $v[0]) {
            return false;
        }
        $t = \substr(\strtok($v = \trim($v), " \n\r\t>"), 1);
        if (false !== \strpos($t, ':') || false !== \strpos($t, '@')) {
            return false;
        }
        if (false !== \strpos('!?', $t[0])) {
            return true;
        }
        $n = \trim($t, '/');
        if (false !== \strpos(',address,article,aside,base,basefont,blockquote,body,caption,center,col,colgroup,dd,details,dialog,dir,div,dl,dt,fieldset,figcaption,figure,footer,form,frame,frameset,h1,h2,h3,h4,h5,h6,head,header,hr,html,iframe,legend,li,link,main,menu,menuitem,nav,noframes,ol,optgroup,option,p,pre,param,script,search,section,source,style,summary,table,tbody,td,textarea,tfoot,th,thead,title,tr,track,ul,', ',' . ($n = \trim($t, '/')) . ',')) {
            return true;
        }
        $v = \strstr($v, "\n", true) ?: $v;
        if ('<' . $t . '>' === $v || '>' === \substr($v, -1) && \preg_match('/^<' . $n . '(\s(?>"[^"]*"|\'[^\']*\'|[^>])*)?>$/', $v)) {
            return true;
        }
        return false;
    }
    function _rule(string $v) {
        $test = \strtr($v = \trim($v), [
            "\t" => "",
            ' ' => ""
        ]);
        return $v && false !== \strpos('*-_', $v[0]) && \strlen($test) === ($n = \strspn($test, $v[0])) && $n >= 3;
    }
    function join(array $blocks, callable $fn) {
        foreach ($blocks as &$block) {
            [$row, $status] = $block;
            $dent = \strspn($row, ' ');
            $prefix = "";
            if ($dent < 4) {
                $prefix = \substr($row, 0, $dent);
                $row = \strtr(\substr($row, $dent), [
                    "\n" . $prefix => "\n"
                ]);
            }
            if ("" === $row || 0 === $status) {
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    if ("" === \trim($v)) {
                        $parts[$k] = "";
                        continue;
                    }
                    $parts[$k] = $prefix . $v;
                }
                $block = \call_user_func($fn, \implode("\n", $parts), $status);
                continue;
            }
            if (_note($row)) {
                $parts = \explode("\n", $row);
                $fix = \substr($parts[0], 0, $n = \strpos($parts[0], ']:') + 2);
                $parts[0] = \substr($parts[0], $n);
                $dent = 0;
                $dent_fix = "" === $parts[0] ? 0 : \strlen($fix);
                foreach ($parts as $k => $v) {
                    if (0 === $k) {
                        continue;
                    }
                    $d = \strspn($v, ' ');
                    if (0 === $dent || $d < $dent) {
                        $dent = $d;
                    }
                    if ($dent > 0) {
                        if ($dent_fix > 0) {
                            $parts[$k] = \substr($v, $dent_fix);
                            continue;
                        }
                        if ($dent > 4) {
                            $parts[$k] = \substr($v, $dent - 4);
                            continue;
                        }
                        $parts[$k] = \substr($v, $dent);
                    }
                }
                $row = join(split(\implode("\n", $parts)), $fn);
                $parts = \explode("\n", $row);
                $dent = 0 === $dent ? $n : $dent;
                foreach ($parts as $k => $v) {
                    if (0 === $k) {
                        $parts[$k] = $prefix . $fix . $v;
                        continue;
                    }
                    if ("" === \trim($v)) {
                        $parts[$k] = "";
                        continue;
                    }
                    if ($dent_fix > 0) {
                        $parts[$k] = $prefix . \str_repeat(' ', $dent_fix) . $v;
                        continue;
                    }
                    $parts[$k] = $prefix . \str_repeat(' ', $dent) . $v;
                }
                $block = \call_user_func($fn, \implode("\n", $parts), $status);
                continue;
            }
            if (_quote($row)) {
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    if ('>' === $v[0]) {
                        if (' ' === ($v[1] ?? "")) {
                            $parts[$k] = \substr($v, 2);
                            continue;
                        }
                        $parts[$k] = \substr($v, 1);
                    }
                }
                $row = join(split(\implode("\n", $parts)), $fn);
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    $parts[$k] = $prefix . ("" === $v ? '>' : '> ' . $v);
                }
                $block = \call_user_func($fn, \implode("\n", $parts), $status);
                continue;
            }
            if (_list_a($row)) {
                $parts = \explode("\n", $row);
                $n = 1 + \strspn(\substr($row, 1), ' ');
                $fix = \substr($row, 0, $n);
                foreach ($parts as $k => $v) {
                    if (0 === $k || \strspn($v, ' ') >= $n) {
                        $parts[$k] = \substr($v, $n);
                        continue;
                    }
                }
                $row = join(split(\implode("\n", $parts)), $fn);
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    if (0 === $k) {
                        $parts[$k] = $prefix . $fix . $v;
                        continue;
                    }
                    if ("" === \trim($v)) {
                        $parts[$k] = "";
                        continue;
                    }
                    $parts[$k] = $prefix . \str_repeat(' ', $n) . $v;
                }
                $block = \call_user_func($fn, \implode("\n", $parts), $status);
                continue;
            }
            if (_list_b($row)) {
                $parts = \explode("\n", $row);
                $n = \strspn($row, '0123456789');
                $n = $n + 1 + \strspn(\substr($row, $n + 1), ' ');
                $fix = \substr($row, 0, $n);
                foreach ($parts as $k => $v) {
                    if (0 === $k || \strspn($v, ' ') >= $n) {
                        $parts[$k] = \substr($v, $n);
                        continue;
                    }
                }
                $row = join(split(\implode("\n", $parts)), $fn);
                $parts = \explode("\n", $row);
                foreach ($parts as $k => $v) {
                    if (0 === $k) {
                        $parts[$k] = $prefix . $fix . $v;
                        continue;
                    }
                    if ("" === \trim($v)) {
                        $parts[$k] = "";
                        continue;
                    }
                    $parts[$k] = $prefix . \str_repeat(' ', $n) . $v;
                }
                $block = \call_user_func($fn, \implode("\n", $parts), $status);
                continue;
            }
            $parts = \explode("\n", $row);
            foreach ($parts as $k => $v) {
                if ("" === \trim($v)) {
                    $parts[$k] = "";
                    continue;
                }
                $parts[$k] = $prefix . $v;
            }
            $block = \call_user_func($fn, \implode("\n", $parts), $status);
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
                $dent_prev = \strspn($prev, ' ');
                if ($dent_prev < 4) {
                    $prev = \substr($prev, $dent_prev);
                }
                // Is in a code block?
                if (_code_a($prev)) {
                    if ("" === $row || $dent > 3) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    // End of the code block?
                    if ("\n" === \substr(\rtrim($prev, " \t"), -1)) {
                        $blocks[$block][0] = \substr(\rtrim($blocks[$block][0], " \t"), 0, -1);
                        $blocks[++$block] = [$prefix, 1];
                    }
                    $blocks[++$block] = [$prefix . $row, _code($row) || _raw($row) ? 0 : (_list($row) || _quote($row) ? 2 : 1)];
                    continue;
                }
                // Is in a code block?
                if (_code_b($prev)) {
                    // End of the code block?
                    if ($row === \str_repeat($prev[0], \strspn($prev, $prev[0]))) {
                        $blocks[$block++][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    // Continue the code block…
                    $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                    continue;
                }
                // Is in a raw block?
                if (_raw($prev)) {
                    // Is in a HTML comment block?
                    if (0 === \strpos($prev, '<!--')) {
                        // End of the HTML comment block?
                        if (false !== \strpos($row, '-->')) {
                            $blocks[$block][0] .= "\n" . $prefix . $row;
                            $block += 1;
                            continue;
                        }
                        $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                        continue;
                    }
                    // Is in a character data block?
                    if (0 === \strpos($prev, '<![CDATA[')) {
                        // End of the character data block?
                        if (false !== \strpos($row, ']]>')) {
                            $blocks[$block][0] .= "\n" . $prefix . $row;
                            $block += 1;
                            continue;
                        }
                        $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                        continue;
                    }
                    // Is in a processing instruction block?
                    if (0 === \strpos($prev, '<?')) {
                        // End of the character data block?
                        if (false !== \strpos($row, '?>')) {
                            $blocks[$block][0] .= "\n" . $prefix . $row;
                            $block += 1;
                            continue;
                        }
                        $blocks[$block][0] .= "\n" . ("" !== $row ? $prefix . $row : "");
                        continue;
                    }
                    if ("" !== $row) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, 1]; // End of the raw block
                    continue;
                }
                // Is a rule block?
                if (_rule($prev)) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    continue;
                }
                // Is in a list block?
                if (_list_a($prev)) {
                    if ("" === $row || $dent > $dent_prev + 1) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    // End of the list block?
                    if ("\n" === \substr(\rtrim($prev, " \t"), -1)) {
                        $blocks[$block][0] = \substr(\rtrim($blocks[$block][0], " \t"), 0, -1);
                        $blocks[++$block] = [$prefix, 1];
                    // Lazy list?
                    } else if (!_code($row) && !_header($row) && !_list($row) && !_note($row) && !_quote($row) && !_raw($row) && !_rule($row)) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, _list($row) || _note($row) || _quote($row) ? 2 : 1];
                    continue;
                }
                // Is in a list block?
                if (_list_b($prev)) {
                    $n = \strspn($prev, '0123456789');
                    if ("" === $row || $dent > $dent_prev + $n + 1) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    // End of the list block?
                    if ("\n" === \substr(\rtrim($prev, " \t"), -1)) {
                        $blocks[$block][0] = \substr(\rtrim($blocks[$block][0], " \t"), 0, -1);
                        $blocks[++$block] = [$prefix, 1];
                    // Lazy list?
                    } else if (!_code($row) && !_header($row) && !_list($row) && !_note($row) && !_quote($row) && !_raw($row) && !_rule($row)) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    $blocks[++$block] = [$prefix . $row, _list($row) || _note($row) || _quote($row) ? 2 : 1];
                    continue;
                }
                // Is in a note block?
                if (_note($prev)) {
                    if ("" === $row || $dent > $dent_prev) {
                        $blocks[$block][0] .= "\n" . $prefix . $row;
                        continue;
                    }
                    // End of the note block?
                    if ("\n" === \substr(\rtrim($prev, " \t"), -1)) {
                        $blocks[$block][0] = \substr(\rtrim($blocks[$block][0], " \t"), 0, -1);
                        $blocks[++$block] = [$prefix, 1];
                    }
                    $blocks[++$block] = [$prefix . $row, _list($row) || _note($row) || _quote($row) ? 2 : 1];
                    continue;
                }
                // Current block is a blank line…
                if ("" === $row) {
                    $blocks[++$block] = [$prefix, 1];
                    continue;
                }
                // Start of a tight code block
                if (_code_b($row)) {
                    $blocks[++$block] = [$prefix . $row, 0];
                    continue;
                }
                // Start of a tight note block
                if (_note($row)) {
                    $blocks[++$block] = [$prefix . $row, 2];
                    continue;
                }
                // Start of a tight quote block
                if (_quote($row) && !_quote($prev)) {
                    $blocks[++$block] = [$prefix . $row, 2];
                    continue;
                }
                // Start of a tight raw block
                if (_raw($row)) {
                    $blocks[++$block] = [$prefix . $row, 0];
                    continue;
                }
                // Start of a tight rule block
                if (_rule($row) && ('-' !== $row[0] || isset($row[1]) && false !== \strpos(" \t", $row[1]))) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    $block += 1; // Force a new block after it
                    continue;
                }
                // Start of a tight header block
                if (_header($row)) {
                    $blocks[++$block] = [$prefix . $row, 1];
                    $block += 1; // Force a new block after it
                    continue;
                }
                // Start of a tight list block
                if (_list($row)) {
                    if (_list_b($row) && 1 === \strspn($row, '0123456789')) {
                        // <https://spec.commonmark.org/0.31.2#example-304>
                        if ('1' !== $row[0]) {
                            $blocks[$block][0] .= "\n" . $prefix . $row;
                            continue;
                        }
                    }
                    $blocks[++$block] = [$prefix . $row, 2];
                    continue;
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
            if (_code($row)) {
                $blocks[++$block] = [$prefix . $row, 0];
                continue;
            }
            // Start of a list block
            if (_list($row)) {
                $blocks[++$block] = [$prefix . $row, 2];
                continue;
            }
            // Start of a note block
            if (_note($row)) {
                $blocks[++$block] = [$prefix . $row, 2];
                continue;
            }
            // Start of a quote block
            if (_quote($row)) {
                $blocks[++$block] = [$prefix . $row, 2];
                continue;
            }
            // Start of a raw block
            if (_raw($row)) {
                $blocks[++$block] = [$prefix . $row, 0];
                continue;
            }
            // Start of a rule block
            if (_rule($row)) {
                $blocks[++$block] = [$prefix . $row, 1];
                $block += 1; // Force a new block after it
                continue;
            }
            // Start of a header block
            if (_header($row)) {
                $blocks[++$block] = [$prefix . $row, 1];
                $block += 1; // Force a new block after it
                continue;
            }
            // Default is to start a new block…
            $blocks[++$block] = [$prefix . $row, 1];
        }
        return $blocks;
    }
}