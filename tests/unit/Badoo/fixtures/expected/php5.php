<?php
namespace Some\Name\Space;


/**
 * This file contains php5 code
 * And comments
 */
isset(\Badoo\SoftMocks::$func_mocks_by_name['error_reporting']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'error_reporting', [\Badoo\SoftMocks::getConst(__NAMESPACE__, 'E_ALL')]) : error_reporting(\Badoo\SoftMocks::getConst(__NAMESPACE__, 'E_ALL'));
isset(\Badoo\SoftMocks::$func_mocks_by_name['ini_set']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'ini_set', ['display_errors', true]) : ini_set('display_errors', true);


if (!empty($_SERVER['HTTP_ORIG_DOMAIN'])) {
    $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_ORIG_DOMAIN'];
    
    
    $header = 'HTTP/1.1 301 Moved Permanently';
    $redirect_address = 'https://badoo.com';
    
    isset(\Badoo\SoftMocks::$func_mocks_by_name['header']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'header', [$header]) : header($header);
    isset(\Badoo\SoftMocks::$func_mocks_by_name['header']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'header', ['Location: ' . $redirect_address]) : header('Location: ' . $redirect_address);
    
    echo <<<END
    <html>\n <head>\n  <title>Status 301 - Moved Permanently</title>\n  <meta http-equiv="Refresh" content="0; url={$redirect_address}">\n </head>\n <body bgcolor="#ffffff" text="#000000" link="#ff0000" alink="#ff0000" vlink="#ff0000">\n Document permanently moved: <a href="{$redirect_address}">{$redirect_address}</a>\n </body>\n</html>
    END;
    
    
    
    
    
    
    
    
    \Badoo\SoftMocks::callExit();} 
    elseif (false) {
        echo "Never1!\n";} else {
    if (false) {
        echo "Never2!\n";} else {
        
        echo "Always!\n";}}


if (true) {echo "Always!\n";} else {
    echo "Never!\n";}

$developer = 'somebody';

$_SERVER['developer'] = $developer;

@(isset(\Badoo\SoftMocks::$func_mocks_by_name['ini_set']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'ini_set', ['error_log', '/local/logs/php/badoo-' . $developer . '.log']) : ini_set('error_log', '/local/logs/php/badoo-' . $developer . '.log'));

isset(\Badoo\SoftMocks::$func_mocks_by_name['define']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'define', ['PHPWEB_PATH_PHOTOS', '/home/' . $developer . '/photos']) : define('PHPWEB_PATH_PHOTOS', '/home/' . $developer . '/photos');

$old_umask = isset(\Badoo\SoftMocks::$func_mocks_by_name['umask']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'umask', [0]) : umask(0);
$create_dirs = [\Badoo\SoftMocks::getConst(__NAMESPACE__, 'PHPWEB_PATH_PHOTOS')];



include_once \Badoo\SoftMocks::rewrite('debug.php');

isset(\Badoo\SoftMocks::$func_mocks_by_name['isRobotDebug']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'isRobotDebug', []) : isRobotDebug();
isset(\Badoo\SoftMocks::$func_mocks_by_name['isCssDebug']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'isCssDebug', []) : isCssDebug();
if (isset(\Badoo\SoftMocks::$func_mocks_by_name['isCompressHTMLDebug']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'isCompressHTMLDebug', []) : isCompressHTMLDebug()) {isset(\Badoo\SoftMocks::$func_mocks_by_name['define']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'define', ['COMPRESS_HTML_DEBUG', true]) : define('COMPRESS_HTML_DEBUG', true);}

while (false) {echo "never!\n";}

while (false) {
    echo "never!\n";}


do {echo "always!\n";} while (false);

do {
    echo "always!\n";} while (false);


$array = [
    1,
    isset(\Badoo\SoftMocks::$func_mocks_by_name['replaceSomething']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'replaceSomething', ['string']) : replaceSomething('string'),
    3,
];
for ($i = 0; $i < (isset(\Badoo\SoftMocks::$func_mocks_by_name['count']) ? \Badoo\SoftMocks::callFunction('', 'count', [$array]) : \count($array)); $i++) {
    $value = $array[$i];}


foreach ($array as $key => $value) {
    echo "{$key}: {$value}\n";}


$switch = 5;

switch ($switch) {
    case 4:
        echo "switch 4\n";
        break;
    
    case 5:
    case 6:
        echo "switch 5|6\n";
        break;
    
    default:
        echo "switch default\n";}


function replaceSomething($string){
    
    
    
    return isset(\Badoo\SoftMocks::$func_mocks_by_name['str_replace']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'str_replace', ['something', 'somebody', $string]) : str_replace('something', 'somebody', $string);}


$function = static function () {
    return 1;};


trait SomeTrait{
    
    public function getSomeValue(){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeTrait::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return 1;}}



interface SomeInterface{
    
    public function getSomeValue();}


class SomeClass implements \Some\Name\Space\SomeInterface{
    
    use \Some\Name\Space\SomeTrait;
    
    const VALUE = 1;
    
    public $a = 1;
    
    public static function getValue(){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return isset(\Badoo\SoftMocks::$class_const_mocks_by_name['VALUE']) ? \Badoo\SoftMocks::getClassConst(self::class, 'VALUE', self::class) : self::VALUE;}
    
    
    public function method($string){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [$string];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return self::methodSelf($string);}
    
    
    protected static function methodSelf($string){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [$string];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return isset(\Badoo\SoftMocks::$func_mocks_by_name['replaceSomething']) ? \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'replaceSomething', [&$string]) : replaceSomething($string);}}



$some = new \Some\Name\Space\SomeClass();