<?php
/**
 * 访问某模块不存在的时候执行错误处理
 * User: timeless
 * Date: 18-3-15
 * Time: 上午10:37
 */

namespace app\common\exception;


use Exception;
use think\exception\Handle;
use think\exception\HttpException;
use think\Request;

class Http extends Handle
{

    public function render(Exception $e)
    {
        echo '<pre>';
        echo Request::instance()->path();
        if ($e instanceof HttpException) {
//            print_r($e);
            if (stristr($e->getMessage(), "module not exists:")) {
                //return Response::create("<script>window.location.href='http://{$_SERVER[ 'HTTP_HOST' ]}';</script>", "html")->send();
                // 如果提示不存在的话的操作方式
//                print_r($e);
            }
        }
        exit;
        //可以在此交由系统处理
        return parent::render($e);
    }

}