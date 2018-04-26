<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\model\Article;
use app\index\model\ArticleSyncCount;
use think\Cache;
use think\Config;
use think\Db;
use think\View;

/**
 * 详情页 静态化 比如 文章 之类
 * 执行详情页的静态化相关操作
 */
class Detailstatic extends Common
{

    //系统默认每次静态化的数量
    private static $system_default_count;
    public $typeid_arr = '';
    public $commontool;

    public function __construct()
    {
        parent::__construct();
        $data = cache::remember('system_config', function () {
            return Db::name('system_config')->where(['name' => 'SYSTEM_DEFAULTSTATIC_COUNT', 'need_auth' => 0])->field('value')->find();
        });
        if ($data && array_key_exists('value', $data)) {
            // 系统中默认能静态化的数量
            self::$system_default_count = $data['value'];
        } else {
            //如果没有设置该字段 则默认生成五篇
            self::$system_default_count = 5;
        }
        list($type_aliasarr, $typeid_arr) = (new Commontool())->getTypeIdInfo();
        $this->typeid_arr = $typeid_arr;
        $this->commontool = new Commontool();
        $this->commontool->tag = 'detail';
    }


    /**
     * 设置文章等继续静态化指定的数量
     * @access public
     * 静态化 文章 问答 零散段落等相关数据
     */
    public function setStaticCount()
    {
        //需要推送到百度后台
        $default_count = self::$system_default_count;
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $articletypeid_str = implode(',', array_keys($article_typearr));
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        $questiontypeid_str = implode(',', array_keys($question_typearr));
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $producttypeid_str = implode(',', array_keys($product_typearr));
        $where = [
            'node_id' => $this->node_id,
            'site_id' => $this->site_id,
            'type_name' => 'article'
        ];
        if ($articletypeid_str) {
            $articlepre_stop = $this->detail_maxid('article');
            $article_list_sql = "id >= $articlepre_stop and node_id=$this->node_id and articletype_id in ($articletypeid_str)";
            // 要 step_limit+1 因为要 获取上次的最后一条 最后一条的下一篇需要重新生成链接
            $article_ids = (new \app\index\model\Article)->where($article_list_sql)->order("id", "asc")->limit($default_count + 1)->column('id');
            // 这个地方需要 拉取文章缩略图
            $articleCount = ArticleSyncCount::where($where)->find();
            if ($article_ids) {
                $articlemax_id = max($article_ids);
                if (isset($articleCount->count) && $articleCount->count >= 0) {
                    $articleCount->count = $articlemax_id;
                    $articleCount->save();
                } else {
                    ArticleSyncCount::create([
                        'node_id' => $this->node_id,
                        'site_id' => $this->site_id,
                        'site_name' => $this->site_name,
                        'type_name' => 'article',
                        'count' => $articlemax_id
                    ]);
                }
            }
            $this->formBaiduUrls($article_ids, 'article');
        }
        if ($questiontypeid_str) {
            $questionpre_stop = $this->detail_maxid('question');
            $question_list_sql = "id >= $questionpre_stop and node_id=$this->node_id and type_id in ($questiontypeid_str)";
            $question_ids = (new \app\index\model\Question)->where($question_list_sql)->order("id", "asc")->limit($default_count + 1)->column('id');
            $where['type_name'] = 'question';
            $questionCount = ArticleSyncCount::where($where)->find();
            if ($question_ids) {
                $questionmax_id = max($question_ids);
                if (isset($questionCount->count) && $questionCount->count >= 0) {
                    $questionCount->count = $questionmax_id;
                    $questionCount->save();
                } else {
                    ArticleSyncCount::create([
                        'node_id' => $this->node_id,
                        'site_id' => $this->site_id,
                        'site_name' => $this->site_name,
                        'type_name' => 'question',
                        'count' => $questionmax_id
                    ]);
                }
                $this->formBaiduUrls($question_ids, 'question');
            }
        }
        if ($producttypeid_str) {
            //产品相关操作
            $productpre_stop = $this->detail_maxid('product');
            $productsql = "id >= $productpre_stop and node_id=$this->node_id and type_id in ($producttypeid_str)";
            $product_ids = (new \app\index\model\Product)->where($productsql)->order("id", "asc")->limit($default_count + 1)->column('id');
            $where['type_name'] = 'product';
            $productCount = ArticleSyncCount::where($where)->find();
            if ($product_ids) {
                $productmax_id = max($product_ids);
                if (isset($productCount->count) && $productCount->count >= 0) {
                    $productCount->count = $productmax_id;
                    $productCount->save();
                } else {
                    ArticleSyncCount::create([
                        'node_id' => $this->node_id,
                        'site_id' => $this->site_id,
                        'site_name' => $this->site_name,
                        'type_name' => 'product',
                        'count' => $productmax_id
                    ]);
                }
                $this->formBaiduUrls($product_ids, 'product');
            }
        }
    }


