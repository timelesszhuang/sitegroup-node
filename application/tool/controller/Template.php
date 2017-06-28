<?php

namespace app\tool\controller;

use app\common\controller\Common;


/**
 * 配置文件中的页面静态化
 * 执行首页静态化相关操作
 */
class Template extends Common
{


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
        //模板相关操作
        $path = ROOT_PATH . 'public/template/';
        //获取文件列表
        $fileArray = [];
        if (false != ($handle = opendir($path))) {
            while (false !== ($file = readdir($handle))) {
                //去掉"“.”、“..”以及带“.xxx”后缀的文件
                if ($file != "." && $file != ".." && $file != ".env" && $file != 'README.md' && strpos($file, ".html")) {
                    $filepath = $path . $file;
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
            $data = ['status' => 'success', 'msg' => '模板文件列表获取成功。', 'filelist' => $fileArray];
        } else {
            $data = ['status' => 'failed', 'msg' => '模板未传递或没有获取到模板文件。', 'filelist' => $fileArray,];
        }
//        print_r(json_encode($data));
        return json_encode($data);
    }


    /**
     * 模板读操作
     * @access public
     */
    public function templateread()
    {
        //模板读取

    }

    /**
     * 模板写操作
     * @access public
     */
    public function templatewrite()
    {
        //模板写操作

    }
}
