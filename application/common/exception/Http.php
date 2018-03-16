<?php
/**
 * 访问某模块不存在的时候执行错误处理
 * User: timeless
 * Date: 18-3-15
 * Time: 上午10:37
 */

namespace app\common\exception;


use app\index\controller\Detailenter;
use Exception;
use think\exception\Handle;
use think\exception\HttpException;
use think\Request;

class Http extends Handle
{

    /**
     * 自定义异常处理
     * @access public
     */
    public function render(Exception $e)
    {
        if ($e instanceof HttpException) {
            if (stristr($e->getMessage(), "module not exists:")) {
                $pathinfo = pathinfo(Request::instance()->url());
                if (array_key_exists('extension', $pathinfo) && $pathinfo['extension']) {
                    //说明后缀是html 去模板文件中请求下文件
                    //需要 判断下是不是 孩子站点的请求
                    $filename = $pathinfo['basename'];
                    // 把请求调取到其他控制器中
                    (new Detailenter())->detailMenu($filename);
                }
            }
        }
        //可以在此交由系统处理
        return parent::render($e);
    }

}