    /**
     * 生成并添加百度的url到缓存中
     * @access public
     */
    public function formBaiduUrls($ids, $type)
    {
        $urls = [];
        switch ($type) {
            case 'article':
                foreach ($ids as $id) {
                    array_push($urls, sprintf($this->articleaccesspath, $id));
                }
                break;
            case 'product':
                foreach ($ids as $id) {
                    array_push($urls, sprintf($this->productaccesspath, $id));
                }
                break;
            case 'question':
                foreach ($ids as $id) {
                    array_push($urls, sprintf($this->questionaccesspath, $id));
                }
                break;
        }
        $this->urlsCache($urls);
    }


    /**
     * 获取制定文章tag 的列表
     * @access public
     * @param $tags 标签
     * @param $articletype_idstr 该站点选择的文章类型列表
     * @param $article_typearr
     * @param int $limit
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \throwable
     */
    public function getTagArticleList($tags, $articletype_idstr, $article_typearr, $limit = 10)
    {
        $max_id = $this->detail_maxid('article');
        $tags = array_filter(explode(',', $tags));
        if (!$tags) {
            //tag 没有选择的情况
            return [];
        }
        $where = " node_id=$this->node_id and articletype_id in ($articletype_idstr) and (%s) and id<={$max_id}";
        $tagwhere = '';
        foreach ($tags as $k => $v) {
            $seperator = ' ';
            if ($tagwhere) {
                $seperator = ' or ';
            }
            $tagwhere .= $seperator . " tags like '%,$v,%' ";
        }
        $where = sprintf($where, $tagwhere);
        $tagsArticleList = (new \app\index\model\Article)->Where($where)->limit($limit)->field($this->commontool->articleListField)->order(['sort' => 'desc', 'id' => 'desc'])->select();
        if ($tagsArticleList) {
            $this->commontool->formatArticleList($tagsArticleList, $article_typearr);
            return $tagsArticleList;
        }
        return [];
    }

    /**
     * 添加分享代码
     * @access private
     */
    private function add_share_code($content)
    {
        $share_code = (new Commontool())->get_share_code();
        $content = $content . '<br/>' . $share_code;
        return $content;
    }


