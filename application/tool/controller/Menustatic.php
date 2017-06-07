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
     * 菜单首页
     * @access public
     */
    public static function menuIndex($menu_id)
    {
        //第一次访问的时候  因为需要获取一些数据
        $menu_info = \app\index\model\Menu::get($menu_id);
        list($com_name, $title, $keyword, $description,
            $m_url, $redirect_code, $menu, $before_head,
            $after_head, $chain_type, $next_site,
            $main_site, $partnersite, $commonjscode,
            $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id);
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $site_id, $site_name, $node_id);
        //菜单 页面的TDK
        Commontool::getMenuPageTDK($keyword_info, $menu_info->generate_name, $menu_info->name, $site_id, $site_name, $node_id, $menu_id, $menu_info->name);
    }


    /**
     * 栏目页面的静态化
     * @access public
     */
    public static function envIndex()
    {
        //  获取首页生成需要的资源
        //  关键词
        //  栏目url  展现以下已经在数据库
        //  文章 或者 问答
        $siteinfo = Site::getSiteInfo();


    }


}
