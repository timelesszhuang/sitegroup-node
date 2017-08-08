<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\Request;


/**
 * 配置文件中的页面静态化
 * 执行首页静态化相关操作
 */
class Template extends Common
{
    public $templatepath = ROOT_PATH;

    /**
     * Converts bytes into human readable file size.
     *
     * @param string $bytes
     * @return string human readable file size (2,87 Мб)
     * @author Mogilev Arseny
     */
    function FileSizeConvert($bytes)
    {
        $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );
        foreach ($arBytes as $arItem) {
            if ($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                $result = strval(round($result, 2)) . " " . $arItem["UNIT"];
                break;
            }
        }
        return $result;
    }

    /**
     * 模板 相关操作
     * @access public
     */
    public function templatelist()
    {
        //获取文件列表
        $fileArray = [];
        if (false != ($handle = opendir($this->templatepath))) {
            while (false !== ($file = readdir($handle))) {
                //去掉"“.”、“..”以及带“.xxx”后缀的文件
                if ($file != "." && $file != ".." && $file != ".env" && $file != 'README.md' && strpos($file, ".html")) {
                    $filepath = $this->templatepath . $file;
                    $formatsize = $this->FileSizeConvert(filesize($filepath));
                    $fileArray[] = [
                        'name' => $file,
                        //字节数
                        'size' => $formatsize,
                        //文件的修改时间
                        'filemtime' => date('Y-m-d H:i:s', filemtime($filepath))
                    ];
                }
            }
            //关闭句柄
            closedir($handle);
        }
        if ($fileArray) {
            $data = ['status' => '', 'msg' => '模板文件列表获取成功。', 'filelist' => $fileArray];
        } else {
            $data = ['status' => 'failed', 'msg' => '模板未传递或没有获取到模板文件。', 'filelist' => $fileArray,];
        }
        return json_encode($data);
    }


    /**
     * 模板读操作
     * 需要传递 site_id 跟 filename 不带 .html(只需要filename)
     * @access public
     */
    public function templateread()
    {
        //模板读取
        $filename = Request::instance()->param('filename');
        $file_path = $this->templatepath . $filename . '.html';
        if (!file_exists($file_path)) {
            return json_encode(['status' => 'failed', 'msg' => '模板文件不存在。', 'filename' => $filename, 'content' => '']);
        }
        //读取文件内容
        $content = file_get_contents($file_path);
        if ($content) {
            return json_encode(['status' => '', 'msg' => '获取模板内容成功。', 'filename' => $filename, 'content' => $content]);
        } else {
            return json_encode(['status' => 'failed', 'msg' => '获取模板内容失败，请稍后重试。', 'filename' => $filename, 'content' => '']);
        }
    }

    /**
     * 模板更新操作
     * filename 模板名
     * content
     * @access public
     */
    public function templateupdate()
    {
        //模板写操作
        $filename = Request::instance()->param('filename');
        $content = Request::instance()->param('content');
        $file_path = $this->templatepath . $filename . '.html';
        if (!file_exists($file_path)) {
            return json_encode(['status' => 'failed', 'msg' => '模板文件不存在,请确定文件存在。']);
        }
        if ($content) {
            file_put_contents($file_path, $content);
            return json_encode(['status' => '', 'msg' => '修改模板内容成功。']);
        } else {
            return json_encode(['status' => 'failed', 'msg' => '修改模板内容失败，请填写模板内容。']);
        }
    }

    /**
     * 模板添加操作
     * filename 模板文件名
     * content 要填充的内容
     * @access public
     */
    public function templateadd()
    {
        //模板写操作
        $filename = Request::instance()->param('filename');
        $content = Request::instance()->param('content');
        $file_path = $this->templatepath . $filename . '.html';
        if (file_exists($file_path)) {
            return json_encode(['status' => 'failed', 'msg' => '模板文件已经存在，请更换模板名。']);
        }
        if ($content) {
            file_put_contents($file_path, $content);
            return json_encode(['status' => '', 'msg' => '添加模板成功。']);
        } else {
            return json_encode(['status' => 'failed', 'msg' => '添加模板失败，请填写模板内容。']);
        }
    }

}
