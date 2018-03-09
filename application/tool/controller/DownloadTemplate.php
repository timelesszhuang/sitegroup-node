<?php
/**
 * 下载模板文件相关
 * User: timeless
 * Date: 18-3-9
 * Time: 上午10:52
 */

namespace app\tool\controller;


use app\common\controller\Coding;
use app\common\controller\Common;
use think\Request;


class DownloadTemplate extends Common
{

    /**
     * 下载模板文件
     * @access public
     */
    public function downloadtemplatefile()
    {
        $filetoken = Request::instance()->get('filetoken');
        if (!$filetoken) {
            exit(json_encode(['status' => 'failed', 'msg' => '参数异常']));
        }
        $filerelativename = Coding::tiriDecode($filetoken);
        $filepath = ROOT_PATH . 'public' . $filerelativename;
        $pathinfo = pathinfo($filepath);
        $file_name = $pathinfo['basename'];
        header("Content-type:text/html;charset=utf-8");
        //用以解决中文不能显示出来的问题
        $file_name = iconv("utf-8", "gb2312", $file_name);
        //首先要判断给定的文件存在与否
        if (!file_exists($filepath)) {
            exit(json_encode(['status' => 'failed', 'msg' => '参数异常']));
            return;
        }
        $fp = fopen($filepath, "r");
        $filesize = filesize($filepath);
        //下载文件需要用到的头
        Header("Content-type: application/octet-stream");
        Header("Accept-Ranges: bytes");
        Header("Accept-Length:" . $filesize);
        Header("Content-Disposition: attachment; filename=" . $file_name);
        $buffer = 1024;
        $file_count = 0;
        //向浏览器返回数据
        while (!feof($fp) && $file_count < $filesize) {
            $file_con = fread($fp, $buffer);
            $file_count += $buffer;
            echo $file_con;
        }
        fclose($fp);
    }
}