    /**
     * 生成单独的文章内容 预览跟重新生成的时候会
     * @access public
     */
    public function form_perarticle_content(&$item, $keyword_id, $menu_id, $menu_name, $menu_enname, $tags)
    {
        //截取出 页面的 description 信息
        $description = mb_substr(strip_tags($item['content']), 0, 200);
        //页面的描述
        $summary = $description ?: $item['summary'];
        $summary = preg_replace('/^&.+\;$/is', '', $summary);
        $summary = mb_substr(strip_tags($summary), 0, 70);
        //页面的关键词
        $keywords = $item['keywords'];
        //获取网站的 tdk 文章列表等相关 公共元素
        $assign_data = $this->commontool->getEssentialElement($item, $summary, $keywords, $keyword_id, $menu_id, ['menu_name' => $menu_name, 'menu_enname' => $menu_enname], 'article');
        if ($item['thumbnails_name']) {
            //表示是oss的
            $this->get_osswater_img($item['thumbnails'], $item['thumbnails_name'], $this->waterString, $this->waterImgUrl);
        }
        //替换图片静态化内容中图片文件
        $item['content'] = $this->form_content_img($item['content']);
        // 替换关键词为指定链接 遍历全文和所有关键词
        $item['content'] = $this->articleReplaceKeyword($item['content']);
        // 替换关键字
        $item['content'] = $this->replaceKeyword($this->node_id, $this->site_id, $item['content']);
        // 将A链接插入到内容中去
        $contentWIthLink = $this->contentJonintALink($this->node_id, $this->site_id, $item['content']);
        if ($contentWIthLink) {
            $item['content'] = $contentWIthLink;
        }
        $item['content'] = $this->add_share_code($item['content']);
        $articletags = [];
        if ($item['tags']) {
            $tag_arr = explode(',', $item['tags']);
            foreach ($tag_arr as $val) {
                if (array_key_exists($val, $tags)) {
                    $articletags[] = $tags[$val];
                }
            }
        }
        $item['tags'] = $articletags;
        return $assign_data;
    }


    /**
     * 根据内容生成图片
     * @access public
     */
    public function form_content_img($content)
    {
        //使用正则匹配 从文章中获取oss图片链接
        preg_match_all('/<img[^>]+src\s*=\\s*[\'\"]([^\'\"]+)[\'\"][^>]*>/i', $content, $match);
        //两种均可
        /*preg_match_all('/<[img|IMG].*?src=[\'|\"](.*?(?:[\.gif|\.jpg]))[\'|\"].*?[\/]?>/', $content, $match);*/
        if (!empty($match[0])) {
            if (array_key_exists(1, $match)) {
                $endpoint = Config::get('oss.endpoint');
                $bucket = Config::get('oss.bucket');
                foreach ($match[1] as $k => $v) {
                    $endpointurl = sprintf("https://%s.%s/", $bucket, $endpoint);
                    if (strpos($v, $endpointurl) === false) {
                        continue;
                    }
                    //有时候有图片没有后缀
                    list($filetype, $filename) = $this->analyseUrlFileType($v);
                    //阿里云图片生成
                    $filepath = $filename . '.' . $filetype;
                    $localfilepath = '/images/' . $filepath;
                    if ($this->get_osswater_img($v, $filepath, $this->waterString, $this->waterImgUrl)) {
                        $content = str_replace($v, $localfilepath, $content);
                    }
                }
            }
        }
        return $content;
    }


