<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\View;


/**
 * 栏目的静态化
 * 执行首页静态化相关操作
 */
class Menustatic extends Common
{

    /**
     * 栏目页面的静态化
     * @access public
     */
    public function index()
    {
        //  获取首页生成需要的资源
        //  关键词
        //  栏目url  展现以下已经在数据库
        //  文章 或者 问答
        $siteinfo = Site::getSiteInfo();
//      print_r($siteinfo);
        $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $siteinfo['id'], $siteinfo['site_name'], $siteinfo['node_id']);
//      print_r($keyword_info);
        $menu = Menu::getMergedMenu($siteinfo['menu'], $siteinfo['id'], $siteinfo['site_name'], $siteinfo['node_id']);
//      print_r($menu);
        //获取站点的类型 手机站的域名 手机站点的跳转链接
        list($m_url, $redirect_code) = Commontool::getMobileSiteInfo();
        //然后获取 TDK 等数据  首先到数据库
        list($title, $keyword, $description) = Commontool::getMenuPageTDK($keyword_info, 'contact', $siteinfo['id'], $siteinfo['node_id'], '联系我们');

        //获取栏目页面的TDK 返回值的话如果是空的  说明关键词有问题
        //var_dump();

        //

        //页面中还需要填写隐藏的 表单 node_id site_id
        $content = (new View())->fetch('template/index.html');
        echo $content;
        file_put_contents('a.html', $content);
    }


}
