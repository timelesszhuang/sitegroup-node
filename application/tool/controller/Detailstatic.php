<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\model\ArticleSyncCount;
use app\index\model\Articletype;
use app\index\model\ScatteredTitle;
use think\Db;
use think\View;

/**
 * 详情页 静态化 比如 文章 之类
 * 执行详情页的静态化相关操作
 */
class Detailstatic extends Common
{
    use FileExistsTraits;

    /**
     * 验证下是不是 时间段允许 允许的话 返回时间段的 count
     * @access public
     * @param $site_id 站点的site_id
     * @param $requesttype 请求的类型 crontab 或者是 后台动态请求
     * @return array
     */
    public static function check_static_time($site_id, $requesttype)
    {
        $default_count = Db::name('system_config')->where(['name' => 'SYSTEM_DEFAULTSTATIC_COUNT', 'need_auth' => 0])->field('value')->find()['value'] ?: 5;
        if (!$requesttype) {
            //如果是 点击更新来的请求的话 只需要同步5条
            return [true, $default_count, true, $default_count, true, $default_count];
        }
        //这种情况是 crontab配置的
        $config_sync_info = self::get_staticconfig_info($site_id);
        $article_status = false;
        $article_count = $default_count;
        $question_status = false;
        $question_count = $default_count;
        $scattered_status = false;
        $scattered_count = $default_count;
        if (array_key_exists('article', $config_sync_info)) {
            foreach ($config_sync_info['article'] as $k => $v) {
                //比较时间
                $starttime = strtotime(date('Y-m-d') . ' ' . $v['starttime']);
                $stoptime = strtotime(date('Y-m-d') . ' ' . $v['stoptime']);
                $time = time();
                if ($time > $starttime && $time < $stoptime) {
                    $article_count = $v['staticcount'];
                    $article_status = true;
                    break;
                }
            }
        } else {
            //没有相关配置的话 默认是5条
            $article_status = true;
        }
        if (array_key_exists('question', $config_sync_info)) {
            foreach ($config_sync_info['question'] as $k => $v) {
                //比较时间
                $starttime = strtotime(date('Y-m-d') . ' ' . $v['starttime']);
                $stoptime = strtotime(date('Y-m-d') . ' ' . $v['stoptime']);
                $time = time();
                if ($time > $starttime && $time < $stoptime) {
                    $question_count = $v['staticcount'];
                    $question_status = true;
                    break;
                }
            }
        } else {
            //没有相关配置的话 默认是5条
            $question_status = true;
        }
        if (array_key_exists('scatteredarticle', $config_sync_info)) {
            foreach ($config_sync_info['scatteredarticle'] as $k => $v) {
                //比较时间
                $starttime = strtotime(date('Y-m-d') . ' ' . $v['starttime']);
                $stoptime = strtotime(date('Y-m-d') . ' ' . $v['stoptime']);
                $time = time();
                if ($time > $starttime && $time < $stoptime) {
                    $scattered_count = $v['staticcount'];
                    $scattered_status = true;
                    break;
                }
            }
        } else {
            //没有相关配置的话 默认是5条
            $scattered_status = true;
        }
        return [$article_status, $article_count, $question_status, $question_count, $scattered_status, $scattered_count];
    }

    /**
     * 获取配置信息
     * @access public
     */
    public static function get_staticconfig_info($site_id)
    {
        $config_info = Db::name('site_staticconfig')->where(['site_id' => $site_id])->field('type,starttime,stoptime,staticcount')->select();
        $config_sync_info = [];
        foreach ($config_info as $k => $v) {
            if (!array_key_exists($v['type'], $config_sync_info)) {
                $config_sync_info[$v['type']] = [];
            }
            $config_sync_info[$v['type']][] = [
                'starttime' => $v['starttime'],
                'stoptime' => $v['stoptime'],
                'staticcount' => $v['staticcount']
            ];
        }
        return $config_sync_info;
    }


