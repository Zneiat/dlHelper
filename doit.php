<?php
// 设置
error_reporting(E_ALL^E_NOTICE);
date_default_timezone_set('Asia/Shanghai');
define("APP_ROOT", dirname(__FILE__));

// 加载
function __autoload($className){
    if (class_exists($className,false)) {
        // 如果已引入该类
        return;
    }
    $classFilePath = APP_ROOT.'/'.str_replace('\\', '/', $className).'.php';
    if (!file_exists($classFilePath) || !is_readable($classFilePath)) {
        // 文件不存在，不可读
        return;
    }
    require($classFilePath);
}

// 运行
$config = require(APP_ROOT . '/dlHelper/config.php');
$app = new dlHelper\app($config);
$app->run();