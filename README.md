SoftMocks
=
The idea behind "Soft Mocks" - as opposed to "hardcore" mocks that work on the level of the PHP interpreter (runkit and uopz) - is to rewrite class code on the spot so that it can be inserted in any place. It works by rewriting code on the fly during file inclusion instead of using extensions like runkit or uopz.

Usage
=
The thing that sets SoftMocks apart (and also limits their usage) is that they need to be initiated (along with all of their dependencies) at the earliest phase of the app launch. It's necessary to do it this way because you can't redefine the classes and functions that are already loaded into the memory in PHP. For an example of PHPUnit bootstrap presets, see _src/bootstrap.php_ and _example-phpunit/bootstrap.php_.

SoftMocks don't rewrite the following system parts:
* it's own code
* PHPUnit code (see setPhpunitPath() for details)
* PHP-Parser code (see setPhpParserPath() for details)
* already rewritten code

In order to add external dependencies (for example, vendor/autoload.php) to a bootstrap file, you need to use a wrapper:
```
require_once (\QA\SoftMocks::rewrite('vendor/autoload.php'));
require_once (\QA\SoftMocks::rewrite('path/to/external/lib.php'));
```

After you've added the file via SoftMocks::rewrite(), all nested include calls will already be "wrapped" by the system itself.

You can see a more detailed example by executing the following command:
```
[~/Work/soft-mocks]-> php example/run_me.php
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

API (short description)
=
Initialize Soft Mocks (set phpunit injections, define internal mocks, get list of internal functions, etc): 

```
\QA\SoftMocks::init();
```

Cache files are created in /tmp/mocks by default. If you want to choose a different path, you can redefine it as follows:

```
\QA\SoftMocks::setMocksCachePath($cache_path);
```

Redefine constant
==

You can assign a new value to $constantName or create one if it wasn't already declared. Since it isn't created using the define() call, the operation can be canceled.

Both "regular constants" and class constants like "className::CONST_NAME" are supported.

```
\QA\SoftMocks::redefineConstant($constantName, $value)
```

Redefine functions
==

Soft Mocks let you redefine both user-defined and built-in functions except for those that depend on the current context (see \QA\SoftMocksTraverser::$ignore_functions property if you want to see the full list), or for those that have built-in mocks (debug_backtrace, call_user_func* and a few others).

Definition:
```
\QA\SoftMocks::redefineFunction($func, $functionArgs, $fakeCode)
```

Usage example (redefine strlen function and call original for the trimmed string):
```
\QA\SoftMocks::redefineFunction(
    'strlen',
    '$a',
    'return \\QA\\SoftMocks::callOriginal("strlen", [trim($a)]));'
);

var_dump(strlen("  a  ")); // int(1)
```

Redefine methods
==

At the moment, only user-defined method redefinition is supported. This functionality is not supported for built-in classes.

Definition:
```
\QA\SoftMocks::redefineMethod($class, $method, $functionArgs, $fakeCode, $strict = true)
```

Arguments are the same as for redefineFunction, but $class is argument is introducted, and it's possible to work in non-strict mode ($strict = false). If non-strict mode is selected, then runkit behavior is emulated when class methods are redefined so that in addition to the $class method itself, its ancestor methods are also redefined.

As an argument, $class accepts a class name or a trait name.

Redefine constructors
==

This method let you redefine constructor for both user-defined and built-in classes.

Definition:
 ```
 \QA\SoftMocks::redefineNew($class, $constructorFunc)
 ```

Usage example (redefine \DateTime constructor):
```
\QA\SoftMocks::redefineNew('DateTime', function() {
    return new \DateTime('2015-02-03 00:00:00');
});

var_dump((new \DateTime)->format('c')); // string(25) "2015-02-03T00:00:00+03:00"
```

Redefining functions that are generators
==
This method that lets you replace a generator function call with another \Generator. Generators differ from regular functions in that you can't return a value using "return"; you have to use "yield".

```
\QA\SoftMocks::redefineGenerator($class, $method, \Generator $replacement)
```

Restore values
==

The following functions undo mocks that were made using one of the redefine methods described above.
```
\QA\SoftMocks::restoreAll()

// You can also undo only chosen mocks:
\QA\SoftMocks::restoreConstant($constantName)
\QA\SoftMocks::restoreAllConstants()
\QA\SoftMocks::restoreFunction($func)
\QA\SoftMocks::restoreMethod($class, $method)
\QA\SoftMocks::restoreGenerator($class, $method)
```

FAQ
=
**Q**: How can I prevent a specific function/class/constant from being redefined?

**A**: Use the \QA\SoftMocks::ignore(Class|Function|Constant) method.

**Q**: I can't override certain function calls: call_user_func(_array)?, defined, etc.

**A**: There are a bunch of functions that have their own built-in mocks which can't be intercepted. Here is an incomplete list of them:
* call_user_func_array
* call_user_func
* is_callable
* function_exists
* constant
* defined
* debug_backtrace

**Q**: How do I use Soft Mocks with PHPUnit?

**A**: You need to merge our pull request https://github.com/sebastianbergmann/phpunit/pull/2116 into your phpunit version or just take this branch.
Soft Mocks will work even without any phpunit patches but you will see "unreadable" stack traces for failed tests and you will not be able to redefine classes and methods that are defined in tests themselves.

**Q**: Does Soft Mocks work with PHP7?

**A**: Yes. The whole idea of Soft Mocks is that it will continue to work for all further PHP versions without requiring a full system rewrite as it is for runkit and uopz.

**Q**: Does Soft Mocks work with HHVM?

**A**: It seems that Soft Mocks indeed works when using HHVM at the moment of writing this Q&A (HipHop VM 3.12.1 (rel)). We do not use HHVM internally so there can be some corner cases that are not covered. We appreciate any issues/pull requests regarding HHVM support.
