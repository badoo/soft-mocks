<?php
/**
 * Mocks core that rewrites code
 * @author Yuriy Nasretdinov <y.nasretdinov@corp.badoo.com>
 * @author Oleg Efimov <o.efimov@corp.badoo.com>
 * @author Kirill Abrosimov <k.abrosimov@corp.badoo.com>
 */

namespace Badoo;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/SoftMocksFunctionCreator.php';
require_once __DIR__ . '/SoftMocksPrinter.php';
require_once __DIR__ . '/SoftMocksTraverser.php';
require_once __DIR__ . '/SoftMocksStream.php';

class SoftMocks
{
    /** for create new files when parser version changed */
    const PARSER_VERSION = '3.0.6';
    const MOCKS_CACHE_TOUCHTIME = 86400; // 1 day

    private static $rewrite_cache = [/* source => target */];
    private static $orig_paths = [/* target => source */];

    private static $version;

    private static $error_descriptions = [
        E_ERROR => "Error",
        E_WARNING => "Warning",
        E_PARSE => "Parse Error",
        E_NOTICE => "Notice",
        E_CORE_ERROR => "Core Error",
        E_CORE_WARNING => "Core Warning",
        E_COMPILE_ERROR => "Compile Error",
        E_COMPILE_WARNING => "Compile Warning",
        E_USER_ERROR => "User Error",
        E_USER_WARNING => "User Warning",
        E_USER_NOTICE => "User Notice",
        E_STRICT => "Strict Notice",
        E_RECOVERABLE_ERROR => "Recoverable Error",
    ];

    private static $ignore = [];

    public static $internal_functions = [];

    private static $mocks = [];

    private static $func_mocks = [];
    private static $internal_func_mocks = []; // internal mocks that cannot be changed

    private static $generator_mocks = []; // mocks for generators
    private static $generator_func_mocks = []; // mocks for generators

    private static $new_mocks = []; // mocks for "new" operator
    private static $lang_construct_mocks = [];
    private static $constant_mocks = [];
    private static $removed_constants = [];

    private static $debug = false;

    private static $temp_disable = false;

    const LANG_CONSTRUCT_EXIT = 'exit';

    private static $project_path;
    private static $rewrite_internal = false;
    private static $mocks_cache_path = "/tmp/mocks/";
    private static $ignore_sub_paths = [
        '/phpunit/' => '/phpunit/',
        '/php-parser/' => '/php-parser/',
    ];
    private static $base_paths = [];
    private static $prepare_for_rewrite_callback;
    private static $lock_file_path = '/tmp/mocks/soft_mocks_rewrite.lock';

    protected static function getEnvironment($key)
    {
        return \getenv($key);
    }

    public static function init()
    {
        // SoftMocks wrapper is not going to work well with opcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
            register_shutdown_function('opcache_reset');
        }

        if (!defined('SOFTMOCKS_ROOT_PATH')) {
            define('SOFTMOCKS_ROOT_PATH', '/');
        }

        if (!empty(static::getEnvironment('SOFT_MOCKS_DEBUG'))) {
            self::$debug = true;
        }

        stream_wrapper_register("soft", SoftMocksStream::class);

        self::$func_mocks['call_user_func_array'] = [
            'args' => '', 'code' => 'return \\' . self::class . '::call($params[0], $params[1]);',
        ];
        self::$func_mocks['call_user_func'] = [
            'args' => '', 'code' => '$func = array_shift($params); return \\' . self::class . '::call($func, $params);',
        ];
        self::$func_mocks['is_callable'] = [
            'args' => '$arg', 'code' => 'return \\' . self::class . '::isCallable($arg);',
        ];
        self::$func_mocks['constant'] = [
            'args' => '$constant', 'code' => 'return \\' . self::class . '::getConst("", $constant);',
        ];
        self::$func_mocks['defined'] = [
            'args' => '$constant', 'code' => 'return \\' . self::class . '::constDefined($constant);',
        ];

        self::$internal_func_mocks = [];
        foreach (self::$func_mocks as $func => $mock) {
            self::$internal_func_mocks[$func] = $mock;
        }

        $functions = get_defined_functions();
        foreach ($functions['internal'] as $func) {
            self::$internal_functions[$func] = true;
        }

