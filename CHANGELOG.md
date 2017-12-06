# SoftMocks v1 Change Log

## master

There are next changes:

- using getenv instead of $_ENV global variable;
- error "PHP Fatal error:  Class 'Symfony\Polyfill\Php70\Php70' not found" was fixed;
- use path in project for cached files path.

## v1.3.2

There are next changes:

- Line numbering in rewritten code improved
- Only multiline /**/ comments are present in rewritten file

## v1.3.0

There are next changes:

- PHP 7.1 support (mostly nullable and void return type declarations);
- update nikic/php-parser to 3.0.6;
- fix bug with throwing from generators;
- added tests for constants redefine.

## v1.2.0

There are next changes:

- added Travis and Scrutinizer support;
- skipped running PHP7.0 tests on previously versions of PHP;
- changed default namespace to \Badoo. \QA namespace marked as deprecated and will be removed in 2.0.0;
- \QA\SoftMocksTraverser::$can_ref gone private, was mistakenly without scope.

## v1.1.2

There are next changes:

- vaimo/composer-patches version was fixed for prevent error 'The "badoo/soft-mocks/patches/phpunit5.x/phpunit_phpunit.patch" file could not be downloaded: failed to open stream: No such file or directory';
- load parser file was added for prevent error "Fatal error: Uncaught Error: Class 'PhpParser\NodeTraverser' not found in vendor/badoo/soft-mocks/src/QA/SoftMocks.php:1154".

## v1.1.1

There are next changes:

- nikic/php-parser was updated to 2.0.0beta1;
- using nikic/php-parser version in path to rewritten file was added;
- info how reapply patches was added.

## v1.1.0

There are next changes:

- patches for phpunit in composer.json was added;
- exact version of nikic/php-parser in composer.json was provided;
- parameter $strict for method `\QA\SoftMocks::redefineMethod()` was removed, now only strict mode available;
- redefine for built-in mocks was allowed (is activated by `\QA\SoftMocks::setRewriteInternal(true)`) [https://github.com/badoo/soft-mocks/pull/15](https://github.com/badoo/soft-mocks/pull/15), thanks [Mougrim](https://github.com/mougrim);
- null for redefined constants was allowed [https://github.com/badoo/soft-mocks/pull/11](https://github.com/badoo/soft-mocks/pull/11), thanks [Alexey Manukhin](https://github.com/axxapy);
- error "Fatal error: Couldn't find constant QA\SoftMocks::CLASS in /src/QA/SoftMocks.php on line 388" was fixed for old versions hhvm [https://github.com/badoo/soft-mocks/pull/16](https://github.com/badoo/soft-mocks/pull/16), thanks [Mougrim](https://github.com/mougrim);
- warning "PHP Warning:  array_key_exists() expects exactly 2 parameters" was fixed [https://github.com/badoo/soft-mocks/pull/14](https://github.com/badoo/soft-mocks/pull/14), thanks [Mougrim](https://github.com/mougrim);
- handle phpunit wrapped exceptions (PHPUnit_Framework_ExceptionWrapper, \PHPUnit\Framework\ExceptionWrapper);
- unit tests was added.
