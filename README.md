PHP Markdown Filter
===================

![index.php](https://img.shields.io/github/size/taufik-nurrohman/markdown-filter/index.php?branch=main&color=%234f5d95&label=index.php&labelColor=%231f2328&style=flat-square)

Motivation
----------

As [Markdown](https://github.com/taufik-nurrohman/markdown) has grown in popularity, many people have expected to see
new formatting syntaxes added. However, people who develop Markdown parsers will generally stick to the philosophy that
[John Gruber](https://daringfireball.net/projects/markdown) has explained, that the design goal of Markdown’s formatting
syntax is to make it as readable as possible. The idea is that a Markdown-formatted document should be publishable
as-is, as plain text, without looking like it’s been marked up with tags or formatting instructions.

Typically, they will simply tell people to use raw HTML syntax if their wishes are too complex and/or not in line with
Markdown’s philosophy. Markdown parser generally does not prohibit people from doing so.

People who just know how to search and replace text with PHP often give naive suggestions, such as telling people to use
regular expressions to replace text directly in the Markdown document, which often ends up with people replacing text in
the wrong places, such as replacing text inside a code block syntax that should be left as it is.

This filter can be used to separate parts of a Markdown document into blocks and spans, so that you can replace text
only in certain blocks and spans that you consider safe.

Usage
-----

This converter can be installed using [Composer](https://packagist.org/packages/taufik-nurrohman/markdown-filter), but
it doesn’t need any other dependencies and just uses Composer’s ability to automatically include files. Those of you who
don’t use Composer should be able to include the `index.php` file directly into your application without any problems.

### Using Composer

From the command line interface, navigate to your project folder then run this command:

~~~ sh
composer require taufik-nurrohman/markdown-filter
~~~

Require the generated auto-loader file in your application:

~~~ php
<?php

use function x\markdown_filter\row as filter_row;
use function x\markdown_filter\rows as filter_rows;

require 'vendor/autoload.php';

$content = file_get_contents('.\path\to\file.md');

$content = filter_rows($content, function ($block, $status) {
    if (0 === $status || 2 === $status) {
        return $block;
    }
    return filter_row($block, function ($chop, $status) {
        if (0 === $status) {
            return $chop;
        }
        // Safely replace `:)` with `😊`
        return strtr($chop, [':)' => '&#128522;']);
    });
});

// You can now convert the Markdown document to HTML using your preferred Markdown converter
echo (new ParsedownExtra)->text($content);
~~~

### Using File

Require the `index.php` file in your application:

~~~ php
<?php

use function x\markdown_filter\row as filter_row;
use function x\markdown_filter\rows as filter_rows;

require 'index.php';

$content = file_get_contents('.\path\to\file.md');

$content = filter_rows($content, function ($block, $status) {
    if (0 === $status || 2 === $status) {
        return $block;
    }
    return filter_row($block, function ($chop, $status) {
        if (0 === $status) {
            return $chop;
        }
        // Safely replace `:)` with `😊`
        return strtr($chop, [':)' => '&#128522;']);
    });
});

// You can now convert the Markdown document to HTML using your preferred Markdown converter
echo (new ParsedownExtra)->text($content);
~~~

The `$status` variable shows whether or not a part of the document is safe for any kind of text substitutions. For now,
it can have the value set to `0`, `1`, or `2`. A value of `0` means that the part of the document is generally not safe
for any kind of text substitutions. It is typically contained in the code and raw HTML chunks. A value of `2` means that
a block can contain other blocks, so it would be better to skip it as well, because indentation usually has a different
meaning in this situation, until then this filter reaches into the inner content of that block.

The main goal of this project is to introduce [the “embed” syntax for Markdown][1], which I believe has never been
discussed before (for this kind of syntax). That’s why I implemented this filter on the test page as a sort of utility
to safely replace the syntax:

![Example][2]

 [1]: https://github.com/taufik-nurrohman/markdown
 [2]: https://github.com/taufik-nurrohman/markdown-filter/assets/1669261/7fe0f9be-9d25-4e1e-b947-8a51a0275a3a

You can also use this filter to strip HTML tags other than those that are written in Markdown’s code syntax. People
usually write HTML syntax there to share a piece of code in your comments section:

~~~ php
<?php

use function x\markdown_filter\row as filter_row;
use function x\markdown_filter\rows as filter_rows;

if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['comment']['content'])) {
    $_POST['comment']['content'] = filter_rows($_POST['comment']['content'], function ($block, $status) {
        if (2 === $status) {
            return $block;
        }
        if (0 === $status) {
            $dent = strspn($block, ' ');
            if ($dent >= 4) {
                return $block; // Code block (indent-style)
            }
            $test = substr($block, $dent);
            if (0 === strpos($test, '```') || 0 === strpos($test, '~~~')) {
                return $block; // Code block (fence-style)
            }
            return strip_tags($block);
        }
        return filter_row($block, function ($chop, $status) {
            if (0 === $status) {
                $test = strspn($chop, '`');
                if ($test > 0 && str_repeat('`', $test) === substr($chop, -$test)) {
                    return $chop; // Code span
                }
            }
            return strip_tags($chop);
        });
    });
}
~~~

Tests
-----

Clone this repository into the root of your web server that supports PHP and then you can open the `test.php` file with
your browser.

License
-------

This library is licensed under the [MIT License](LICENSE). Please consider
[donating 💰](https://github.com/sponsors/taufik-nurrohman) if you benefit financially from this library.