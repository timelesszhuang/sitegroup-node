<?php

namespace app\tool\controller;

use app\common\controller\Common;


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
        //  获取首页生成需要的资源
        //  关键词
        //  栏目url  展现以下已经在数据库
        //  文章 或者 问答
        $keyword_info = Keyword::getKeywordInfo();
        print_r($keyword_info);

        $menu = Menu::getMenuInfo();
        $Envmenu = Menu::getEnvMenuInfo();



        $view = new View();
        $content = $view->fetch('template/index.html');
        echo $content;
        file_put_contents('a.html', $content);

    }


}
