<?php
/**
 * Created by PhpStorm.
 * 文件相关操作 模板同步 活动同步等信息 文件复制 删除等相关操作
 * User: timeless
 * Date: 17-5-17
 * Time: 下午4:40
 */

namespace app\tool\controller;


use app\common\controller\Common;


class Filemanage extends Common
{

    //目录是相对于 public  使用 ROOT_PATH 需 手动追加 public/ 目录
    //亚索模板文件的路径
    static $zipTemplatePath = 'upload/ziptemplate';
    static $zipActivityPath = 'upload/zipactivity';

    //html模板文件要解压到的地方
    static $templateHtmlPath = 'template/';
    //静态模板文件解压到的地方
    static $templateStaticPath = 'static/';
    //活动要解压到的地方
    static $activityPath = '/activity';


    /**
     * 文件上传程序　
     * @return array
     */
    public function uploadFile()
    {
        $this->checkOrigin();
        $type = request()->param('type');
        if ($type == 'template') {
            //模板文件相关操作
            $this->manageTemplate();
        } else if ($type == 'activity') {
            //活动相关操作
            $this->manageActivity();
        }
    }


    /**
     * 处理模板文件接收 以及解压缩操作
     * @access private
     */
    private function manageTemplate()
    {
        $file = request()->file('file');
        $zipTemplateFilePath = ROOT_PATH . 'public/' . self::$zipTemplatePath;
        $info = $file->move($zipTemplateFilePath);
        // 要解压到的位置
        $file_savename = $info->getSaveName();
        $pathinfo = pathinfo($file_savename);
        // 文件名
        $file_name = $pathinfo['filename'];

        $status = '文件解压缩失败';
        //解压缩主题文件到指定的目录中
        $realTemplatePath = $zipTemplateFilePath . '/' . $file_savename;
        $realTemplateUnzipPath = ROOT_PATH . 'public/' . self::$templateHtmlPath;
        if ($this->unzipFile($realTemplatePath, $realTemplateUnzipPath)) {
            //然后修改权限
            //解压的/public/template/static中的 静态文件 复制到/public/static 文件夹中
            $status = '文件解压缩成功';
        }
        if ($info) {
            return $this->resultArray('上传成功', '', ['code_path' => $file_savename, 'status' => $status]);
        } else {
            // 上传失败获取错误信息
            return $this->resultArray('上传失败', 'failed', $info->getError());
        }
    }


    /**
     * 批量拷贝目录（包括子目录下所有文件）
     * 实例
     * $arr_file=copydir_recurse('D:\wamp\tmp','E:\dir',1);
     *
     * copy a direction's all files to another direction
     * 用法：
     * copydir_recurse("feiy","feiy2",1):拷贝feiy下的文件到 feiy2,包括子
     * 目录
     * copydir_recurse("feiy","feiy2",0):拷贝feiy下的文件到 feiy2,不包括
     * 子目录
     * @param $source 源目录名
     * @param $destination 目的目录名
     * @param $child 复制时，是不是包含的子目录
     * @return array
     */
    private static function copydir_recurse($source, $destination, $child)
    {
        $arr_file = [];
        if (!is_dir($source)) {
            echo("Error:the $source is not a direction!");
            return 0;
        }
        if (!is_dir($destination)) {
            mkdir($destination, 0777);
        }
        $handle = dir($source);
        while ($entry = $handle->read()) {
            if (($entry !== ".") && ($entry !== "..")) {
                if (is_dir($source . "/" . $entry)) {
                    if ($child)
                        self::copydir_recurse($source . "/" . $entry, $destination .
                            "/" . $entry, $child);
                    self::deldirs($destination . "/" . $entry); //删除空文件夹
                } else {
                    $arr_file[$source . "/" . $entry] = $entry;
                }
            }
        }
        return $arr_file;
    }


    /**
     *批量拷贝目录（包括子目录）下所有文件到一个文件夹下;
     *
     *用法：
     * copy_only_file($arr_file,'E:\dir')
     * 参数说明：
     * $arr_file这个数组是从 copydir_recurse中得到的;
     * $file_arr:文件数组，键名为包含原文件夹下的所有文件路经，键值为目录文件名;
     * $aim_dir:目标目录名
     * @param $arr_file
     * @param $aim_dir
     */
    private static function copy_only_file($arr_file, $aim_dir)
    {
        foreach (self::$arr_file as $key => $value) {
            if (is_dir($value))
                self::deldirs($value);//删除空目录
            copy($key, $aim_dir . '\ ' . $value);
        }
    }


    /**
     * 递归删除文件夹
     * @param $dir
     * @return bool
     */
    private static function deldirs($dir)
    {
        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if ($file !== "." && $file !== "..") {
                $fullpath = $dir . "/" . $file;
                if (!is_dir($fullpath)) {
                    unlink($fullpath);
                } else {
                    self::deldirs($fullpath);
                }
            }
        }
        closedir($dh);
        if (rmdir($dir)) {
            return true;
        } else {
            return false;
        }
    }


}