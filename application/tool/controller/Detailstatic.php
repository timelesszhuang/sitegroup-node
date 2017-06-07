<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\model\ArticleSyncCount;
use app\index\model\Articletype;
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
        //获取 site页面 中 menu 指向的 a_keyword_id
        $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');
        $menu_typeid_arr = Menu::getTypeIdInfo($siteinfo['menu']);

        foreach ($menu_typeid_arr as $detail_key => $v) {
            foreach ($v as $type) {
                switch ($detail_key) {
                    case'article':
                        $this->articlestatic($site_id,$site_name,$node_id,$type['id'], $menu_akeyword_id_arr[$type['menu_id']]);
                        break;
//                    case'question':
//                        $this->questionstatic($type['id'], $menu_akeyword_id_arr[$type['menu_id']]);
//                        break;
//                    case'scatteredarticle':
//                        $this->scatteredarticlestatic($type['id'], $menu_akeyword_id_arr[$type['menu_id']]);
//                        break;
                }
            }
        }
    }


    /**
     * 文章详情页面的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     * @param $type_id 文章的分类id
     * @param $a_keyword_id 栏目所对应的a类 关键词
     */
    public function articlestatic($site_id,$site_name,$node_id,$type_id, $a_keyword_id)
    {
        //  获取详情 页生成需要的资源  首先需要比对下当前页面是不是已经静态化了
        //  关键词
        $type_name="article";
        $where=[
            'type_id'=>$type_id,
            'type_name'=>$type_name,
            "node_id"=>$node_id,
            "site_id"=>$site_id
        ];
        dump($site_id);die;

        $articleCount=ArticleSyncCount::where($where)->find();
        dump($articleCount);die;
//        \app\index\model\Article::where(["articletype_id"=>$type_id])->chunk(50,function(){
//
//
//        });

        list($com_name, $title, $keyword, $description,
            $m_url, $redirect_code, $menu, $before_head,
            $after_head, $chain_type, $next_site,
            $main_site, $partnersite, $commonjscode,
            $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('detail',$title,$content,$a_keyword_id);


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
