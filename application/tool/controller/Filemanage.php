<?php
/**
 * Created by PhpStorm.
 * 文件相关操作 模板同步 活动同步等信息
 * User: timeless
 * Date: 17-5-17
 * Time: 下午4:40
 */

namespace app\tool\controller;


use app\common\controller\Common;


class Filemanage extends Common
{

    //目录是相对于 public  使用 ROOT_PATH 需 手动追加 public/ 目录
    static $templatePath = 'upload/demo';

    static $activityPath = '';


    /**
     * 文件上传程序　
     * @return array
     */
    public function index()
    {
        $file = request()->file('file');
        $info = $file->move(ROOT_PATH . 'public/' . self::$templatePath);
        $file_savename = $info->getSaveName();
        $pathinfo = pathinfo($file_savename);
        if ($info) {
            return $this->resultArray('上传成功', '', $file_savename);
        } else {
            // 上传失败获取错误信息
            return $this->resultArray('上传失败', 'failed', $info->getError());
        }
    }

}