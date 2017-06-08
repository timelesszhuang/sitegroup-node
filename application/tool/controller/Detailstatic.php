<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\model\ArticleSyncCount;
use app\index\model\Articletype;
use app\index\model\ScatteredArticle;
use app\index\model\Question;
use app\index\model\ScatteredTitle;
use think\Db;
use think\View;


/**
 * 详情页静态化
 * 执行详情页的静态化相关操作
 */
class Detailstatic extends Common
{

    /**
     * 首先第一次入口
     * @access public
     */
    public function index()
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        //获取  文章分类 还有 对应的pageinfo中的 所选择的A类关键词
        //获取 site页面 中 menu 指向的 a_keyword_id
        // 从数据库中 获取的页面的a_keyword_id 信息 可能有些菜单 还没有存储到数据库中 如果是第一次请求的话
        $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');

        $menu_typeid_arr = Menu::getTypeIdInfo($siteinfo['menu']);
        foreach ($menu_typeid_arr as $detail_key => $v) {
            foreach ($v as $type) {
                if (!array_key_exists($type['menu_id'], $menu_akeyword_id_arr)) {
                    //请求一下 该位置 可以把该菜单的 TDK 还有 相关 a_keyword_id  等信息存储到数据库中
                    Menustatic::menuIndex($type['menu_id']);
                    $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');
                }
                $a_keyword_id = $menu_akeyword_id_arr[$type['menu_id']];
                switch ($detail_key) {
//                    case'article':
//                        $this->articlestatic($site_id, $site_name, $node_id, $type['id'], $a_keyword_id);
//                        break;
//                    case'question':
//                        $this->questionstatic($site_id, $site_name, $node_id,$type['id'], $a_keyword_id);
//                        break;
                    case'scatteredarticle':
                        $this->scatteredarticlestatic($site_id, $site_name, $node_id, $type['id'], $menu_akeyword_id_arr[$type['menu_id']]);
                        break;

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
    public function articlestatic($site_id, $site_name, $node_id, $type_id, $a_keyword_id)
    {
        //  获取详情 页生成需要的资源  首先需要比对下当前页面是不是已经静态化了
        //  关键词
        //当前分类名称
        $type_name = "article";
        $where = [
            'type_id' => $type_id,
            'type_name' => $type_name,
            "node_id" => $node_id,
            "site_id" => $site_id
        ];
        $limit = 0;
        $articleCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($articleCount->count) && $articleCount->count > 0) {
            $limit = $articleCount->count;
        } else {
            $article_temp = new ArticleSyncCount();
        }
        $count = \app\index\model\Article::where(["id"=>["gt",$limit],"articletype_id" => $type_id, "node_id" => $node_id])->count();
        $page = 50;
        //需要循环的页数
        $step=ceil($count/$page);
        //当前的页数
        $need_step=ceil($limit/$page);
        for($i=$need_step;$i<=$step;$i++){
            $article_data = \app\index\model\Article::where(["articletype_id" => $type_id, "node_id" => $node_id])->order("id", "asc")->limit($i, $page)->select();
            foreach ($article_data as $item) {
                $temp_content = mb_substr(strip_tags($item->content), 0, 200);
                list($com_name, $title, $keyword, $description,
                    $m_url, $redirect_code, $menu, $before_head,
                    $after_head, $chain_type, $next_site,
                    $main_site, $partnersite, $commonjscode,
                    $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('detail', $item->title, $temp_content, $a_keyword_id);
                $assign_data = compact('com_name', 'title', 'keyword', 'description', 'm_url', 'redirect_code', 'menu', 'before_head', 'after_head', 'chain_type', 'next_site', 'main_site', 'common_site', 'partnersite', 'commonjscode', 'article_list', 'question_list', 'scatteredarticle_list');
//                    file_put_contents('log/article.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
                //页面中还需要填写隐藏的 表单 node_id site_id
                //获取上一篇和下一篇
                $pre_article = \app\index\model\Article::where(["id" => ["lt", $item["id"]]])->find();
                $next_article = \app\index\model\Article::where(["id" => ["gt", $item["id"]]])->find();
                $content = (new View())->fetch('template/article_make.html',
                    [
                        'd' => $assign_data,
                        'article' => $item,
                        'pre_article' => $pre_article,
                        'next_article' => $next_article
                    ]
                );
                $make_web = file_put_contents('article/article' . $item["id"] . '.html', $content);
                //开始同步数据库
                if ($make_web) {
                    $articleCountModel = ArticleSyncCount::where($where)->find();
                    if (is_null($articleCountModel)) {
                        $article_temp->count = $item->id;
                        $article_temp->type_id = $type_id;
                        $article_temp->type_name = $type_name;
                        $article_temp->node_id = $node_id;
                        $article_temp->site_id = $site_id;
                        $article_temp->site_name = $site_name;
                        $article_temp->save();
                    } else {
                        $articleCountModel->count = $item->id;
                        $articleCountModel->save();
                    }
                }
            }

        }

    }




    /**
     * 零散文章的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     * @param $type_id 文章的分类id
     * @param $a_keyword_id 栏目所对应的a类 关键词
     */
    public function scatteredarticlestatic($site_id, $site_name, $node_id, $type_id, $a_keyword_id)
    {
        //  获取详情 页生成需要的资源  首先需要比对下当前页面是不是已经静态化了
        //  关键词
        $type_name = "scatteredarticle";
        $where = [
            'type_id' => $type_id,
            'type_name' => $type_name,
            "node_id" => $node_id,
            "site_id" => $site_id
        ];
        $limit = 0;
        $articleCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($articleCount->count) && $articleCount->count > 0) {
            $limit = $articleCount->count;
        } else {
            $article_temp = new ArticleSyncCount();
        }
        $scatTitleArray = (new ScatteredTitle())->where(["articletype_id" => $type_id])->select();
        foreach ($scatTitleArray as $item) {
            $scatArticleArray = Db::name('ScatteredArticle')->where(["id" => ["in", $item->article_ids]])->column('content_paragraph');
            $item['content'] = implode('<br/>', $scatArticleArray);
            $temp_content = mb_substr(strip_tags($item['content']), 0, 200);
            list($com_name, $title, $keyword, $description,
                $m_url, $redirect_code, $menu, $before_head,
                $after_head, $chain_type, $next_site,
                $main_site, $partnersite, $commonjscode,
                $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('detail', $item->title, $temp_content, $a_keyword_id);
            $assign_data = compact('com_name', 'title', 'keyword', 'description', 'm_url', 'redirect_code', 'menu', 'before_head', 'after_head', 'chain_type', 'next_site', 'main_site', 'common_site', 'partnersite', 'commonjscode', 'article_list', 'question_list', 'scatteredarticle_list');
//          file_put_contents('log/article.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //页面中还需要填写隐藏的 表单 node_id site_id
            //获取上一篇和下一篇
            $pre_article = \app\index\model\ScatteredTitle::where(["id" => ["lt", $item["id"]]])->find();

            $next_article = \app\index\model\ScatteredTitle::where(["id" => ["gt", $item["id"]]])->find();

            $content = (new View())->fetch('template/newslist.html',
                [
                    'd' => $assign_data,
                    'scatteredarticle' => $item,
                    'pre_article' => $pre_article,
                    'next_article' => $next_article
                ]
            );
            $make_web = file_put_contents('newslist/newslist' . $item["id"] . '.html', $content);
            //开始同步数据库
           if ($make_web) {
                $articleCountModel = ArticleSyncCount::where($where)->find();
                if (is_null($articleCountModel)) {
                    $article_temp->count = $item->id;
                    $article_temp->type_id = $type_id;
                    $article_temp->type_name = $type_name;
                    $article_temp->node_id = $node_id;
                    $article_temp->site_id = $site_id;
                    $article_temp->site_name = $site_name;
                    $article_temp->save();
                } else {
                    $articleCountModel->count = $item->id;
                    $articleCountModel->save();
                }
            }


        }


    }

    /**
     * 问答
     * @param $site_id
     * @param $site_name
     * @param $node_id
     * @param $type_id
     * @param $a_keyword_id
     */
    public function questionstatic($site_id, $site_name, $node_id, $type_id, $a_keyword_id)
    {
        //  获取详情 页生成需要的资源  首先需要比对下当前页面是不是已经静态化了
        //  关键词
        //当前分类名称
        $type_name = "question";
        $where = [
            'type_id' => $type_id,
            'type_name' => $type_name,
            "node_id" => $node_id,
            "site_id" => $site_id
        ];
        $limit = 0;
        $articleCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($articleCount->count) && $articleCount->count > 0) {
            $limit = $articleCount->count;
        } else {
            $article_temp = new ArticleSyncCount();
        }
        $count = \app\index\model\Question::where(["id"=>["gt",$limit],"type_id" => $type_id, "node_id" => $node_id])->count();
        $page = 50;
        //需要循环的页数
        $step=ceil($count/$page);
        //当前的页数
        $need_step=ceil($limit/$page);
        for($i=$need_step;$i<=$step;$i++){
            $question_data = \app\index\model\Question::where(["type_id" => $type_id, "node_id" => $node_id])->order("id", "asc")->limit($i, $count)->select();
            foreach ($question_data as $item) {
                $temp_content = mb_substr(strip_tags($item->content_paragraph), 0, 200);
                list($com_name, $title, $keyword, $description,
                    $m_url, $redirect_code, $menu, $before_head,
                    $after_head, $chain_type, $next_site,
                    $main_site, $partnersite, $commonjscode,
                    $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('detail', $item->question, $temp_content, $a_keyword_id);
                $assign_data = compact('com_name', 'title', 'keyword', 'description', 'm_url', 'redirect_code', 'menu', 'before_head', 'after_head', 'chain_type', 'next_site', 'main_site', 'common_site', 'partnersite', 'commonjscode', 'article_list', 'question_list', 'scatteredarticle_list');
//                    file_put_contents('log/article.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
                //页面中还需要填写隐藏的 表单 node_id site_id
                //获取上一篇和下一篇
                $pre_question = \app\index\model\Question::where(["id" => ["lt", $item->id]])->find();
                $next_question = \app\index\model\Question::where(["id" => ["gt", $item->id]])->find();
                $content = (new View())->fetch('template/question_make.html',
                    [
                        'd' => $assign_data,
                        'question' => $item,
                        'pre_question' => $pre_question,
                        'next_question' => $next_question
                    ]
                );
                $make_web = file_put_contents('question/question' . $item["id"] . '.html', $content);
                //开始同步数据库
                if ($make_web) {
                    $articleCountModel = ArticleSyncCount::where($where)->find();
                    if (is_null($articleCountModel)) {
                        $article_temp->count = $item->id;
                        $article_temp->type_id = $type_id;
                        $article_temp->type_name = $type_name;
                        $article_temp->node_id = $node_id;
                        $article_temp->site_id = $site_id;
                        $article_temp->site_name = $site_name;
                        $article_temp->save();
                    } else {
                        $articleCountModel->count = $item->id;
                        $articleCountModel->save();
                    }
                }
            }
        }
    }

}
