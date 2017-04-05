<?php
set_time_limit(0);
error_reporting(E_ALL);
define('EXT_CLASS', '.php');
if(! ini_get('date.timezone')) date_default_timezone_set('Asia/Shanghai');
define("REAL_ROOT_PATH", dirname(__FILE__) . '/');
define("COMMON_PATH", REAL_ROOT_PATH . 'common/');
define("COMMON_CORE_PATH", COMMON_PATH . 'core/');
define("COMMON_CORE_CORE_PATH", COMMON_CORE_PATH . 'core/');
define("COMMON_LIBS_PATH", COMMON_PATH . 'libs/');
define("COMMON_LIBS_CRAWL_PATH", COMMON_LIBS_PATH . 'crawl/');
define("CONTROLLERS_PATH", REAL_ROOT_PATH . 'controllers/');
set_include_path(
    get_include_path()		.PATH_SEPARATOR
    .COMMON_LIBS_PATH		.PATH_SEPARATOR
    .CONTROLLERS_PATH		.PATH_SEPARATOR
);
require_once (COMMON_CORE_CORE_PATH.'AutoLoadHander.php');
if (isset($_REQUEST['m']) && isset($_REQUEST['a'])) {
    $controller = $_REQUEST['m'].'Controller';
    unset($_REQUEST['m']);
    $action = $_REQUEST['a'].'Action';
    unset($_REQUEST['a']);
    try {
        @include_once ($controller.EXT_CLASS);
        $returnRes = (new $controller())->$action($_REQUEST);
        echo json_encode(['success'=>true,'result'=>$returnRes]);
    } catch (Exception $ex){
        echo json_encode(['success'=>false,'message'=>$ex->getMessage()]);
    }
}
else
{
    echo json_encode(['success'=>false,'message'=>'参数错误!']);
}
