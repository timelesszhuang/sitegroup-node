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
use think\Config;
use think\Db;
use think\Exception;


class Filemanage extends Common
{

    //目录是相对于 public  使用 ROOT_PATH 需 手动追加 public/ 目录
    //亚索模板文件的路径
    static $zipTemplatePath = 'upload/ziptemplate';
    static $zipActivityPath = 'upload/zipactivity';

    //html模板文件要解压到的地方
    static $templateHtmlPath = 'template';
    //静态模板文件解压到的地方
    static $templateStaticPath = 'templatestatic';

    //活动html要解压到的地方
    static $activityHtmlPath = 'activity';


    //因为需要递归操作文件
    static $arr_files = [];

    //测试文件传递的路径
    //http://节点域名/index.php/Site/uploadTemplateFile


    /**
     * 文件上传程序
     * @return array
     */
    public function uploadFile()
    {
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        $this->checkOrigin();
        $type = request()->param('type');
        $id = request()->param('id');
        file_put_contents('a.txt', $id, FILE_APPEND);
        file_put_contents('a.txt', $type, FILE_APPEND);
        $site_id = Config::get('site.SITE_ID');
        $where = ['id' => $site_id];
        if ($type == 'template') {
            //模板文件相关操作
            $this->manageTemplate();
            //置下是不是 传递模板成功
            Db::name('site')->where($where)->update(['template_status' => '10']);
        } else if ($type == 'activity') {
            //活动相关操作
            $this->manageActivity();
            //置下是不是解压缩成功  首先需要判断下是不是已经传递过当前的活动 同步过的话 怎么处理
            $sync_info = Db::name('site')->where($where)->field('sync_id')->find();
            $sync_id_arr = [];
            if ($sync_info) {
                $sync_id_arr = array_filter(explode(',', $sync_info['sync_id']));
            }
            $sync_id_arr[] = $id;
            $unique_sync_id_arr = array_unique($sync_id_arr);
            Db::name('site')->where(['id' => Config::get('site.SITE_ID')])->update(['sync_id' => ',' . implode(',', $unique_sync_id_arr) . ',']);
        }
    }


    /**
     * 处理模板文件接收 以及解压缩操作
     * @access private
     * 说明下模板文件的命名以及上传的机制
     *
     * 模板文件命名：
     * 1 首先模板跟文件的命名为 /template/  下边 模板文件   ××.html
     *                                                 ××1.html
     *                            静态js css 图片文件   /templatestatic/ ××.css
     *                                                                 ××.js
     *                                                                 ××.img
     * 解压缩机制
     *
     * 1、节点服务器后台 触发模板同步 post 文件到 uploadFile ,file 为 文件 ，type：template 表示模板 activity 为 活动
     *
     * 2、模板 首先解压缩在 public/template下  ，以为解压缩之后会有 目录 所以解压缩之后目录为  /public/template/template/
     *
     * 3、把/public/template/template/ 下的文件以及文件夹 都复制到  /public/template/ 目录下
     *
     * 4、然后复制 /public/template/templatestatic/下的js css img等文件到/public/templatestatic/下
     *
     * 5、 删除/public/template/templatestatic/ 目录 ，删除/public/template/template目录。
     *
     * 页面静态化机制
     *
     * 模板文件的位置为 /public/template ,设置tp 文件的视图文件位置为 该目录。
     * 生成的静态文件放置在 /public 下,设置 index.html的优先级大于 index.php 设置 robots.txt 文件 不允许访问 router.php 跟 indx.php
     *
     * @todo 解压模板html文件到 /template 下 而不是 /template/文件名/××.html
     *       解压 静态文件到/templatestatic 下 而不是 /template/文件名/css 等信息
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
        $realTemplatePath = $zipTemplateFilePath . DIRECTORY_SEPARATOR . $file_savename;
        $realTemplateUnzipPath = ROOT_PATH . 'public/' . self::$templateHtmlPath;
        $realStaticPath = ROOT_PATH . 'public/' . self::$templateStaticPath;
        if ($this->unzipFile($realTemplatePath, $realTemplateUnzipPath)) {
            //因为 模板文件解压缩之后 会在/template 下 生成文件夹  所以需要把 文件夹中的 文件复制到上级目录中
            //复制 所有文件到上级目录中  然后删除 临时的模板解压文件
            self::copydir_recurse($realTemplateUnzipPath . DIRECTORY_SEPARATOR . 'template', $realTemplateUnzipPath, true);
            if (self::$arr_files) {
                self::copy_only_file($realTemplateUnzipPath);
            }
            self::$arr_files = [];
            //解压的/public/template/static中的 静态文件 复制到/public/static 文件夹中
            self::copydir_recurse($realTemplateUnzipPath . DIRECTORY_SEPARATOR . self::$templateStaticPath, $realStaticPath, true);
            if (self::$arr_files) {
                self::copy_only_file($realStaticPath);
            }
            //删除临时解压到的目录
            self::deldirs($realTemplateUnzipPath . DIRECTORY_SEPARATOR . 'template');
            self::deldirs($realTemplateUnzipPath . DIRECTORY_SEPARATOR . self::$templateStaticPath);
            $status = '文件解压缩成功';
        }
    }


    /**
     * 处理 活动信息
     * @access public
     * 接收 活动 zip 模板 然后 解压缩文件 到 public/activity/ 中
     */
    public function manageActivity()
    {
        $file = request()->file('file');
        $zipActivityFilePath = ROOT_PATH . 'public/' . self::$zipActivityPath;
        $info = $file->move($zipActivityFilePath);
        // 要解压到的位置
        $file_savename = $info->getSaveName();
        $pathinfo = pathinfo($file_savename);
        // 文件名
        $file_name = $pathinfo['filename'];
        $status = '文件解压缩失败';
        // 解压缩主题文件到指定的目录中
        $realActivityPath = $zipActivityFilePath . DIRECTORY_SEPARATOR . $file_savename;
        // 活动解压缩文件到的路径
        $realActivityUnzipPath = ROOT_PATH . 'public/' . self::$activityHtmlPath;
        if ($this->unzipFile($realActivityPath, $realActivityUnzipPath)) {
            //解压缩到的 位置为 upload/Activity 文件
            $status = '文件解压缩成功';
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
     * @return int
     */
    private static function copydir_recurse($source, $destination, $child, $directory = '')
    {
        if (!is_dir($source)) {
            echo("Error:the $source is not a direction!");
            return 0;
        }
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }
        $handle = dir($source);
        while ($entry = $handle->read()) {
            if (($entry !== ".") && ($entry !== "..")) {
                echo $source . '/' . $entry . '<br>';
                if (is_dir($source . DIRECTORY_SEPARATOR . $entry)) {
                    if ($child)
                        self::copydir_recurse($source . DIRECTORY_SEPARATOR . $entry, $destination . DIRECTORY_SEPARATOR . $entry, $child, $entry);
                } else {
                    $file_name = $entry;
                    if ($directory) {
                        $file_name = $directory . DIRECTORY_SEPARATOR . $entry;
                    }
                    self::$arr_files[$source . DIRECTORY_SEPARATOR . $entry] = $file_name;
                }
            }
        }
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
     * @param $aim_dir
     */
    private static function copy_only_file($aim_dir)
    {
        foreach (self::$arr_files as $key => $value) {
            copy($key, $aim_dir . DIRECTORY_SEPARATOR . $value);
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