    /**
     * 首先第一次入口
     * @access public
     * 静态化 文章 问答 零散段落等相关数据  如果 $requestype 为 crontab 的话 会 按照配置的 时间段跟文章数量来生成静态页面
     */
    public function index($requesttype = '')
    {
        set_time_limit(0);
        ignore_user_abort();
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        //获取  文章分类 还有 对应的pageinfo中的 所选择的A类关键词
        //获取 site页面 中 menu 指向的 a_keyword_id
        //从数据库中 获取的页面的a_keyword_id 信息 可能有些菜单 还没有存储到数据库中 如果是第一次请求的话
        $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');
        $menu_typeid_arr = Menu::getTypeIdInfo($siteinfo['menu']);

        //验证下 是不是这个时间段内 是不是可以生成
        list($articlestatic_status, $articlestatic_count, $questionstatic_status, $questionstatic_count, $scatteredstatic_status, $scatteredstatic_count) = self::check_static_time($site_id, $requesttype);
        foreach ($menu_typeid_arr as $detail_key => $v) {
            foreach ($v as $type) {
                if (!array_key_exists($type['menu_id'], $menu_akeyword_id_arr)) {
                    //请求一下 该位置 可以把该菜单的 TDK 还有 相关 a_keyword_id  等信息存储到数据库中
                    //第一次访问的时候
                    $menu_info = \app\index\model\Menu::get($type['menu_id']);
                    $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $site_id, $site_name, $node_id);
                    //菜单 页面的TDK
                    Commontool::getMenuPageTDK($keyword_info, $menu_info->generate_name, $menu_info->name, $site_id, $site_name, $node_id, $type['menu_id'], $menu_info->name);
                    $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');
                }
                $a_keyword_id = $menu_akeyword_id_arr[$type['menu_id']];
                switch ($detail_key) {
                    case'article':
                        if ($articlestatic_status) {
                            $this->articlestatic($site_id, $site_name, $node_id, $type['id'], $a_keyword_id, $articlestatic_count);
                        }
                        break;
                    case'question':
                        if ($questionstatic_status) {
                            $this->questionstatic($site_id, $site_name, $node_id, $type['id'], $a_keyword_id, $questionstatic_count);
                        }
                        break;
                    case'scatteredarticle':
                        if ($scatteredstatic_status) {
                            $this->scatteredarticlestatic($site_id, $site_name, $node_id, $type['id'], $a_keyword_id, $scatteredstatic_count);
                        }
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
    public function articlestatic($site_id, $site_name, $node_id, $type_id, $a_keyword_id, $step_limit)
    {
        //判断模板是否存在
        if (!$this->fileExists('template/article.html')) {
            return;
        }
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
        $count = \app\index\model\Article::where(["id" => ["gt", $limit], "articletype_id" => $type_id, "node_id" => $node_id])->count();
        if ($count == 0) {
            return;
        }
        //获取 所有允许同步的sync=20的  还有这个 站点添加的数据20
        $commonsql = "id >$limit and node_id=$node_id and articletype_id=$type_id and";
        $where3 = "($commonsql is_sync=20 ) or  ($commonsql site_id = $site_id)";
        $article_data = \app\index\model\Article::where($where3)->order("id", "asc")->limit($step_limit)->select();
        foreach ($article_data as $key => $item) {
            $temp_content = mb_substr(strip_tags($item->content), 0, 200);
            $assign_data = Commontool::getEssentialElement('detail', $item->title, $temp_content, $a_keyword_id);
            file_put_contents('log/article.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //页面中还需要填写隐藏的 表单 node_id site_id
            //获取上一篇和下一篇
            $pre_article = \app\index\model\Article::where(["id" => ["lt", $item["id"]],"site_id"=>0, "node_id" => $node_id, "articletype_id" => $type_id])->field("id,title")->order("id", "desc")->find();
            //上一页链接
            if ($pre_article) {
                $pre_article['href'] = "/article/article{$pre_article['id']}.html";
            }
            $next_article = [];
            if (($step_limit - $key) >= 1) {
                $commonsql1 = "id >{$item['id']} and node_id=$node_id and articletype_id=$type_id and ";
                $where2 = "($commonsql1 is_sync=20 ) or  ( $commonsql1 site_id = $site_id)";
                $next_article = \app\index\model\Article::where($where2)->field("id,title")->limit(1)->find();
                //下一页链接
                if ($next_article) {
                    $next_article = $next_article->toArray();
                    $next_article['href'] = "/article/article{$next_article['id']}.html";
                }
            }
            $temp_content = $item->content;
            //替换关键字
            $temp_content = $this->replaceKeyword($node_id, $site_id, $temp_content);
            // 将A链接插入到内容中去
            $contentWIthLink = $this->contentJonintALink($node_id, $site_id, $temp_content);
            if ($contentWIthLink) {
                $temp_content = $contentWIthLink;
            }
            $content = (new View())->fetch('template/article.html',
                [
                    'd' => $assign_data,
                    'article' => ["title" => $item->title, "auther" => $item->auther, "create_time" => $item->create_time, "content" => $temp_content],
                    'pre_article' => $pre_article,
                    'next_article' => $next_article,
                ]
            );
            //判断模板是否存在
            if (!file_exists('article')) {
                $this->make_error("article");
                die;
            }
            $make_web = file_put_contents('article/article' . $item["id"] . '.html', $content);
            //开始同步数据库
            if ($make_web) {
                $articleCountModel = ArticleSyncCount::where($where)->find();
                if (is_null($articleCountModel)) {
                    $article_temp->count = $item["id"];
                    $article_temp->type_id = $type_id;
                    $article_temp->type_name = $type_name;
                    $article_temp->node_id = $node_id;
                    $article_temp->site_id = $site_id;
                    $article_temp->site_name = $site_name;
                    $article_temp->save();
                } else {
                    $articleCountModel->count = $item["id"];
                    $articleCountModel->save();
                }
                $limit = $item["id"];
            }
        }
    }


    /**
     * 零散文章的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     * @param $type_id 文章的分类id
     * @param $a_keyword_id 栏目所对应的a类 关键词
     * @param $site_id 站点id
     * @param $site_name 站点name
     * @param $node_id 节点id
     */
    public function scatteredarticlestatic($site_id, $site_name, $node_id, $type_id, $a_keyword_id, $step_limit)
    {
        //  获取详情 页生成需要的资源  首先需要比对下当前页面是不是已经静态化了
        //  关键词
        //判断模板是否存在
        if (!$this->fileExists('template/news.html')) {
            return;
        }
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
        $count = \app\index\model\ScatteredTitle::where(["id" => ["gt", $limit], "articletype_id" => $type_id, "node_id" => $node_id])->count();
        if ($count == 0) {
            return;
        }
        $scatTitleArray = (new ScatteredTitle())->where(["id" => ["gt", $limit], "articletype_id" => $type_id])->limit($step_limit)->select();
        foreach ($scatTitleArray as $key => $item) {
            $scatArticleArray = Db::name('ScatteredArticle')->where(["id" => ["in", $item->article_ids]])->column('content_paragraph');
            $temp_arr = $item->toArray();
            $temp_arr['content'] = implode('<br/>', $scatArticleArray);
            $temp_content = mb_substr(strip_tags($temp_arr['content']), 0, 200);
            $assign_data = Commontool::getEssentialElement('detail', $temp_arr["title"], $temp_content, $a_keyword_id);
            file_put_contents('log/scatteredarticle.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //页面中还需要填写隐藏的 表单 node_id site_id
            //获取上一篇和下一篇
            $pre_article = \app\index\model\ScatteredTitle::where(["id" => ["lt", $item["id"]], "site_id"=>0,"node_id" => $node_id, "articletype_id" => $type_id])->field("id,title")->order("id", "desc")->find();
            if ($pre_article) {
                $pre_article['href'] = "/news/news{$pre_article['id']}.html";
            }
            $next_article = [];
            if (($step_limit - $key) > 1) {
                $next_article = \app\index\model\ScatteredTitle::where(["id" => ["gt", $item["id"]], "node_id" => $node_id, "articletype_id" => $type_id])->field("id,title")->limit(1)->find();
                if ($next_article) {
                    $next_article['href'] = "/news/news{$next_article['id']}.html";
                }
            }
            $content = (new View())->fetch('template/news.html',
                [
                    'd' => $assign_data,
                    'scatteredarticle' => $temp_arr,
                    'pre_article' => $pre_article,
                    'next_article' => $next_article
                ]
            );
            //判断模板是否存在
            if (!file_exists('news')) {
                $this->make_error("news");
                die;
            }
            $make_web = file_put_contents('news/news' . $item["id"] . '.html', $content);
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
                $limit = $item["id"];
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
    public function questionstatic($site_id, $site_name, $node_id, $type_id, $a_keyword_id, $step_limit)
    {
        //判断模板是否存在
        if (!$this->fileExists('template/question.html')) {
            return;
        }
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
        //判断下是否有数据 没有就创建模型  需要减去1 因为要将以前最后一页重新生成
        if (isset($articleCount->count) && $articleCount->count > 0) {
            $limit = $articleCount->count;
        } else {
            $article_temp = new ArticleSyncCount();
        }
        $question_data = \app\index\model\Question::where(["id" => ["gt", $limit], "type_id" => $type_id, "node_id" => $node_id])->order("id", "asc")->limit($step_limit)->select();
        foreach ($question_data as $key => $item) {
            $temp_content = mb_substr(strip_tags($item->content_paragraph), 0, 200);
            $assign_data = Commontool::getEssentialElement('detail', $item->question, $temp_content, $a_keyword_id);
            file_put_contents('log/question.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //页面中还需要填写隐藏的 表单 node_id site_id
            //获取上一篇和下一篇
            $pre_article = \app\index\model\Question::where(["id" => ["lt", $item->id], "node_id" => $node_id, "type_id" => $type_id])->field("id,question as title")->order("id", "desc")->find();
            if ($pre_article) {
                $pre_article['href'] = "/question/question{$pre_article['id']}.html";
            }
            $next_article = [];
            if (($step_limit - $key) > 1) {
                $next_article = \app\index\model\Question::where(["id" => ["gt", $item->id],"site_id"=>0, "node_id" => $node_id, "type_id" => $type_id])->field("id,question as title")->limit(1)->find();
                if ($next_article) {
                    $next_article['href'] = "/question/question{$next_article['id']}.html";
                }
            }
            $content = (new View())->fetch('template/question.html',
                [
                    'd' => $assign_data,
                    'question' => $item,
                    'pre_article' => $pre_article,
                    'next_article' => $next_article
                ]
            );
            //判断模板是否存在
            if (!file_exists('question')) {
                $this->make_error("question");
                die;
            }
            $make_web = file_put_contents('question/question' . $item["id"] . '.html', $content);
            //开始同步数据库
            if ($make_web) {
                $articleCountModel = ArticleSyncCount::where($where)->find();
                if (is_null($articleCountModel)) {
                    $article_temp->count = $item["id"];
                    $article_temp->type_id = $type_id;
                    $article_temp->type_name = $type_name;
                    $article_temp->node_id = $node_id;
                    $article_temp->site_id = $site_id;
                    $article_temp->site_name = $site_name;
                    $article_temp->save();
                } else {
                    $articleCountModel->count = $item["id"];
                    $articleCountModel->save();
                }
                $limit = $item["id"];
            }
        }
    }

}
