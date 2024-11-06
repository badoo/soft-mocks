# SoftMocks
The idea behind "Soft Mocks" - as opposed to "hardcore" mocks that work on the level of the PHP interpreter (runkit and uopz) - is to rewrite class code on the spot so that it can be inserted in any place. It works by rewriting code on the fly during file inclusion instead of using extensions like runkit or uopz.

[![Build Status](https://secure.travis-ci.org/badoo/soft-mocks.png?branch=master)](https://travis-ci.org/badoo/soft-mocks)
[![GitHub release](https://img.shields.io/github/release/badoo/soft-mocks.svg)](https://github.com/badoo/soft-mocks/releases/latest)
[![Total Downloads](https://img.shields.io/packagist/dt/badoo/soft-mocks.svg)](https://packagist.org/packages/badoo/soft-mocks)
[![Daily Downloads](https://img.shields.io/packagist/dd/badoo/soft-mocks.svg)](https://packagist.org/packages/badoo/soft-mocks)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%205.5-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/badoo/soft-mocks.svg)](https://packagist.org/packages/badoo/soft-mocks)

## Installation

You can install SoftMocks via [Composer](https://getcomposer.org/):

```bash
composer require --dev badoo/soft-mocks
```

## Usage

The thing that sets SoftMocks apart (and also limits their usage) is that they need to be initiated at the earliest phase of the app launch. It's necessary to do it this way because you can't redefine the classes and functions that are already loaded into the memory in PHP. For an example bootstrap presets, see _[src/bootstrap.php](src/bootstrap.php)_. For PHPUnit you should use patches form _[composer.json](composer.json)_, because you should require composer autoload through SoftMocks.

SoftMocks don't rewrite the following system parts:
* it's own code;
* PHPUnit code (see `\Badoo\SoftMocks::addIgnorePath()` for details);
* PHP-Parser code (see `\Badoo\SoftMocks::addIgnorePath()` for details);
* already rewritten code;
* code which was loaded before SoftMocks initialization.

In order to add external dependencies (for example, vendor/autoload.php) in file, which which was loaded before SoftMocks initialization, you need to use a wrapper:
```
require_once (\Badoo\SoftMocks::rewrite('vendor/autoload.php'));
require_once (\Badoo\SoftMocks::rewrite('path/to/external/lib.php'));
```

After you've added the file via `SoftMocks::rewrite()`, all nested include calls will already be "wrapped" by the system itself.

You can see a more detailed example by executing the following command:
```
$ php example/run_me.php
Result before applying SoftMocks = array (
  'TEST_CONSTANT_WITH_VALUE_42' => 42,
  'someFunc(2)' => 84,
  'Example::doSmthStatic()' => 42,
  'Example->doSmthDynamic()' => 84,
  'Example::STATIC_DO_SMTH_RESULT' => 42,
)
Result after applying SoftMocks = array (
  'TEST_CONSTANT_WITH_VALUE_42' => 43,
  'someFunc(2)' => 57,
  'Example::doSmthStatic()' => 'Example::doSmthStatic() redefined',
  'Example->doSmthDynamic()' => 'Example->doSmthDynamic() redefined',
  'Example::STATIC_DO_SMTH_RESULT' => 'Example::STATIC_DO_SMTH_RESULT value changed',
)
Result after reverting SoftMocks = array (
  'TEST_CONSTANT_WITH_VALUE_42' => 42,
  'someFunc(2)' => 84,
  'Example::doSmthStatic()' => 42,
  'Example->doSmthDynamic()' => 84,
  'Example::STATIC_DO_SMTH_RESULT' => 42,
)
```

## API (short description)

Initialize SoftMocks (set phpunit injections, define internal mocks, get list of internal functions, etc):

```
\Badoo\SoftMocks::init();
```

Cache files are created in /tmp/mocks by default. If you want to choose a different path, you can redefine it as follows:

```
\Badoo\SoftMocks::setMocksCachePath($cache_path);
```

This method should be called before rewrite first file. Also you can redefine cache path using environment variable `SOFT_MOCKS_CACHE_PATH`.

### Redefine constant

You can assign a new value to $constantName or create one if it wasn't already declared. Since it isn't created using the define() call, the operation can be canceled.

Both "regular constants" and class constants like "className::CONST_NAME" are supported.

```
\Badoo\SoftMocks::redefineConstant($constantName, $value)
```

There can be next cases with class constants redefining:

- You can redefine base class constant:
  ```php
  class A {const NAME = 'A';}
  class B {}
  echo A::NAME . "\n"; // A
  echo B::NAME . "\n"; // A
  \Badoo\SoftMocks::redefineConstant(A::class . '::NAME', 'B');
  echo A::NAME . "\n"; // B
  echo B::NAME . "\n"; // B
  ```
- You can add middle class constant:
  ```php
  class A {const NAME = 'A';}
  class B {}
  class C {}
  echo A::NAME . "\n"; // A
  echo B::NAME . "\n"; // A
  echo C::NAME . "\n"; // A
  \Badoo\SoftMocks::redefineConstant(B::class . '::NAME', 'B');
  echo A::NAME . "\n"; // A
  echo B::NAME . "\n"; // B
  echo C::NAME . "\n"; // B
  ```
- You can add constant to base class:
  ```php
  class A {const NAME = 'A';}
  class B {}
  echo A::NAME . "\n"; // Undefined class constant 'NAME'
  echo B::NAME . "\n"; // Undefined class constant 'NAME'
  \Badoo\SoftMocks::redefineConstant(A::class . '::NAME', 'A');
  echo A::NAME . "\n"; // A
  echo B::NAME . "\n"; // A
  ```
- You can remove middle class constant:
  ```php
  class A {const NAME = 'A';}
  class B {const NAME = 'B';}
  class C {}
  echo A::NAME . "\n"; // A
  echo B::NAME . "\n"; // B
  echo C::NAME . "\n"; // B
  \Badoo\SoftMocks::removeConstant(B::class . '::NAME');
  echo A::NAME . "\n"; // A
  echo B::NAME . "\n"; // A
  echo C::NAME . "\n"; // A
  ```
- Other more simple cases (just add or redefine constant and etc.).

### Redefine functions

SoftMocks let you redefine both user-defined and built-in functions except for those that depend on the current context (see \Badoo\SoftMocksTraverser::$ignore_functions property if you want to see the full list), or for those that have built-in mocks (debug_backtrace, call_user_func* and a few others, but built-in mocks you can enable redefine by call `\Badoo\SoftMocks::setRewriteInternal(true)`).

Definition:
```
\Badoo\SoftMocks::redefineFunction($func, $functionArgs, $fakeCode)
```

Usage example (redefine strlen function and call original for the trimmed string):
```
\Badoo\SoftMocks::redefineFunction(
    'strlen',
    '$a',
    'return \\Badoo\\SoftMocks::callOriginal("strlen", [trim($a)]));'
);

var_dump(strlen("  a  ")); // int(1)
```

### Redefine methods

At the moment, only user-defined method redefinition is supported. This functionality is not supported for built-in classes.

Definition:
```
\Badoo\SoftMocks::redefineMethod($class, $method, $functionArgs, $fakeCode)
```

Arguments are the same as for redefineFunction, but argument $class is introduced.

As an argument $class accepts a class name or a trait name.

### Redefining functions that are generators

This method that lets you replace a generator function call with another \Generator. Generators differ from regular functions in that you can't return a value using "return"; you have to use "yield".

```
\Badoo\SoftMocks::redefineGenerator($class, $method, \Generator $replacement)
```

### Restore values

The following functions undo mocks that were made using one of the redefine methods described above.
```
\Badoo\SoftMocks::restoreAll()

// You can also undo only chosen mocks:
\Badoo\SoftMocks::restoreConstant($constantName)
\Badoo\SoftMocks::restoreAllConstants()
\Badoo\SoftMocks::restoreFunction($func)
\Badoo\SoftMocks::restoreMethod($class, $method)
\Badoo\SoftMocks::restoreGenerator($class, $method)
\Badoo\SoftMocks::restoreNew()
\Badoo\SoftMocks::restoreAllNew()
\Badoo\SoftMocks::restoreExit()
```

## Using with PHPUnit

### Maximum supported version

Currently, the maximum supported version is **PHPUnit 8.5.38**

### Installation

If you want to use SoftMocks with PHPUnit 8.x then there are next particularities:
- If phpunit is installed by composer then you should apply patch to `phpunit` _[patches/phpunit7.x/phpunit_phpunit.patch](patches/phpunit7.x/phpunit_phpunit.patch)_,so that classes loaded by composer would be rewritten by SoftMocks;
- if phpunit is installed manually then you should require _[src/bootstrap.php](src/bootstrap.php)_, so that classes loaded by composer would be rewritten by SoftMocks;
- so that trace would be readable you should apply patch for `phpunit` _[patches/phpunit8.x/phpunit_add_ability_to_set_custom_filename_rewrite_callbacks.patch](patches/phpunit8.x/phpunit_add_ability_to_set_custom_filename_rewrite_callbacks.patch)_;
- so that coverage would be right the you should apply patch to `php-code-coverage` _[patches/phpunit8.x/php-code-coverage_add_ability_to_set_custom_filename_rewrite_callbacks.patch](patches/phpunit8.x/php-code-coverage_add_ability_to_set_custom_filename_rewrite_callbacks.patch)_.

Use `phpunit7.x` directory instead of `phpunit8.x` for `phpunit7.x`.
Use `phpunit6.x` directory instead of `phpunit8.x` for `phpunit6.x`.
Use `phpunit5.x` directory instead of `phpunit8.x` for `phpunit5.x`.
Use `phpunit4.x` directory instead of `phpunit8.x` for `phpunit4.x`.

If you want that patches are applied automatically, you should write next in в composer.json:
```json
{
  "require-dev": {
    "vaimo/composer-patches": "3.23.1",
    "phpunit/phpunit": "^8.4.3" // or "^7.5.17" or "^6.5.5" or "^5.7.20" or "^4.8.35"
  }
}
```

To force reapply patches use next command:
```bash
composer patch --redo
```

For more information about patching see [vaimo/composer-patches documentation](https://github.com/vaimo/composer-patches/blob/3.22.4/README.md).

## Using with xdebug

There is two possibilities to use soft-mocks with xdebug - debug rewritten files and debug original file using xdebug-proxy.

### Debug rewritten files

If you use soft-mocks locally then you can just debug it by calling to `xdebug_break()`. Also you can add break point to the rewritten file, but you should know rewritten file path. For getting the rewritten file path you can call `\Badoo\SoftMocks::rewrite($file)`, but be attentive - if you change the file then new one will be created and it'll have different path.

If you use soft-mocks on the server, then you can mount /tmp/mocks using sshfs or something like this.

### Debug original files using xdebug-proxy

As you see debug rewritten files is uncomfortable. You can also debug original files using [xdebug-proxy](https://github.com/mougrim/php-xdebug-proxy).

```php
composer.phar require mougrim/php-xdebug-proxy --dev
cp -r vendor/mougrim/php-xdebug-proxy/config xdebug-proxy-config
```

After that change `xdebug-proxy-config/factory.php` to the following:
```php
<?php
use Mougrim\XdebugProxy\Factory\SoftMocksFactory;

return new SoftMocksFactory();
```

If you use soft-mocks locally, then you can just run proxy:
```bash
vendor/bin/xdebug-proxy --configs=xdebug-proxy-config
```

After that register your IDE on `127.0.0.1:9001` and run script, which uses soft-mocks (for example phpunit):
```bush
php -d'zend_extension=xdebug.so' -d'xdebug.remote_autostart=On' -d'xdebug.idekey=idekey' -d'xdebug.remote_connect_back=On' -d'xdebug.remote_enable=On' -d'xdebug.remote_host=127.0.0.1' -d'xdebug.remote_port=9002' /local/php72/bin/phpunit
```

If you use soft-mocks on the server, then you should run xdebug-proxy on the server too, and modify ip in `xdebug-proxy-config/config.php` for `ideRegistrationServer` from `127.0.0.1` to `0.0.0.0`.

In general xdebug-proxy works as the following:
1. The first step is to register your IDE in the xdebug-proxy (eg: Main menu -> Tools -> DBGp proxy -> Register IDE in PHPStorm). Use `127.0.0.1:9001` or your server IP:PORT which xdebug-proxy is listening on for the IDE registration. You can configure that PORT in the xdebug-proxy config. On that step IDE sends its IP:PORT to the proxy which IDE is listening on.
2. When you run php-script with command-line options provided above xdebug connects to `127.0.0.1:9002`. This ip and port is where xdebug-proxy is listening on for the connection from xdebug. Xdebug-proxy matches IDEKEY with the registered IDE. If any registered IDE is matched then xdebug-proxy will connect to that particular IDE using provided IDE client IP:PORT at the registration step.

For more information read [xdebug documentation](https://xdebug.org/docs/remote) and [xdebug-proxy documentation](https://github.com/mougrim/php-xdebug-proxy).

## SoftMocks development

If you need to make changes to SoftMocks, you need to clone repository and install dependencies:

```
composer install
```

Then you can change SoftMocks and run tests to be sure that all works:

```
./vendor/bin/phpunit 
```

Remember to update CHANGELOG.md.

If you are bumping the PHPUnit version, update readme with maximum supported version.

## FAQ

**Q**: How can I prevent a specific function/class/constant from being redefined?

**A**: Use the \Badoo\SoftMocks::ignore(Class|Function|Constant) method.

**Q**: I can't override certain function calls: call_user_func(_array)?, defined, etc.

**A**: There are a bunch of functions that have their own built-in mocks which by default can't be intercepted.
Here is an incomplete list of them:
* call_user_func_array
* call_user_func
* is_callable
* function_exists
* constant
* defined
* debug_backtrace

So you can enable intercepting for them by call `\Badoo\SoftMocks::setRewriteInternal(true)` after require bootstrap, but be attentive.
For example, if strlen and call_user_func(_array) is redefined, then you can get different result for strlen:
```php
\Badoo\SoftMocks::redefineFunction('call_user_func_array', '', 'return 20;');
\Badoo\SoftMocks::redefineFunction('strlen', '', 'return 5;');
...
strlen('test'); // will return 5
call_user_func_array('strlen', ['test']); // will return 20
call_user_func('strlen', 'test'); // will return 5
```

**Q**: Does SoftMocks work with PHP 8.2?

**A**: Yes. The whole idea of SoftMocks is that it will continue to work for all further PHP versions without requiring a full system rewrite as it is for runkit and uopz.

**Q**: Why do I get parse errors or fatal errors like "PhpParser::pSmth is undefined"?

**A**: SoftMocks uses custom pretty-printer for PHP Parser that does not seem to be compatible with all PHP Parser versions. Please use our vendored version until we found a way to get around that.
