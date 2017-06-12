<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-5-27
 * Time: 下午2:26
 */

/*ob_end_clean();
ob_start();
print_r(json_encode(['status' => "success", 'data' => '', 'msg' => "正在发送模板,请等待.."]));
echo 'dsdsds';
echo ob_get_level();
$size = ob_get_length();
// send headers to tell the browser to close the connection
header("Content-Length: $size");
header('Connection: close');
ob_end_flush();

ob_flush();
flush();

echo 112323;*/


ob_start();
echo 'hello';//此处并不会在页面中输出
$a = ob_get_level();
$b = ob_get_contents();//获得缓存结果,赋予变量
ob_clean();
ob_start();
echo 'world';//此处并不会在页面中输出
$c = ob_get_level();
$d = ob_get_contents();//获得缓存结果,赋予变量
ob_clean();
ob_start();
echo 'hi';//此处并不会在页面中输出
$e = ob_get_level();
$f = ob_get_contents();//获得缓存结果,赋予变量
ob_clean();

echo 'level:'.$a.',ouput:'.$b.'<br>';
echo 'level:'.$c.',ouput:'.$d.'<br>';
echo 'level:'.$e.',ouput:'.$f.'<br>';