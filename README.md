PHP Markdown Filter
===================

Motivation
----------

_TODO_

Usage
-----

This converter can be installed using [Composer](https://packagist.org/packages/taufik-nurrohman/markdown.filter), but
it doesn’t need any other dependencies and just uses Composer’s ability to automatically include files. Those of you who
don’t use Composer should be able to include the `index.php` file directly into your application without any problems.

### Using Composer

From the command line interface, navigate to your project folder then run this command:

~~~ sh
composer require taufik-nurrohman/markdown.filter
~~~

Require the generated auto-loader file in your application:

~~~ php
<?php

use function x\markdown__filter as filter;

require 'vendor/autoload.php';

echo filter(file_get_contents('.\path\to\file.md'), static function ($part, $status) {
    if (0 === $status) {
        return $part;
    }
    // Safely convert `~~asdf~~` syntax to `<del>asdf</del>`
    return preg_replace('/~~(.*?)~~/', '<del>$1</del>', $part);
});
~~~

### Using File

Require the `index.php` file in your application:

~~~ php
<?php

use function x\markdown__filter as filter;

require 'index.php';

echo filter(file_get_contents('.\path\to\file.md'), static function ($part, $status) {
    if (0 === $status) {
        return $part;
    }
    // Safely convert `~~asdf~~` syntax to `<del>asdf</del>`
    return preg_replace('/~~(.*?)~~/', '<del>$1</del>', $part);
});
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
markdown__filter(?string $value, callable $fn): ?string;
~~~

Tests
-----

Clone this repository into the root of your web server that supports PHP and then you can open the `test.php` file with
your browser.

License
-------

This library is licensed under the [MIT License](LICENSE). Please consider
[donating 💰](https://github.com/sponsors/taufik-nurrohman) if you benefit financially from this library.