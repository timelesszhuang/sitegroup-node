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
        //  获取首页生成需要的资源
        //  关键词
        //  栏目url  展现以下已经在数据库
        //  文章 或者 问答
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id=$siteinfo['node_id'];
//      print_r($siteinfo);
        $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $site_id, $site_name, $node_id);
//      print_r($keyword_info);
        $menu = Menu::getMergedMenu($siteinfo['menu'], $site_id, $site_name, $node_id);
//      print_r($menu);
        //获取站点的类型 手机站的域名 手机站点的跳转链接
        list($m_url, $redirect_code) = Commontool::getMobileSiteInfo();
        //然后获取 TDK 等数据  首先到数据库
        list($title, $keyword, $description) = Commontool::getIndexPageTDK($keyword_info, $site_id, $node_id, $siteinfo['com_name']);
        //获取首页中  会用到的 文章列表 问题列表 零散段落
        //配置的菜单信息  用于获取 文章的列表
        //$info = $menu_info;
        $type_id_arr = Menu::getTypeIdInfo($siteinfo['menu']);
        //获取十条　文章　问答　断句
        $article_list = [];
        if (array_key_exists('article', $type_id_arr)) {
            $key = array_rand($type_id_arr['article']);
            $article_id = $type_id_arr['article'][$key]['id'];
            $article_list = Commontool::getArticleList($article_id, $site_id);
        }
        $question_list = [];
        if (array_key_exists('question', $type_id_arr)) {
            $key = array_rand($type_id_arr['question']);
            $question_id = $type_id_arr['question'][$key]['id'];
            $question_list = Commontool::getQuestionList($question_id, $site_id);
        }
        $scatteredarticle_list = [];
        if (array_key_exists('scatteredarticle', $type_id_arr)) {
            $key = array_rand($type_id_arr['scatteredarticle']);
            $scatteredarticle_id = $type_id_arr['scatteredarticle'][$key]['id'];
            $scatteredarticle_list = Commontool::getScatteredArticleList($scatteredarticle_id, $site_id);
        }
        //获取友链
        $partnersite = Commontool::getPatternLink($siteinfo['link_id']);

        //链轮的类型
        $chain_type = '';
        //该站点需要链接到的站点
        $next_site = [];
        //主站是哪个
        $main_site = [];
        $is_mainsite = $siteinfo['main_site'];
        if ($is_mainsite == '10') {
            //表示不是主站
            //站点类型 用于取出主站 以及链轮类型 来
            $site_type_id = $siteinfo['site_type'];
            list($chain_type, $next_site, $main_site) = Site::getLinkInfo($site_type_id, $site_id, $site_name, $node_id);
        }

        //获取代码　
        //获取公共代码

    }


}