        self::ignoreFiles(get_included_files());
        self::injectIntoPhpunit();
        self::initProjectPath();
    }

    protected static function initProjectPath()
    {
        $lib_path = dirname(dirname(__DIR__));
        $vendor_path = dirname(dirname($lib_path));
        if (basename($vendor_path) === 'vendor') {
            self::$project_path = dirname($vendor_path);
            return;
        }
        self::$project_path = $lib_path;
    }

    public static function setProjectPath($project_path)
    {
        if (!empty($project_path)) {
            self::$project_path = rtrim($project_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        if (!is_dir(self::$project_path)) {
            throw new \RuntimeException("Project path isn't exists");
        }
    }

    /**
     * @param bool $rewrite_internal
     */
    public static function setRewriteInternal($rewrite_internal)
    {
        self::$rewrite_internal = (bool)$rewrite_internal;
    }

    /**
     * @param string $mocks_cache_path - Path to cache of rewritten files
     */
    public static function setMocksCachePath($mocks_cache_path)
    {
        if (!empty($mocks_cache_path)) {
            self::$mocks_cache_path = rtrim($mocks_cache_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        if (!file_exists(self::$mocks_cache_path) && !mkdir(self::$mocks_cache_path, 0777)) {
            throw new \RuntimeException("Can't create cache dir for rewritten files at " . self::$mocks_cache_path);
        }
    }

    /**
     * @param $lock_file_path - Path to lock file that is used when file is rewritten
     */
    public static function setLockFilePath($lock_file_path)
    {
        if (!empty($lock_file_path)) {
            self::$lock_file_path = $lock_file_path;
        }

        if (!file_exists(self::$lock_file_path) && !touch(self::$lock_file_path)) {
            throw new \RuntimeException("Can't create lock file at " . self::$lock_file_path);
        }
    }

    /**
     * @deprecated use addIgnorePath
     * @see addIgnoreSubPath
     * @param string $phpunit_path - Part of path to phpunit so that it can be ignored when rewriting files
     */
    public static function setPhpunitPath($phpunit_path)
    {
        if ($phpunit_path) {
            unset(self::$ignore_sub_paths['/phpunit/']);
        }
        self::addIgnoreSubPath($phpunit_path);
    }

    /**
     * @deprecated use addIgnorePath
     * @see addIgnoreSubPath
     * @param $php_parser_path - Part of path to PHP Parser so that it can be ignored when rewriting files
     */
    public static function setPhpParserPath($php_parser_path)
    {
        if ($php_parser_path) {
            unset(self::$ignore_sub_paths['/php-parser/']);
        }
        self::addIgnoreSubPath($php_parser_path);
    }

    /**
     * @param string $sub_path Part of path so that it can be ignored when rewriting files
     */
    public static function addIgnoreSubPath($sub_path)
    {
        if (!empty($sub_path)) {
            self::$ignore_sub_paths[$sub_path] = $sub_path;
        }
    }

    /**
     * @param array $ignore_sub_paths will be ignored when rewriting files:
     * array(
     *     'path' => 'path',
     * )
     */
    public static function setIgnoreSubPaths(array $ignore_sub_paths)
    {
        self::$ignore_sub_paths = $ignore_sub_paths;
    }

    public static function addBasePath($base_path)
    {
        if (!empty($base_path)) {
            self::$base_paths[] = $base_path;
        }
    }

    public static function setBasePaths(array $base_paths)
    {
        self::$base_paths = $base_paths;
    }

    /**
     * @param callable $prepare_for_rewrite_callback
     */
    public static function setPrepareForRewriteCallback($prepare_for_rewrite_callback)
    {
        if (!empty($prepare_for_rewrite_callback)) {
            self::$prepare_for_rewrite_callback = $prepare_for_rewrite_callback;
        }
    }

    /**
     * @param string $class - Do not allow to mock $class
     */
    public static function ignoreClass($class)
    {
        SoftMocksTraverser::ignoreClass(ltrim($class, '\\'));
    }

    /**
     * @param string $constant - Do not allow to mock $constant
     */
    public static function ignoreConstant($constant)
    {
        SoftMocksTraverser::ignoreConstant(ltrim($constant, '\\'));
    }

    /**
     * @param string $function - Do not allow to mock $function
     */
    public static function ignoreFunction($function)
    {
        SoftMocksTraverser::ignoreFunction(ltrim($function, '\\'));
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return;
        }
        $descr = isset(self::$error_descriptions[$errno]) ? self::$error_descriptions[$errno] : "Unknown error ($errno)";
        echo "\n$descr: $errstr in " . self::replaceFilename($errfile) . " on line $errline\n";
    }

    // printBackTrace is no longer needed, actually
    public static function printBackTrace($e = null)
    {
        echo $e ?: new \Exception();
    }

    public static function replaceFilenameRaw($file)
    {
        return self::replaceFilename($file, true);
    }

    public static function replaceFilename($file, $raw = false)
    {
        if (isset(self::$orig_paths[$file])) {
            if ($raw) {
                return self::$orig_paths[$file];
            }
            return $file . " (orig: " . self::$orig_paths[$file] . ")";
        }

        return $file;
    }

    public static function clearBasePath($file)
    {
        foreach (self::$base_paths as $base_path) {
            if (mb_orig_strpos($file, $base_path) === 0) {
                return mb_orig_substr($file, 0, mb_orig_strlen($base_path));
            }
        }
        return $file;
    }

    private static function getVersion()
    {
        if (!isset(self::$version)) {
            $res = '';
            $files = glob(__DIR__ . '/*.php');
            sort($files);

            foreach ($files as $file) {
                $res .= md5_file($file);
            }

            self::$version = phpversion() . self::PARSER_VERSION . md5($res);
        }
        return self::$version;
    }

    public static function rewrite($file)
    {
        return (self::$orig_paths[$file] = 'soft://' . $file);
    }

    public static function doRewrite($file, &$opened_path = '')
    {
        if (self::$prepare_for_rewrite_callback !== null) {
            $callback = self::$prepare_for_rewrite_callback;
            $file = $callback($file);
        }
        $file = self::resolveFile($file);

        if (!$file) {
            return $file;
        }

        $opened_path = $file;

        if (!isset(self::$rewrite_cache[$file])) {
            if (mb_orig_strpos($file, self::$mocks_cache_path) === 0
                || mb_orig_strpos($file, self::getVersion() . DIRECTORY_SEPARATOR) === 0) {
                return $file;
            }

            foreach (self::$ignore_sub_paths as $ignore_path) {
                if (mb_orig_strpos($file, $ignore_path) !== false) {
                    return $file;
                }
            }

            if (isset(self::$ignore[$file])) {
                return $file;
            }

            $md5_file = md5_file($file);
            if (!$md5_file) {
                return (self::$orig_paths[$file] = self::$rewrite_cache[$file] = $file);
            }

            $clean_filepath = $file;
            if (strpos($clean_filepath, SOFTMOCKS_ROOT_PATH) === 0) {
                $clean_filepath = substr($clean_filepath, strlen(SOFTMOCKS_ROOT_PATH));
            }

            $md5 = md5($clean_filepath . ':' . $md5_file);
            if (self::$project_path && strpos($file, self::$project_path) === 0) {
                $file_in_project = substr($file, strlen(self::$project_path));
            } else {
                $file_in_project = basename($file);
            }

            $target_file = self::$mocks_cache_path . self::getVersion() . DIRECTORY_SEPARATOR . $file_in_project . "_" . $md5 . ".php";
            if (!file_exists($target_file)) {
                $old_umask = umask(0);
                self::createRewrittenFile($file, $target_file);
                umask($old_umask);
                /* simulate atime to prevent deletion files if you use find "$CACHE_DIR" -mtime +14 -type f -delete */
            } else if (time() - filemtime($target_file) > self::MOCKS_CACHE_TOUCHTIME) {
                touch($target_file);
            }

            $target_file = realpath($target_file);
            self::$rewrite_cache[$file] = $target_file;
            self::$orig_paths[$target_file] = $file;
        }

        return self::$rewrite_cache[$file];
    }

    private static function resolveFile($file)
    {
        if (!$file) {
            return $file;
        }
        // if path is not absolute
        if ($file[0] !== '/') {
            // skip stream
            $path_info = parse_url($file);
            if (isset($path_info['scheme'])) {
                return $file;
            }
            $found = false;
            $cwd = getcwd();
            // try include path
            foreach (explode(':', get_include_path()) as $dir) {
                if ($dir === '.') {
                    $dir = $cwd;
                }

                if (file_exists("{$dir}/{$file}")) {
                    $file = "{$dir}/{$file}";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // try relative path
                $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                $dir = dirname(self::replaceFilename($bt[2]['file'], true));
                if (file_exists("{$dir}/{$file}")) {
                    $file = "{$dir}/{$file}";
                } else {
                    // try cwd
                    $dir = $cwd;
                    if (file_exists("{$dir}/{$file}")) {
                        $file = "{$dir}/{$file}";
                    }
                }
            }
        }
        // resolve symlinks
        return realpath($file);
    }

    private static function createRewrittenFile($file, $target_file)
    {
        if (self::$debug) {
            fwrite(STDOUT, "Rewriting $file => $target_file\n");
            fwrite(STDOUT, new \Exception());
            fwrite(STDOUT, "\n");
        }

        $contents = file_get_contents($file);
        $old_nesting_level = ini_set('xdebug.max_nesting_level', 3000);
        $contents = self::rewriteContents($file, $target_file, $contents);
        ini_set('xdebug.max_nesting_level', $old_nesting_level);

        if (!$fp = fopen(self::$lock_file_path, 'a+')) {
            throw new \RuntimeException("Could not create lock file " . self::$lock_file_path);
        }

        if (!flock($fp, LOCK_EX)) {
            throw new \RuntimeException("Could not flock " . self::$lock_file_path);
        }

        $target_dir = dirname($target_file);
        $base_mocks_path = '';
        $relative_target_dir = $target_dir;
        if (mb_orig_strpos($file, self::$mocks_cache_path) === 0) {
            $base_mocks_path = self::$mocks_cache_path;
            $relative_target_dir = substr($target_dir, strlen($base_mocks_path));
        }
        self::createDirRecursive($base_mocks_path, $relative_target_dir);

        $tmp_file = $target_file . ".tmp." . uniqid(getmypid());
        $wrote = file_put_contents($tmp_file, $contents);
        $expected_bytes = mb_orig_strlen($contents);
        if ($wrote !== $expected_bytes) {
            throw new \RuntimeException('Could not fully write rewritten content! Wrote ' . var_export($wrote, true) . " instead of $expected_bytes");
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            // You cannot atomically replace files in Windows
            if (file_exists($target_file) && !unlink($target_file)) {
                throw new \RuntimeException("Could not unlink $target_file");
            }
        }

        if (!rename($tmp_file, $target_file)) {
            throw new \RuntimeException("Could not move tmp rewritten file $tmp_file into $target_file");
        }

        if (!fclose($fp)) {
            throw new \RuntimeException("Could not fclose lock file descriptor for file " . self::$lock_file_path);
        }
    }

    /**
     * Create dir recursive
     * if create dirs recursive using mkdir with $recursive = true, then there can be race conditions, for example:
     * process1: mkdir('/foo/bar1', 0777, true);
     * process1: check '/foo', it not exists, try to create it
     * process2: mkdir('/foo/bar2', 0777, true);
     * process2: check '/foo', it not exists, try to create it
     * process1: successful created '/foo', check '/foo/bar1', it not exists, create it
     * process1: can't create '/foo', '/foo/bar2' not exists, fail
     * to prevent this race condition, create dir recursive manually
     *
     * @see https://bugs.php.net/bug.php?id=35326
     *
     * @param string $base_dir base (existing) dir
     * @param string $relative_target_dir dir, which need to create
     * @throws \RuntimeException
     */
    private static function createDirRecursive($base_dir, $relative_target_dir)
    {
        $current_dir = $base_dir;
        foreach (explode(DIRECTORY_SEPARATOR, $relative_target_dir) as $sub_dir) {
            $current_dir .= DIRECTORY_SEPARATOR . $sub_dir;
            if (!@mkdir($current_dir) && !is_dir($current_dir)) {
                $error = error_get_last();
                $message = '';
                if (is_array($error)) {
                    $message = ", error: {$error['message']}";
                }
                throw new \RuntimeException("Can't create directory {$current_dir}{$message}");
            }
        }
    }

    /**
     * Generic method to call a callable, useful for proxying call_user_func* calls
     *
     * @param $callable
     * @param $args
     * @return mixed
     */
    public static function call($callable, $args)
    {
        if (is_scalar($callable) && mb_orig_strpos($callable, '::') === false) {
            return self::callFunction('', $callable, $args);
        }

        if (is_scalar($callable)) {
            $parts = explode('::', $callable);
            if (count($parts) != 2) {
                throw new \RuntimeException("Invalid callable format for '$callable', expected single '::'");
            }
            list($obj, $method) = $parts;
        } else if (is_array($callable)) {
            if (count($callable) != 2) {
                throw new \RuntimeException("Invalid callable format, expected array of exactly 2 elements");
            }
            list($obj, $method) = $callable;
        } else {
            return call_user_func_array($callable, $args);
        }

        if (is_object($obj)) {
            return self::callMethod($obj, null, $method, $args, true);
        } else if (is_scalar($obj)) {
            return self::callStaticMethod($obj, $method, $args, true);
        }

        throw new \RuntimeException("Invalid callable format, expected first array element to be object or scalar, " . gettype($obj) . " given");
    }

    public static function isCallable($callable)
    {
        if (empty($callable)) {
            return false;
        }

        if (is_array($callable) && sizeof($callable) === 2) {
            if (is_object($callable[0])) {
                $class = get_class($callable[0]);
            } else {
                $class = $callable[0];
            }

            if (isset(self::$mocks[$class][$callable[1]])) {
                return true;
            }

            return is_callable($callable);
        }

        if (is_scalar($callable) && isset(self::$func_mocks[$callable])) {
            return true;
        }

        return is_callable($callable);
    }

    public static function constDefined($const)
    {
        if (isset(self::$removed_constants[$const])) {
            return false;
        }
        return defined($const) || isset(self::$constant_mocks[$const]);
    }

    public static function callMethod($obj, $class, $method, $args, $check_mock = false)
    {
        if (!$class) {
            $class = get_class($obj);
        }
        if ($check_mock && isset(self::$mocks[$class][$method])) {
            if (self::$debug) {
                self::debug("Intercepting call to $class->$method");
            }
            return (new SoftMocksFunctionCreator())->run($obj, $class, $args, self::$mocks[$class][$method]);
        }

        try {
            $Rm = new \ReflectionMethod($class, $method);
            $Rm->setAccessible(true);

            $decl_class = $Rm->getDeclaringClass()->getName();
            if ($check_mock && isset(self::$mocks[$decl_class][$method])) {
                if (self::$debug) {
                    self::debug("Intercepting call to $class->$method");
                }
                return (new SoftMocksFunctionCreator())->run($obj, $class, $args, self::$mocks[$decl_class][$method]);
            }
        } catch (\ReflectionException $e) {
            if (method_exists($obj, '__call')) {
                $Rm = new \ReflectionMethod($obj, '__call');
                $Rm->setAccessible(true);
                return $Rm->invokeArgs($obj, [$method, $args]);
            }

            return call_user_func_array([$obj, $method], $args); // give up, got some weird shit
        }

        return $Rm->invokeArgs($obj, $args);
    }

    public static function callStaticMethod($class, $method, $args, $check_mock = false)
    {
        if ($check_mock && isset(self::$mocks[$class][$method])) {
            if (self::$debug) {
                self::debug("Intercepting call to $class::$method");
            }
            return (new SoftMocksFunctionCreator())->run(null, $class, $args, self::$mocks[$class][$method]);
        }

        try {
            $Rm = new \ReflectionMethod($class, $method);
            $Rm->setAccessible(true);

            $decl_class = $Rm->getDeclaringClass()->getName();

            if ($check_mock && isset(self::$mocks[$decl_class][$method])) {
                if (self::$debug) {
                    self::debug("Intercepting call to $class::$method");
                }
                return (new SoftMocksFunctionCreator())->run(null, $class, $args, self::$mocks[$decl_class][$method]);
            }
        } catch (\ReflectionException $e) {
            if (method_exists($class, '__callStatic')) {
                $Rm = new \ReflectionMethod($class, '__callStatic');
                $Rm->setAccessible(true);
                return $Rm->invokeArgs(null, [$method, $args]);
            }

            return call_user_func_array([$class, $method], $args);
        }

        return $Rm->invokeArgs(null, $args);
    }

    public static function callExit($code = '')
    {
        if (empty(self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT])) {
            exit($code);
        } else {
            if (self::$debug) {
                self::debug("Intercepting call to exit()/die()");
            }
            $params = [$code]; // $params will be used inside the eval()
            if (self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]['code'] instanceof \Closure) {
                $callable = self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]['code'];
            } else {
                $callable = eval("return function(" . self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]['args'] . ") use(\$params) { \$mm_func_args = \$params; " . self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]['code'] . " };");
            }
            return call_user_func($callable, $code);
        }
    }

    public static function callFunction($namespace, $func, $params)
    {
        if ($namespace !== '' && is_scalar($func)) {
            $ns_func = $namespace . '\\' . $func;
            if (isset(self::$func_mocks[$ns_func])) {
                if (self::$debug) {
                    self::debug("Intercepting call to $ns_func");
                }
                $func_callable = eval("return function(" . self::$func_mocks[$ns_func]['args'] . ") use (\$params) { \$mm_func_args = \$params; " . self::$func_mocks[$ns_func]['code'] . " };");

                return call_user_func_array($func_callable, $params);
            }

            if (is_callable($ns_func)) {
                return call_user_func_array($ns_func, $params);
            }
        }
        if (is_scalar($func)) {
            if (isset(self::$func_mocks[$func])) {
                if (self::$debug) {
                    self::debug("Intercepting call to $func");
                }
                if (self::$func_mocks[$func]['code'] instanceof \Closure) {
                    $func_callable = self::$func_mocks[$func]['code'];
                } else {
                    $func_callable = eval("return function(" . self::$func_mocks[$func]['args'] . ") use (\$params) { \$mm_func_args = \$params; " . self::$func_mocks[$func]['code'] . " };");
                }
                return call_user_func_array($func_callable, $params);
            }
        }
        return call_user_func_array($func, $params);
    }

    public static function callNew($class, $args)
    {
        if (isset(self::$new_mocks[$class])) {
            return call_user_func_array(self::$new_mocks[$class], $args);
        }

        $Rc = new \ReflectionClass($class);
        $Constructor = $Rc->getConstructor();

        if ($Constructor && !$Constructor->isPublic()) {
            $instance = $Rc->newInstanceWithoutConstructor();
            $Constructor->setAccessible(true);
            $Constructor->invokeArgs($instance, $args);
        } else {
            $instance = $Rc->newInstanceArgs($args);
        }

        return $instance;
    }

    public static function getConst($namespace, $const)
    {
        if ($namespace !== '') {
            $ns_const = $namespace . '\\' . $const;
            if (array_key_exists($ns_const, self::$constant_mocks)) {
                if (self::$debug) {
                    self::debug("Mocked $ns_const");
                }
                return self::$constant_mocks[$ns_const];
            }

            if (defined($ns_const)) {
                return constant($ns_const);
            }
        }

        if (array_key_exists($const, self::$constant_mocks)) {
            if (self::$debug) {
                self::debug("Mocked $const");
            }
            return self::$constant_mocks[$const];
        }

        if (array_key_exists($const, self::$removed_constants)) {
            trigger_error('Trying to access removed constant ' . $const . ', assuming "' . $const . '"');
            return $const;
        }

        return constant($const);
    }

    public static function getClassConst($class, $const, $self_class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $const_full_name = $class . '::' . $const;

        // Check current scope, see comment below
        if (class_exists('ReflectionClassConstant', false)) {
            try {
                $R = new \ReflectionClassConstant($class, $const);
                if ($R->isPrivate()) {
                    if (is_null($self_class) || ($self_class !== $class)) {
                        throw new \Error("Cannot access private const {$const_full_name}");
                    }
                }
                if ($R->isProtected()) {
                    if (is_null($self_class) || (($self_class !== $class) && !is_subclass_of($self_class, $class))) {
                        throw new \Error("Cannot access protected const {$const_full_name}");
                    }
                }
            } catch (\ReflectionException $E) {/* if we add new constant */}
        }

        if (isset(self::$constant_mocks[$const_full_name])) {
            if (self::$debug) {
                self::debug("Intercepting constant $const_full_name");
            }
            return self::$constant_mocks[$const_full_name];
        }

        if (isset(self::$removed_constants[$const_full_name])) {
            trigger_error('Trying to access removed constant ' . $const_full_name . ', assuming "' . $const_full_name . '"');
            return $const_full_name;
        }

        // To avoid 'Cannot access private/protected const' error, see comment above
        return !empty($R) ? $R->getValue() : constant($const_full_name);
    }

    private static function rewriteContents($orig_file, $target_file, $contents)
    {
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new SoftMocksTraverser($orig_file));

        $prettyPrinter = new SoftMocksPrinter();
        $parser = (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::PREFER_PHP7);
        $stmts = $parser->parse($contents);
        $stmts = $traverser->traverse($stmts);

        return $prettyPrinter->prettyPrintFile($stmts);
    }

    public static function ignoreFiles($files)
    {
        foreach ($files as $f) {
            if (self::$debug) {
                self::debug("Asked to ignore $f");
            }
            self::$ignore[$f] = true;
        }
    }

    public static function redefineFunction($func, $functionArgs, $fakeCode)
    {
        if (self::$debug) {
            self::debug("Asked to redefine $func($functionArgs)");
        }
        if (!self::$rewrite_internal && isset(self::$internal_func_mocks[$func])) {
            throw new \RuntimeException("Function $func is mocked internally, cannot mock");
        }
        if (SoftMocksTraverser::isFunctionIgnored($func)) {
            throw new \RuntimeException("Function $func cannot be mocked using Soft Mocks");
        }
        if (!is_scalar($fakeCode)) {
            throw new \RuntimeException("Only code in text form can be supplied");
        }
        self::$func_mocks[$func] = ['args' => $functionArgs, 'code' => $fakeCode];
    }

    public static function redefineExit($args, $fakeCode)
    {
        if (self::$debug) {
            self::debug("Asked to redefine exit(\$code)");
        }
        self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT] = ['args' => $args, 'code' => $fakeCode];
    }

    public static function restoreFunction($func)
    {
        if (isset(self::$internal_func_mocks[$func])) {
            if (!self::$rewrite_internal) {
                throw new \RuntimeException("Function $func is mocked internally, cannot unmock");
            }
            self::$func_mocks[$func] = self::$internal_func_mocks[$func];
            return;
        }

        unset(self::$func_mocks[$func]);
    }

    public static function restoreAll()
    {
        self::$mocks = [];
        self::$generator_mocks = [];
        self::$generator_func_mocks = [];
        self::$func_mocks = self::$internal_func_mocks;
        self::$temp_disable = false;
        self::$lang_construct_mocks = [];
        self::restoreAllConstants();
        self::restoreAllNew();
    }

    /**
     * Redefine method $class::$method with args list specified by $functionArgs
     * (args list must be compatible with original function, you can specify only variable names)
     * by using code $fakeCode instead of original function code.
     *
     * There are two already defined variables that you can use in fake code:
     *  $mm_func_args = func_get_args();
     *  $params is array of references to supplied arguments (func_get_args() does not contain refs in PHP5)
     *
     * You can use SoftMocks::callOriginal(...) for accessing original function/method as well
     *
     * Example:
     *
     *  class A { public function b($c, &$d) { var_dump($c, $d); } }
     *
     *  SoftMocks::redefineMethod(A::class, 'b', '$e, &$f', '$f = "hello";');
     *  $a = 2;
     *  (new A())->b(1, $a); // nothing is printed here, so we intercepted the call
     *  var_dump($a); // string(5) "hello"
     *
     * @param string $class
     * @param string $method         Method of class to be intercepted
     * @param string $functionArgs   List of argument names
     * @param string $fakeCode       Code that will be eval'ed instead of function code
     */
    public static function redefineMethod($class, $method, $functionArgs, $fakeCode)
    {
        if (self::$debug) {
            self::debug("Asked to redefine $class::$method($functionArgs)");
        }
        if (SoftMocksTraverser::isClassIgnored($class)) {
            throw new \RuntimeException("Class $class cannot be mocked using Soft Mocks");
        }

        $params = [];
        $real_classname = $real_methodname = '';
        try {
            $Rc = new \ReflectionClass($class);
            $real_classname = $Rc->getName();
            $Rm = $Rc->getMethod($method);
            $real_methodname = $Rm->getName();
            $params = $Rm->getParameters();
        } catch (\Exception $e) {
            if (self::$debug) {
                self::debug("Could not get parameters for $class::$method via reflection: $e");
            }
        }

        if (($real_classname && $real_classname != $class) || ($real_methodname && $real_methodname != $method)) {
            throw new \RuntimeException("Requested to mock $class::$method while method name is $real_classname::$real_methodname");
        }

        self::$mocks[$class][$method] = [
            'args' => $functionArgs,
            'code' => self::generateCode($functionArgs, $params) . $fakeCode,
        ];
    }

    public static function restoreMethod($class, $method)
    {
        if (self::$debug) {
            self::debug("Restore method $class::$method");
        }
        if (isset(self::$mocks[$class][$method]['decl_class'])) {
            if (self::$debug) {
                self::debug("Restore also method $class::$method");
            }
            unset(self::$mocks[self::$mocks[$class][$method]['decl_class']][$method]);
        }
        unset(self::$mocks[$class][$method]);
    }

    public static function restoreExit()
    {
        if (self::$debug) {
            self::debug("Restore exit language construct");
        }
        unset(self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]);
    }

    public static function redefineGenerator($class, $method, callable $replacement)
    {
        self::$generator_mocks[$class][$method] = $replacement;
    }

    public static function redefineGeneratorFunction($func, callable $replacement)
    {
        self::$generator_func_mocks[$func] = $replacement;
    }

    public static function restoreGenerator($class, $method)
    {
        unset(self::$generator_mocks[$class][$method]);
    }

    public static function restoreGeneratorFunction($func)
    {
        unset(self::$generator_func_mocks[$func]);
    }

    public static function isGeneratorMocked($class, $method)
    {
        return isset(self::$generator_mocks[$class][$method]);
    }

    public static function isGeneratorMockedFunction($func)
    {
        return isset(self::$generator_func_mocks[$func]);
    }

    public static function getMockForGenerator($class, $method)
    {
        if (!isset(self::$generator_mocks[$class][$method])) {
            throw new \RuntimeException("Generator $class::$method is not mocked");
        }

        return self::$generator_mocks[$class][$method];
    }

    public static function getMockForGeneratorFunction($func)
    {
        if (!isset(self::$generator_func_mocks[$func])) {
            throw new \RuntimeException("Generator $func is not mocked");
        }

        return self::$generator_mocks[$func];
    }

    public static function redefineNew($class, callable $constructorFunc)
    {
        self::$new_mocks[$class] = $constructorFunc;
    }

    public static function restoreNew($class)
    {
        unset(self::$new_mocks[$class]);
    }

    public static function restoreAllNew()
    {
        self::$new_mocks = [];
    }

    public static function redefineConstant($constantName, $value)
    {
        $constantName = ltrim($constantName, '\\');
        if (self::$debug) {
            self::debug("Asked to redefine constant $constantName to $value");
        }

        if (SoftMocksTraverser::isConstIgnored($constantName)) {
            throw new \RuntimeException("Constant $constantName cannot be mocked using Soft Mocks");
        }

        self::$constant_mocks[$constantName] = $value;
    }

    public static function restoreConstant($constantName)
    {
        unset(self::$constant_mocks[$constantName]);
    }

    public static function restoreAllConstants()
    {
        self::$constant_mocks = [];
        self::$removed_constants = [];
    }

    public static function removeConstant($constantName)
    {
        unset(self::$constant_mocks[$constantName]);
        self::$removed_constants[$constantName] = true;
    }

    // there can be a situation when usage of static is not suitable for mocking so we need additional checks here
    // see \Badoo\SoftMocksTest::testParentMismatch to see when getDeclaringClass check is needed
    private static function staticContextIsOk($self, $static, $method)
    {
        try {
            $Rm = new \ReflectionMethod($static, $method);
            $Dc = $Rm->getDeclaringClass();
            if (!$Dc) {
                if (self::$debug) {
                    self::debug("Failed to get geclaring class for $static::$method");
                }
                return false;
            }

            $decl_class = $Dc->getName();
            if ($decl_class === $self) {
                return true;
            }

            // In PHP 5.5 the declared class is actually correct class, but it never a trait.
            // So we need to find the actual trait then if it is applicable to the class
            $Dt = self::getDeclaringTrait($decl_class, $method);
            if (!$Dt) {
                if (self::$debug) {
                    self::debug("Failed to get geclaring trait for $static::$method ($decl_class::$method");
                }
                return false;
            }

            if ($Dt->getName() === $self) {
                return true;
            }
        } catch (\ReflectionException $e) {
            if (self::$debug) {
                self::debug("Failed to get reflection method for $static::$method: $e");
            }
        }

        return false;
    }

    private static function getDeclaringTrait($class, $method)
    {
        $Rc = new \ReflectionClass($class);

        foreach (self::recursiveGetTraits($Rc) as $Trait) {
            if ($Trait->hasMethod($method)) {
                return $Trait;
            }
        }

        return null;
    }

    /**
     * @param \ReflectionClass $Rc
     * @return \ReflectionClass[]
     */
    private static function recursiveGetTraits(\ReflectionClass $Rc)
    {
        foreach ($Rc->getTraits() as $Trait) {
            yield $Trait;

            foreach (self::recursiveGetTraits($Trait) as $T) {
                yield $T;
            }
        }
    }

    /**
     * @param $self
     * @param $static
     * @param $method
     * @return false|string code
     */
    public static function isMocked($self, $static, $method)
    {
        if (self::$temp_disable) {
            if (self::$debug) {
                self::debug("Temporarily disabling mock check: $self::$method (static = $static)");
            }
            self::$temp_disable = false;
            return false;
        }

        $ancestor = $static;
        do {
            if (isset(self::$mocks[$ancestor][$method]) && self::staticContextIsOk($self, $ancestor, $method)) {
                return self::$mocks[$ancestor][$method]['code'];
            }
        } while ($ancestor = get_parent_class($ancestor));

        // it is very hard to make "self" work incorrectly because "self" is just an alias for class name at compile time
        return isset(self::$mocks[$self][$method]) ? self::$mocks[$self][$method]['code'] : false;
    }

    /**
     * @param string $func
     * @return false|string code
     */
    public static function isFuncMocked($func)
    {
        if (self::$temp_disable) {
            if (self::$debug) {
                self::debug("Temporarily disabling mock check: $func");
            }
            self::$temp_disable = false;
            return false;
        }

        if (!isset(self::$func_mocks[$func]['code'])) {
            return false;
        }

        if (self::$debug) {
            self::debug("Func is mocked: $func");
        }

        return "\$__softmocks_cb = function(" . self::$func_mocks[$func]['args'] . ") use (\$params) { \$mm_func_args = \$params; " . self::$func_mocks[$func]['code'] . "; }; return call_user_func_array(\$__softmocks_cb, \$mm_func_args);";
    }

    public static function callOriginal($callable, $args, $class = null)
    {
        if (is_array($callable)) {
            if (is_object($callable[0])) {
                $obj = $callable[0];
                if (!$class) $class = get_class($obj);
            } else {
                $class = $callable[0];
            }

            $method = $callable[1];
        } else if (is_scalar($callable) && mb_orig_strpos($callable, '::') !== false) {
            list($class, $method) = explode("::", $callable);
        } else if (is_scalar($callable)) {
            try {
                $Rf = new \ReflectionFunction($callable);
                if ($Rf->isUserDefined()) {
                    self::$temp_disable = true; // we can only mock and check for mocks for user defined methods
                }
            } catch (\ReflectionException $e) {
                // do nothing, it is ok in this case because it means that mock disabling is not needed
            }
            return call_user_func_array($callable, $args);
        } else {
            return call_user_func_array($callable, $args);
        }

        try {
            $Rm = new \ReflectionMethod($class, $method);
            if ($Rm->isUserDefined()) {
                self::$temp_disable = true; // we can only mock and check for mocks for user defined methods
            }
        } catch (\ReflectionException $e) {
            // do nothing, it is ok in this case because it means that mock disabling is not needed
        }

        if (isset($obj)) {
            return self::callMethod($obj, $class, $method, $args);
        } else {
            return self::callStaticMethod($class, $method, $args);
        }
    }

    /**
     * Generate code that parses function parameters that are specified as string $args
     *
     * @param $args
     * @param \ReflectionParameter[] $params
     * @return string
     */
    private static function generateCode($args, array $params)
    {
        $args = trim($args);
        if (!$args) return '';

        $codeArgs = '';

        $list = token_get_all("<?php " . $args);
        $params_toks = [];
        $i = 0;
        foreach ($list as $tok) {
            if ($tok === ',') {
                $i++;
                continue;
            }
            $params_toks[$i][] = $tok;
        }

        foreach ($params_toks as $i => $toks) {
            $isRef = false;
            $varName = false;
            $haveDefault = false;
            $default = "";
            $mode = 'var';

            foreach ($toks as $tok) {
                if ($tok === '&') {
                    $isRef = true;
                    continue;
                }

                if ($tok === '=') {
                    $haveDefault = true;
                    $mode = 'default';
                    continue;
                }

                if ($mode == 'default') {
                    $default .= is_array($tok) ? $tok[1] : $tok;
                    continue;
                }

                if ($tok[0] === T_VARIABLE) {
                    $varName = $tok[1];
                }
            }

            if ($haveDefault) {
                $codeArgs .= "if (count(\$mm_func_args) > $i) {\n";
            }

            if ($isRef && isset($params[$i])) {
                $param_name = $params[$i]->getName();
                if (ltrim($varName, '$') !== $param_name) {
                    $codeArgs .= "$varName = &\$$param_name;\n";
                }
            } else {
                $codeArgs .= "$varName = \$params[$i];\n";
            }

            if ($haveDefault) {
                $codeArgs .= "} else {\n";
                $codeArgs .= "$varName = $default;\n";
                $codeArgs .= "}\n";
            }
        }

        return $codeArgs;
    }

    protected static function debug($message)
    {
        fwrite(STDOUT, $message . "\n");
    }

    public static function injectIntoPhpunit()
    {
        if (!class_exists(\PHPUnit_Util_Fileloader::class, false)) {
            return;
        }

        if (!is_callable([\PHPUnit_Util_Fileloader::class, 'setFilenameRewriteCallback'])) {
            if (self::$debug) {
                self::debug("Cannot inject into phpunit: method setFilenameRewriteCallback not found");
            }

            return;
        }

        \PHPUnit_Util_Fileloader::setFilenameRewriteCallback([self::class, 'rewrite']);

        \PHPUnit_Util_Fileloader::setFilenameRestoreCallback(
            function ($filename) {
                return self::replaceFilename($filename, true);
            }
        );

        \PHPUnit_Util_Filter::setCustomStackTraceCallback(
            function ($e) {
                ob_start();
                self::printBackTrace($e);
                return ob_get_clean();
            }
        );
    }
}