    /**
     * 获取文章详细信息相关
     * @access public
     * @param $id
     * @return array
     * @throws \Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \throwable
     */
    public function article_detailinfo($id)
    {
        // 取出指定id的文章
        $articlesql = "id = $id and node_id=$this->node_id";
        $article = (new \app\index\model\Article)->where($articlesql)->find()->toArray();
        $type_id = $article['articletype_id'];
        // 获取menu信息
        $menuInfo = (new \app\tool\model\Menu)->where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = (new \app\tool\model\SitePageinfo)->where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        list($pre_article, $next_article) = $this->get_article_prenextinfo($id, $type_id);
        //需要去除该站点的tag相关列表
        $article_typearr = array_key_exists('article', $this->typeid_arr) ? $this->typeid_arr['article'] : [];
        $articletype_idstr = implode(',', array_keys($article_typearr));
        $tagsArticleList = $this->getTagArticleList($article['tags'], $articletype_idstr, $article_typearr);
        $tags = $this->commontool->getTags('article');
        $assign_data = $this->form_perarticle_content($article, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name'], $menuInfo['generate_name'], $tags);
        $template = $this->getTemplate('detail', $menuInfo['id'], 'article');
        $type_id = $article['articletype_id'];
        $currentmenu_typelist = $this->getDetailMenutypeList($type_id, $article_typearr);
        $data = [
            'd' => $assign_data,
            'page' => $article,
            'pre_page' => $pre_article,
            'next_page' => $next_article,
            'relevant_pages' => $tagsArticleList,
            'currentmenu_typelist' => $currentmenu_typelist
        ];
        return [$template, $data];
    }

    /***
     * 获取每个详情的上次静态化到的max_id
     * @param string $type_name
     * @return mixed
     */
    public function detail_maxid($type_name = 'article')
    {
        //获取 站点 某个栏目同步到的文章id
        return Cache::remember($type_name . "_max_id", function () use ($type_name) {
            $max_id = 0;
            $where = [
                'type_name' => $type_name,
                "node_id" => $this->node_id,
                "site_id" => $this->site_id
            ];
            $Count = (new \app\index\model\ArticleSyncCount)->where($where)->find();
            //判断下是否有数据 没有就创建模型
            if (isset($Count->count) && $Count->count >= 0) {
                $max_id = $Count->count;
            } else {
                //article 暂时没有静态化到的位置数据
                ArticleSyncCount::create([
                    'node_id' => $this->node_id,
                    'site_id' => $this->site_id,
                    'site_name' => $this->site_name,
                    'type_name' => $type_name,
                    'count' => 0
                ]);
            }
            return $max_id;
        });
    }


    /**
     * 获取文章上一页下一页
     * @access private
     */
    private function get_article_prenextinfo($id, $type_id)
    {
        $max_id = $this->detail_maxid('article');
        $pre_article_sql = "id <{$id} and node_id=$this->node_id and articletype_id=$type_id";
        $pre_article = Article::where($pre_article_sql)->field("id,title")->order("id", "desc")->find();
        //上一页链接
        if ($pre_article) {
            $pre_article = $pre_article->toArray();
            $pre_article = ['href' => sprintf($this->prearticlepath, $pre_article['id']), 'title' => $pre_article['title']];
        }
        //获取下一篇 的网址
        //最后一条 不需要有 下一页
        $next_article_sql = "id >{$id} and id<={$max_id} and node_id=$this->node_id and articletype_id=$type_id";
        $next_article = (new \app\index\model\Article)->where($next_article_sql)->field("id,title")->find();
        //下一页链接
        if ($next_article) {
            $next_article = $next_article->toArray();
            $next_article['href'] = sprintf($this->prearticlepath, $id);
        }
        return [$pre_article, $next_article];
    }


    /**
     * 问答预览重新生成时候调取数据需要的数据
     * @param $id
     * @return array
     * @throws \Exception
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @throws \throwable
     */
    public function question_detailinfo($id)
    {
        $questionsql = "id = $id and node_id=$this->node_id";
        $question = (new \app\index\model\Question)->where($questionsql)->find()->toArray();
        // 获取menu信息
        $type_id = $question['type_id'];
        $menuInfo = (new \app\tool\model\Menu)->where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = (new \app\tool\model\SitePageinfo)->where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        list($pre_question, $next_question) = $this->get_question_prenextinfo($id, $type_id);
        //获取站点的tag 信息
        $question_typearr = array_key_exists('question', $this->typeid_arr) ? $this->typeid_arr['question'] : [];
        $questiontype_idstr = implode(',', array_keys($question_typearr));
        $tagsQuestionList = $this->getTagArticleList($question['tags'], $questiontype_idstr, $question_typearr);
        $tags = $this->commontool->getTags('article');
        $assign_data = $this->form_perquestion($question, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name'], $menuInfo['generate_name'], $tags);
        $template = $this->getTemplate('detail', $menuInfo['id'], 'question');
        $type_id = $question['type_id'];
        $currentmenu_typelist = $this->getDetailMenutypeList($type_id, $question_typearr);
        $data = [
            'd' => $assign_data,
            'page' => $question,
            'pre_page' => $pre_question,
            'next_page' => $next_question,
            'relevant_pages' => $tagsQuestionList,
            'currentmenu_typelist' => $currentmenu_typelist
        ];
        return [$template, $data];
    }


    /**
     * 获取问答的上一页下一页
     * @access private
     */
    private function get_question_prenextinfo($id, $type_id)
    {
        $max_id = $this->detail_maxid('question');
        //获取上一篇和下一篇
        $pre_question = (new \app\index\model\Question)->where(["id" => ["lt", $id], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->order("id", "desc")->find();
        if ($pre_question) {
            $pre_question['href'] = sprintf($this->prequestionpath, $pre_question['id']);
        }
        //下一篇可能会导致其他问题
        $next_sql = "id >{$id} and id<={$max_id} and node_id=$this->node_id and type_id=$type_id";
        $next_question = (new \app\index\model\Question)->where($next_sql)->field("id,question as title")->find();
        if ($next_question) {
            $next_question['href'] = "/question/question{$next_question['id']}.html";
        }
    }


    /**
     * 获取问答的TAG
     * @access public
     */
    public function getTagQuestionList($tags, $questiontype_idstr, $question_typearr, $limit = 10)
    {
        $max_id = $this->detail_maxid('question');
        $tags = array_filter(explode(',', $tags));
        if (!$tags) {
            //tag 没有选择的情况
            return [];
        }
        $where = " node_id=$this->node_id and type_id in ($questiontype_idstr) and (%s) and id<={$max_id} ";
        $tagwhere = '';
        foreach ($tags as $k => $v) {
            $seperator = ' ';
            if ($tagwhere) {
                $seperator = ' or ';
            }
            $tagwhere .= $seperator . " tags like '%,$v,%' ";
        }
        $where = sprintf($where, $tagwhere);
        $tagsQuestionList = (new \app\index\model\Question)->where($where)->limit($limit)->field($this->commontool->questionListField)->order(['sort' => 'desc', 'id' => 'desc'])->select();
        if ($tagsQuestionList) {
            $this->commontool->formatQuestionList($tagsQuestionList, $question_typearr);
            return $tagsQuestionList;
        }
        return [];
    }


    /**
     * 格式化每个问题页面
     * @access public
     * @param $item
     * @param $keyword_id
     * @param $menu_id
     * @param $menu_name
     * @param $tags
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function form_perquestion(&$item, $keyword_id, $menu_id, $menu_name, $menu_enname, $tags)
    {
        $description = $item['description'];
        $description = $description ?: mb_substr(strip_tags($item['content_paragraph']), 0, 200);
        $description = preg_replace('/^&.+\;$/is', '', $description);
        $description = mb_substr(strip_tags($description), 0, 70);
        //页面的描述
        $keywords = $item['keywords'];
        $item['content_paragraph'] = $this->form_content_img($item['content_paragraph']);
        $item['content_paragraph'] = $this->add_share_code($item['content_paragraph']);
        $questiontags = [];
        if ($item['tags']) {
            $tag_arr = explode(',', $item['tags']);
            foreach ($tag_arr as $val) {
                if (array_key_exists($val, $tags)) {
                    $questiontags[] = $tags[$val];
                }
            }
        }
        $item['tags'] = $questiontags;
        $assign_data = $this->commontool->getEssentialElement(['id' => $item['id'], 'title' => $item['question']], $description, $keywords, $keyword_id, $menu_id, ['menu_name' => $menu_name, 'menu_enname' => $menu_enname], 'question');
        return $assign_data;
    }


    /**
     * 生成单个产品 因为不用考虑定期生成 多少篇
     * @access public
     */
    public function form_perproduct_content(&$item, $keyword_id, $menu_id, $menu_name, $menu_enname, $tags)
    {
        //截取出 页面的 description 信息
        $description = mb_substr(strip_tags($item['summary']), 0, 200);
        $description = preg_replace('/^&.+\;$/is', '', $description);
        $summary = $item['summary'] ?: $description;
        $summary = mb_substr(strip_tags($summary), 0, 70);
        $keywords = $item['keywords'];
        //获取网站的 tdk 文章列表等相关 公共元素
        $assign_data = $this->commontool->getEssentialElement(['id' => $item['id'], 'title' => $item['name']], $summary, $keywords, $keyword_id, $menu_id, ['menu_name' => $menu_name, 'menu_enname' => $menu_enname], 'product');
        if ($item['image_name']) {
            $this->get_osswater_img($item['image'], $item['image_name'], $this->waterString, $this->waterImgUrl);
        }
        //替换图片 base64 为 图片文件
        $item['detail'] = $this->form_content_img($item['detail']);
        $item['detail'] = $this->add_share_code($item['detail']);
        // 相关图片
        $imgser = $item['imgser'];
        $local_img = [];
        if ($imgser) {
            $imglist = unserialize($imgser);
            //本地的图片链接 需要随机生成链接
            $local_img = $this->form_imgser_img($imglist);
        }
        //其他相关信息
        $template = $this->getTemplate('detail', $menu_id, 'product');
        //判断模板是否存在
        if (!$this->fileExists($template)) {
            return false;
        }
        $producttags = [];
        //获取tags 页面相关数据
        if ($item['tags']) {
            $tag_arr = explode(',', $item['tags']);
            foreach ($tag_arr as $val) {
                if (array_key_exists($val, $tags)) {
                    $producttags[] = $tags[$val];
                }
            }
        }
        $item['tags'] = $producttags;
        $item['images'] = $local_img;
        $item['image'] = "<img src='/images/{$item['image_name']}' alt='{$item['name']}'>";
        return $assign_data;
    }

    /**
     * 获取问答的TAG
     * @access public
     */
    public function getTagProductList($tags, $producttype_idstr, $produt_typearr, $limit = 10)
    {
        $max_id = $this->detail_maxid('product');
        $tags = array_filter(explode(',', $tags));
        if (!$tags) {
            //tag 没有选择的情况
            return [];
        }
        $where = " node_id=$this->node_id and type_id in ($producttype_idstr) and (%s) and id<={$max_id} ";
        $tagwhere = '';
        foreach ($tags as $k => $v) {
            $seperator = ' ';
            if ($tagwhere) {
                $seperator = ' or ';
            }
            $tagwhere .= $seperator . " tags like '%,$v,%' ";
        }
        $where = sprintf($where, $tagwhere);
        $tagsProductList = (new \app\index\model\Product)->where($where)->limit($limit)->field($this->commontool->productListField)->order(['sort' => 'desc', 'id' => 'desc'])->select();
        if ($tagsProductList) {
            $this->commontool->formatProductList($tagsProductList, $produt_typearr);
            return $tagsProductList;
        }
        return [];
    }

    /**
     * 获取文章详细信息相关
     * @access public
     */
    public function product_detailinfo($id)
    {
        // 取出指定id的文章
        $productsql = "id = $id and node_id=$this->node_id";
        $product = (new \app\index\model\Product)->where($productsql)->find()->toArray();
        $type_id = $product['type_id'];
        // 获取menu信息
        $menuInfo = (new \app\tool\model\Menu)->where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = (new \app\tool\model\SitePageinfo)->where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        list($pre_product, $next_product) = $this->get_product_prenextinfo($id, $type_id);
        //需要去除该站点的tag相关列表
        $product_typearr = array_key_exists('product', $this->typeid_arr) ? $this->typeid_arr['product'] : [];
        $producttype_idstr = implode(',', array_keys($product_typearr));
        $tags = $this->commontool->getTags('product');
        $tagsArticleList = $this->getTagProductList($product['tags'], $producttype_idstr, $product_typearr);
        $assign_data = $this->form_perproduct_content($product, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name'], $menuInfo['generate_name'], $tags);
        $template = $this->getTemplate('detail', $menuInfo['id'], 'product');
        // 需要列出现当前文章所选栏目下的所有分类 以及当前选中
        $type_id = $product['type_id'];
        $currentmenu_typelist = $this->getDetailMenutypeList($type_id, $product_typearr);
        $data = [
            'd' => $assign_data,
            'page' => $product,
            'pre_page' => $pre_product,
            'next_page' => $next_product,
            'relevant_pages' => $tagsArticleList,
            'currentmenu_typelist' => $currentmenu_typelist
        ];
        return [$template, $data];
    }


    /**
     * 获取详情页面的所属栏目的 type列表 包含当前
     * @access public
     */
    public function getDetailMenutypeList($type_id, $type_arr)
    {
        $menutype_list = [];
        //当前的菜单的英文名
        $currentmenu_enname = '';
        $currentmenu_name = '';
        foreach ($type_arr as $k => $v) {
            $menu_enname = $v['menu_enname'];
            if (!array_key_exists($menu_enname, $menutype_list)) {
                $menutype_list[$menu_enname] = [];
            }
            $current = $v['type_id'] == $type_id ? true : false;
            if ($currentmenu_enname == '' && $current) {
                $currentmenu_enname = $v['menu_enname'];
                $currentmenu_name = $v['menu_name'];
            }
            $type = [
                'href' => $v['href'],
                'text' => $v['type_name'],
                'title' => $v['type_name'],
                'current' => $current
            ];
            array_push($menutype_list[$menu_enname], $type);
        }
        return ['list' => $menutype_list[$currentmenu_enname], 'menu_name' => $currentmenu_name, 'menu_enname' => $currentmenu_enname];
    }


    /**
     * 获取文章上一页下一页
     * @access private
     */
    private function get_product_prenextinfo($id, $type_id)
    {
        // 把 站点的相关的数据写入数据库中
        //获取上一篇和下一篇
        //获取上一篇
        $max_id = $this->detail_maxid('product');
        $pre_productcommon_sql = "id <{$id} and node_id=$this->node_id and type_id={$type_id} ";
        $pre_product = (new \app\index\model\Product)->where($pre_productcommon_sql)->field("id,name,image_name")->order("id", "desc")->find();
        //上一页链接
        if ($pre_product) {
            $pre_product = $pre_product->toArray();
            $pre_product = ['href' => sprintf($this->preproductpath, $pre_product['id']), 'img' => "<img src='/images/{$pre_product['image_name']}' alt='{$pre_product['name']}'>", 'title' => $pre_product['name']];
        }
        //最后一条 不需要有 下一页 需要判断下 是不是下一篇包含最大id
        $next_productcommon_sql = "id >{$id} and id<={$max_id} and node_id=$this->node_id and type_id={$type_id} ";
        $next_product = (new \app\index\model\Product)->where($next_productcommon_sql)->field("id,name,image_name")->find();
        //下一页链接
        if ($next_product) {
            $next_product = $next_product->toArray();
            $next_product = ['href' => sprintf($this->preproductpath, $next_product['id']), 'img' => "<img src='/images/{$next_product['image_name']}' alt='{$next_product['name']}'>", 'title' => $next_product['name']];
        }
        return [$pre_product, $next_product];
    }


    /**
     * 生成产品的多张图片
     * @access private
     */
    private function form_imgser_img($img_arr)
    {
        $endpoint = Config::get('oss.endpoint');
        $bucket = Config::get('oss.bucket');
        $local_imgarr = [];
        foreach ($img_arr as $k => $v) {
            $endpointurl = sprintf("https://%s.%s/", $bucket, $endpoint);
            //表示链接不存在
            $imgname = $v['imgname'];
            $osssrc = $v['osssrc'];
            if (strpos($osssrc, $endpointurl) === false) {
                array_push($local_imgarr, $osssrc);
                continue;
            }
            if ($this->get_osswater_img($osssrc, $imgname, $this->waterString, $this->waterImgUrl)) {
                array_push($local_imgarr, '/images/' . $imgname);
            }
        }
        return $local_imgarr;
    }

}
