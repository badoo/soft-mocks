<?php
namespace Badoo;

$composer_install = '';
foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        $composer_install = $file;

        break;
    }
}
if (!$composer_install) {
    fwrite(
        STDERR,
        'You need to set up the project dependencies using Composer:' . PHP_EOL . PHP_EOL
            . '    composer install' . PHP_EOL . PHP_EOL
            . 'You can learn all about Composer on https://getcomposer.org/.' . PHP_EOL
    );

    die(1);
}

$php_parser_dir = dirname($composer_install) . '/nikic/php-parser/lib/PhpParser/';
// workaround for right load files, because now PhpParser uses composer autoload, which should be init later
require_once "{$php_parser_dir}Parser.php";
require_once "{$php_parser_dir}ParserAbstract.php";
require_once "{$php_parser_dir}PrettyPrinterAbstract.php";
require_once "{$php_parser_dir}Builder.php";
require_once "{$php_parser_dir}Builder/Declaration.php";
require_once "{$php_parser_dir}NodeVisitor.php";
require_once "{$php_parser_dir}NodeVisitorAbstract.php";
require_once "{$php_parser_dir}NodeTraverserInterface.php";
require_once "{$php_parser_dir}Node.php";
require_once "{$php_parser_dir}NodeAbstract.php";
require_once "{$php_parser_dir}Lexer/TokenEmulator/TokenEmulator.php";
require_once "{$php_parser_dir}Lexer/TokenEmulator/KeywordEmulator.php";
require_once "{$php_parser_dir}Node/Expr.php";
require_once "{$php_parser_dir}Node/FunctionLike.php";
// for prevent autoload problems
$files = [];
exec('find ' . escapeshellarg($php_parser_dir) . " -type f -name '*.php'", $files);
sort($files);
foreach ($files as $file) {
    require_once $file;
}
unset($php_parser_dir, $files, $file);

/* Soft Mocks init */
require_once(dirname(__DIR__) . "/src/Badoo/SoftMocks.php");
SoftMocks::setVendorPath(dirname($composer_install));
SoftMocks::setIgnoreSubPaths(
    array(
        '/vendor/phpunit/' => '/vendor/phpunit/',
        '/vendor/sebastian/diff/' => '/vendor/sebastian/diff/',
        '/vendor/nikic/php-parser/' => '/vendor/nikic/php-parser/',
        '/vendor/symfony/polyfill' => '/vendor/symfony/polyfill',
    )
);
SoftMocks::init();
return SoftMocks::rewrite($composer_install);
