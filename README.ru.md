SoftMocks
=
Основная идея «мягких моков» по сравнению с «жесткими», которые работают на уровне интерпретатора PHP (runkit и uopz) — переписывать «на лету» код классов так, чтобы можно было вставлять моки в любое место.
Идея состоит в следующем: вместо использования расширений типа runkit или uopz, переписывать код на лету во время include.

Использование
=
Ключевой особенностью (и в то же время ограничением) SoftMocks является то, что они (вместе со всеми своими зависимостями) должны быть инициализированы на самом раннем этапе запуска приложения. Это необходимо из-за того, что в PHP нет возможности переопределять уже загруженные в память классы и функции. Пример заготовки bootstrap-файла для PHPUnit можно увидеть в _src/bootstrap.php_.

SoftMocks не переписывают следующие части системы:
* собственный код
* код PHPUnit
* код PHP-Parser
* уже переписанный код

Для того, чтобы в bootstrap-файле подключить какие-либо внешние зависимости (например, vendor/autoload.php), то их нужно подключить через обертку:
```
require_once (\QA\SoftMocks::rewrite('vendor/autoload.php'));
require_once (\QA\SoftMocks::rewrite('path/to/external/lib.php'));
```

После того, как вы подключите файл через SoftMocks::rewrite(), все вложенные вызовы include уже будут «обернуты» самой системой.

Более подробный пример можно увидеть выполнив следующую команду:
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

API (краткое описание)
=
```
\QA\SoftMocks::init();
```
Инициализирует софт-моки. По умолчанию, кэш файлов создаётся в /tmp/mocks. Если вам такой путь не подходит, то его можно переопределить следующим образом:

```
\QA\SoftMocks::setMocksCachePath($cache_path);
```


Переопределение констант
==

Дать новое значение константе $constantName, или создать, если такой ещё не было объявлено. Создание делается не с помощью вызова define(), поэтому операцию можно отменить.

Поддерживаются как «обычные константы», так и константы классов, с синтаксисом "className::CONST_NAME".

```
\QA\SoftMocks::redefineConstant($constantName, $value)
```

Переопределение функций
==

Soft Mocks позволяет переопределить как пользовательские, так и встроенные функции, кроме функций, которые зависят от текущего контекста (см. свойство \QA\SoftMocksTraverser::$ignore_functions), а также тех, для которых есть встроенные моки (debug_backtrace, call_user_func* и некоторые другие).

Сигнатура:
```
\QA\SoftMocks::redefineFunction($func, $functionArgs, $fakeCode)
```

Пример использования (переопределить функцию strlen и вызвать оригинальную для trim'нутой строки):
```
\QA\SoftMocks::redefineFunction(
    'strlen',
    '$a',
    'return \\QA\\SoftMocks::callOriginal("strlen", [trim($a)]));'
);

var_dump(strlen("  a  ")); // int(1)
```

Переопределение методов
==

В данный момент поддерживается только переопределение пользовательских методов, для встроенных классов эта функциональность не поддерживается.

Сигнатура:
```
\QA\SoftMocks::redefineMethod($class, $method, $functionArgs, $fakeCode, $strict = true)
```

Отличие от redefineFunction состоит в наличии класса ($class) и возможности работать в нестрогом режиме ($strict = false). Если выбран нестрогий режим, то это эмулирует поведение runkit при переопределении методов класса, то есть, помимо самого метода класса $class переопределяются также и методы его наследников.

В качестве аргумента $class может выступать как имя класса, так и имя trait'а.

Переопределение конструкторов
==

Это метод позволяет переопределить конструктор как пользовательских, так и встроенных классов.

Сигнатура:
 ```
 \QA\SoftMocks::redefineNew($class, $constructorFunc)
 ```

Пример использования (переопределить конструктор \DateTime):
```
\QA\SoftMocks::redefineNew('DateTime', function() {
    return new \DateTime('2015-02-03 00:00:00');
});

var_dump((new \DateTime)->format('c')); // string(25) "2015-02-03T00:00:00+03:00"
```

Переопределение функций, являющихся генераторами
==
Метод позволяет заменить вызов функции-генератора на \Callable-объект также являющийся генератором. Генераторы отличаются от обычных функций тем, что в них невозможно вернуть значение через return и обязательно нужно пользоваться yield.

```
\QA\SoftMocks::redefineGenerator($class, $method, Callable $replacement)
```

Восстановление значений
==

Следующие функции отменяют замену, осуществлённую одним из приведенных раннее redefine-методов.

```
\QA\SoftMocks::restoreConstant($constantName)
\QA\SoftMocks::restoreAllConstants()
\QA\SoftMocks::restoreFunction($func)
\QA\SoftMocks::restoreAll()
\QA\SoftMocks::restoreMethod($class, $method)
\QA\SoftMocks::restoreGenerator($class, $method)
```

FAQ
=
**Q**: Как запретить переопределять ту или иную функцию/класс/константу?

**A**: Используйте методы \QA\SoftMocks::ignore(Class|Function|Constant).

**Q**: Я не могу переопределить вызовы некоторых функций: call_user_func(_array)?, defined, etc.\

**A**: Есть ряд функций, для который существуют встроенные моки и их действительно нельзя перехватить. Вот их список:
* call_user_func_array
* call_user_func
* is_callable
* function_exists
* constant
* defined
* debug_backtrace
