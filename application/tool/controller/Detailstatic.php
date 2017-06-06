<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\Db;
use think\View;


/**
 * 详情页静态化
 * 执行详情页的静态化相关操作
 */
class Detailstatic extends Common
{

    //首先第一次入口
    public function index()
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        //获取  文章分类 还有 对应的pageinfo中的 所选择的A类关键词
        $menu_ids = $siteinfo['menu'];
        print_r(Db::name('SitePageinfo')->where(['site_id'=>$site_id])->field('menu_id,akeyword_id')->select());
        exit;
        $menu_typeid_arr = Menu::getTypeIdInfo($siteinfo['menu']);
        foreach ($menu_typeid_arr as $detail_key => $v) {
            foreach ($v as $type) {
                switch ($detail_key) {
                    case'article':
                        $this->articlestatic($type['id']);
                        break;
                    case'question':
                        $this->questionstatic($type['id']);
                        break;
                    case'scatteredarticle':
                        $this->scatteredarticlestatic($type['id']);
                        break;
                }
            }
        }
    }


    /**
     * 文章详情页面的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     */
    public function articlestatic($type_id, $key)
    {

        //  获取首页生成需要的资源
        //  关键词
        //  栏目url  展现以下已经在数据库
        //  文章 或者 问答
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
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
        //获取公共代码
        $commonjscode = Commontool::getCommonCode($siteinfo['public_code']);

        //head前后的代码
        $before_head = $siteinfo['before_header_jscode'];
        $after_head = $siteinfo['other_jscode'];
        //公司名称
        $com_name = $siteinfo['com_name'];
        $assign_data = compact('com_name', 'title', 'keyword', 'description', 'm_url', 'redirect_code', 'menu', 'before_head', 'after_head', 'chain_type', 'next_site', 'main_site', 'common_site', 'partnersite', 'commonjscode', 'article_list', 'question_list', 'scatteredarticle_list');
        file_put_contents('log/article.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        $content = (new View())->fetch('template/index.html',
            [
                'd' => $assign_data
            ]
        );
        file_put_contents('index.html', $content);
    }

}
