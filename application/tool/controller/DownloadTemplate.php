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
            exit(json_encode(['status' => 'failed', '参数异常']));
        }
        $filename = Coding::tiriDecode($filetoken);
        $filename = ROOT_PATH . 'public' . $filename;
        print_r($filename);
        exit;
        $file = fopen($filename, "r");
        header("Content-Type: application/octet-stream");
        header("Accept-Ranges: bytes");
        header("Accept-Length: " . filesize($filename));
        header("Content-Disposition: attachment; filename=文件名称");
        echo fread($file, filesize($filename));
        fclose($file);
    }
}