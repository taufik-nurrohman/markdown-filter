PHP Markdown Filter
===================

Motivation
----------

As [Markdown](https://github.com/taufik-nurrohman/markdown) has grown in popularity, many people have expected to see
new formatting syntaxes added. However, people who develop Markdown parsers will generally stick to the philosophy that
[John Gruber](https://daringfireball.net/projects/markdown) has explained, that the design goal of Markdown’s formatting
syntax is to make it as readable as possible. The idea is that a Markdown-formatted document should be publishable
as-is, as plain text, without looking like it’s been marked up with tags or formatting instructions.

Typically, they will simply tell people to use raw HTML syntax if their wishes are too complex and/or not in line with
Markdown’s philosophy. Markdown parser generally does not prohibit people from doing so.

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

use function x\markdown_filter;

require 'vendor/autoload.php';

$value = file_get_contents('.\path\to\file.md');

$value = markdown_filter\rows($value, function ($row, $status) {
    if (0 === $status || 2 === $status) {
        return $row;
    }
    return markdown_filter\row($row, function ($chunk, $status) {
        if (0 === $status) {
            return $chunk;
        }
        // Safely convert `~~asdf~~` syntax to `<del>asdf</del>`
        return preg_replace('/~~([^\n]+)~~/', '<del>$1</del>', $chunk);
    });
});

// You can now convert the Markdown string to HTML string using your preferred Markdown converter
echo (new ParsedownExtra)->text($value);
~~~

### Using File

Require the `index.php` file in your application:

~~~ php
<?php

use function x\markdown_filter;

require 'index.php';

$value = file_get_contents('.\path\to\file.md');

$value = markdown_filter\rows($value, function ($row, $status) {
    if (0 === $status || 2 === $status) {
        return $row;
    }
    return markdown_filter\row($row, function ($chunk, $status) {
        if (0 === $status) {
            return $chunk;
        }
        // Safely convert `~~asdf~~` syntax to `<del>asdf</del>`
        return preg_replace('/~~([^\n]+)~~/', '<del>$1</del>', $chunk);
    });
});

// You can now convert the Markdown string to HTML string using your preferred Markdown converter
echo (new ParsedownExtra)->text($value);
~~~

Options
-------

~~~ php
/**
 * Convert Markdown string to HTML string.
 *
 * @param null|string $value Your Markdown string.
 * @param callable $fn Your filter function, will be applied to each Markdown part.
 * @return null|string
 */
markdown_filter(?string $value, callable $fn): ?string;
~~~

Tests
-----

Clone this repository into the root of your web server that supports PHP and then you can open the `test.php` file with
your browser.

License
-------

This library is licensed under the [MIT License](LICENSE). Please consider
[donating 💰](https://github.com/sponsors/taufik-nurrohman) if you benefit financially from this library.