<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\View;

/**
 * 首页静态化
 * 执行首页静态化相关操作
 */
class Indexstatic extends Common
{

    /**
     * 首恶静态化
     * @access public
     */
    public function index()
    {
        // 判断模板是否存在
        if (!$this->fileExists($this->indextemplate)) {
            return;
        }
        $content = $this->indexstaticdata();
        // 使用该命名是为了 防止请求不经过 index.php 有些服务器的index 优先级 index.html 大于index.php
        if (file_put_contents('indexmenu/index.html', $content) === 'false') {
            return false;
        }
        $this->urlsCache([$this->siteurl . '/index.html']);
        return true;
    }


    /**
     * 获取渲染之后的页面的字符串 有时需要静态化 有时需要直接返回给浏览器
     * @access public
     */
    public function indexstaticdata()
    {
        //  获取首页生成需要的资源
        //  关键词
        //  栏目url  展现以下已经在数据库
        //  文章 或者 问答
        $assign_data = Commontool::getEssentialElement('index');
        //file_put_contents('log/index.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //还需要 存储在数据库中 相关数据
        //页面中还需要填写隐藏的 表单 node_id site_id
        $data = [
            'd' => $assign_data
        ];
        $content = Common::Debug((new View())->fetch($this->indextemplate,
            $data
        ), $data);
        return $content;
    }

}
