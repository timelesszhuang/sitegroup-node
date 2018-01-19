<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\model\Article;
use app\index\model\ArticleSyncCount;
use app\index\model\Product;
use app\index\model\Question;
use app\index\model\ScatteredTitle;
use app\tool\model\SitePageinfo;
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


    public function __construct()
    {
        parent::__construct();
        $data = Db::name('system_config')->where(['name' => 'SYSTEM_DEFAULTSTATIC_COUNT', 'need_auth' => 0])->field('value')->find();
        if ($data && array_key_exists('value', $data)) {
            self::$system_default_count = $data['value'];
        } else {
            //如果没有设置该字段 则默认生成五篇
            self::$system_default_count = 5;
        }
        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($this->menu_ids);
        $this->typeid_arr = $typeid_arr;
    }

    /**
     * 验证下是不是 时间段允许 允许的话 返回时间段的 count
     * @access private
     * @param $site_id 站点的site_id
     * @param $requesttype 请求的类型 crontab 或者是 后台动态请求
     * @return array
     */
    private static function check_static_time($site_id, $requesttype)
    {
        $default_count = self::$system_default_count;
        if ($requesttype == '') {
            //如果是 点击更新来的请求的话 只需要同步5条
            return [true, $default_count, true, $default_count, true, $default_count];
        }
        if ($requesttype == 'allsitestatic') {
            //如果全站重新生成的话
        }
        //这种情况是 crontab配置的 定时请求
        $config_sync_info = self::get_staticconfig_info($site_id);
        $article_status = false;
        $article_count = $default_count;
        $question_status = false;
        $question_count = $default_count;
        $product_status = false;
        $product_count = $default_count;

        if (array_key_exists('article', $config_sync_info)) {
            //表示该站点包含静态化配置  需要遵从 静态化配置 指定时间段内生成多少数据
            foreach ($config_sync_info['article'] as $k => $v) {
                //比较时间
                //上次静态化的时间点
                $laststatic_time = $v['laststatic_time'];
                $starttime = strtotime(date('Y-m-d') . ' ' . $v['starttime']);
                $stoptime = strtotime(date('Y-m-d') . ' ' . $v['stoptime']);
                $time = time();
                if ($laststatic_time > $starttime && $laststatic_time < $stoptime) {
                    //上次静态化的时间 在 该时间段内 则不需要更新 说明之前已经有 生成过
                    break;
                }
                if ($time > $starttime && $time < $stoptime) {
                    $article_count = $v['staticcount'];
                    $article_status = true;
                    //更新下 上次静态化时间
                    self::set_laststatic_time($v['id']);
                    break;
                }
            }
        } else {
            //没有相关配置的话 默认是5条
            $article_status = true;
        }
        if (array_key_exists('question', $config_sync_info)) {
            foreach ($config_sync_info['question'] as $k => $v) {
                //上次静态化的时间点
                $laststatic_time = $v['laststatic_time'];
                //比较时间
                $starttime = strtotime(date('Y-m-d') . ' ' . $v['starttime']);
                $stoptime = strtotime(date('Y-m-d') . ' ' . $v['stoptime']);
                $time = time();
                if ($laststatic_time > $starttime && $laststatic_time < $stoptime) {
                    //上次静态化的时间 在 该时间段内 则不需要更新 说明之前已经有 生成过
                    break;
                }
                if ($time > $starttime && $time < $stoptime) {
                    $question_count = $v['staticcount'];
                    $question_status = true;
                    self::set_laststatic_time($v['id']);
                    break;
                }
            }
        } else {
            //没有相关配置的话 默认是5条
            $question_status = true;
        }

        if (array_key_exists('product', $config_sync_info)) {
            foreach ($config_sync_info['product'] as $k => $v) {
                //上次静态化的时间点
                $laststatic_time = $v['laststatic_time'];
                //比较时间
                $starttime = strtotime(date('Y-m-d') . ' ' . $v['starttime']);
                $stoptime = strtotime(date('Y-m-d') . ' ' . $v['stoptime']);
                $time = time();
                if ($laststatic_time > $starttime && $laststatic_time < $stoptime) {
                    //上次静态化的时间 在 该时间段内 则不需要更新 说明之前已经有 生成过
                    break;
                }
                if ($time > $starttime && $time < $stoptime) {
                    $product_count = $v['staticcount'];
                    $product_status = true;
                    self::set_laststatic_time($v['id']);
                    break;
                }
            }
        } else {
            $product_status = true;
        }
        return [$article_status, intval($article_count), $question_status, intval($question_count), $product_status, intval($product_count)];
    }

    /**
     * 获取配置信息
     * @access private
     */
    private static function get_staticconfig_info($site_id)
    {
        $config_info = Db::name('site_staticconfig')->where(['site_id' => $site_id])->field('id,type,starttime,stoptime,staticcount,laststatic_time')->select();
        $config_sync_info = [];
        foreach ($config_info as $k => $v) {
            if (!array_key_exists($v['type'], $config_sync_info)) {
                $config_sync_info[$v['type']] = [];
            }
            $config_sync_info[$v['type']][] = [
                'id' => $v['id'],
                'starttime' => $v['starttime'],
                'stoptime' => $v['stoptime'],
                'staticcount' => $v['staticcount'],
                'laststatic_time' => $v['laststatic_time']
            ];
        }
        return $config_sync_info;
    }


    /**
     * 上次 静态化时间
     * @access private
     */
    private static function set_laststatic_time($id)
    {
        Db::name('site_staticconfig')->where(['id' => $id])->update(['laststatic_time' => time()]);
    }


    /**
     * 首先第一次入口
     * @access public
     * 静态化 文章 问答 零散段落等相关数据
     * @param string $requesttype 如果 $requestype 为 crontab 的话 会 按照配置的 时间段跟文章数量来生成静态页面
     *                            如果 为空的话 表示 从页面点击操作之后触发的操作
     */
    public function index($requesttype = '')
    {
        // 获取站点的相关的相关信息
        $siteinfo = Site::getSiteInfo();
//        $site_id = $siteinfo['id'];
//        $site_name = $siteinfo['site_name'];
//        $node_id = $siteinfo['node_id'];

        //获取  文章分类 还有 对应的pageinfo中的 所选择的A类关键词
        //获取 site页面 中 menu 指向的 a_keyword_id
        //从数据库中 获取的菜单对应的a_keyword_id 信息 可能有些菜单 还没有存储到数据库中 如果是第一次请求的话
        $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $this->site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');
        //获取当前
        //验证下 是不是这个时间段内 是不是可以生成
        list($articlestatic_status, $articlestatic_count, $questionstatic_status, $questionstatic_count, $productstatic_status, $productstatic_count) = self::check_static_time($this->site_id, $requesttype);
        //区分菜单所属栏目是哪种类型  article question scatteredstatic

        $article_type_keyword = [];
        $question_type_keyword = [];
        $product_type_keyword = [];

        foreach ($this->typeid_arr as $detail_key => $v) {
            // $detail_key 为 类目的类型
            // $v 为
            //[
            //{
            //  id 为 文章分类的 id
            //  name 为 文章的分类 name
            //  menu_id 菜单的 id
            //  menu_name 菜单的 name
            //},
            //{}
            //]
            foreach ($v as $type) {
                //如果数据库中没有 账号
                if (!array_key_exists($type['menu_id'], $menu_akeyword_id_arr)) {
                    //请求一下 该位置 可以把该菜单的 TDK 还有 相关 a_keyword_id  等信息存储到数据库中
                    //第一次访问的时候
                    $menu_info = \app\index\model\Menu::get($type['menu_id']);
                    $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $this->site_id, $this->site_name, $this->node_id);
                    //菜单 页面的TDK
                    Commontool::getMenuPageTDK($keyword_info, $menu_info->generate_name, $menu_info->name, $this->site_id, $this->site_name, $this->node_id, $type['menu_id'], $menu_info->name);
                    $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $this->site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');
                }
                $a_keyword_id = $menu_akeyword_id_arr[$type['menu_id']];
                switch ($detail_key) {
                    case'article':
                        if ($articlestatic_status) {
                            $article_type_keyword[$type['type_id']] = ['type_id' => $type['type_id'], 'menu_id' => $type['menu_id'], 'menu_name' => $type['menu_name'], 'keyword_id' => $a_keyword_id];
                        }
                        break;
                    case'question':
                        if ($questionstatic_status) {
                            $question_type_keyword[$type['type_id']] = ['type_id' => $type['type_id'], 'menu_id' => $type['menu_id'], 'menu_name' => $type['menu_name'], 'keyword_id' => $a_keyword_id];
                        }
                        break;
                    case 'product':
                        //产品类型 不需要限制生成数量一次性添加就可
                        $product_type_keyword[$type['type_id']] = ['type_id' => $type['type_id'], 'menu_id' => $type['menu_id'], 'menu_name' => $type['menu_name'], 'keyword_id' => $a_keyword_id];
                        break;
                }
            }
        }
        if ($article_type_keyword && $articlestatic_count) {
            $article_typearr = array_key_exists('article', $this->typeid_arr) ? $this->typeid_arr['article'] : [];
            $this->articlestatic($article_type_keyword, $articlestatic_count, $article_typearr);
        }
        if ($question_type_keyword && $questionstatic_count) {
            $question_typearr = array_key_exists('question', $this->typeid_arr) ? $this->typeid_arr['question'] : [];
            $this->questionstatic($question_type_keyword, $questionstatic_count, $question_typearr);
        }
        if ($product_type_keyword) {
            $product_typearr = array_key_exists('product', $this->typeid_arr) ? $this->typeid_arr['product'] : [];
            $this->productstatic($product_type_keyword, $productstatic_count, $product_typearr);
        }
    }

    /**
     * 文章详情页面的静态化
     * @access public
     * @param $article_type_keyword
     * @param $step_limit
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function articlestatic($article_type_keyword, $step_limit, $article_typearr)
    {
        $type_name = "article";
        $where = [
            'type_name' => $type_name,
            "node_id" => $this->node_id,
            "site_id" => $this->site_id
        ];
        $pre_stop = 0;
        //获取 站点 某个栏目同步到的文章id
        $articleCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($articleCount->count) && $articleCount->count >= 0) {
            $pre_stop = $articleCount->count;
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
        $articletype_idstr = implode(',', array_keys($article_type_keyword));
        //删除掉是否同步功能
        $article_list_sql = "id >= $pre_stop and node_id=$this->node_id and articletype_id in ($articletype_idstr)";
        // 要 step_limit+1 因为要 获取上次的最后一条 最后一条的下一篇需要重新生成链接
        $article_data = \app\index\model\Article::where($article_list_sql)->order("id", "asc")->limit($step_limit + 1)->select();
        //获取本次最大的id，用于比对是不是有下一页
        if ($article_data) {
            $max_index = max(array_flip(array_keys($article_data)));
            $max_id = $article_data[$max_index]['id'];
        }
        // 生成页面之后需要把链接存储下 生成最后执行ping百度的操作
        $pingurls = [];
        $tags = Commontool::getTags('article');
        foreach ($article_data as $key => $item) {
            //首先修改缩略图
            // 把 站点的相关的数据写入数据库中
            //获取上一篇和下一篇
            //获取上一篇
            //判断目录是否存在
            if (!file_exists('article')) {
                $this->make_error("article");
                return false;
            }
            $type_id = $item['articletype_id'];
            //取出相同分类的上一篇文章
            $pre_article_sql = "id <{$item['id']} and node_id=$this->node_id and articletype_id={$type_id}";
            $pre_article = Article::where($pre_article_sql)->field("id,title")->order("id", "desc")->find();
            //上一页链接
            if ($pre_article) {
                $pre_article = $pre_article->toArray();
                $pre_article['href'] = sprintf($this->prearticlepath, $pre_article['id']);
            }
            //获取相同分类的下一篇文章用于生成
            $next_article = [];
            //获取下一篇 的网址
            if ($key < $step_limit) {
                //最后一条 不需要有 下一页 需要判断下 是不是下一篇包含最大id
                $next_article_sql = "id >{$item['id']} and id<={$max_id} and node_id={$this->node_id} and articletype_id={$type_id}";
                $next_article = Article::where($next_article_sql)->field("id,title")->find();
            }
            //下一页链接
            if ($next_article) {
                $next_article = $next_article->toArray();
                $next_article['href'] = sprintf($this->prearticlepath, $next_article['id']);
//                    "/article/article{$next_article['id']}.html";
            }
            //需要传递下
            $keyword_id = $article_type_keyword[$type_id]['keyword_id'];
            $menu_id = $article_type_keyword[$type_id]['menu_id'];
            $menu_name = $article_type_keyword[$type_id]['menu_name'];
            //需要列出该文章同类相关tag的推荐内容
            $tagsArticleList = $this->getTagArticleList($item['tags'], $articletype_idstr, $article_typearr);
            $assign_data = $this->form_perarticle_content($item, $keyword_id, $menu_id, $menu_name, $tags);
            //如果没有设置模板 则使用默认模板
            $data = [
                'd' => $assign_data,
                'page' => $item,
                'pre_page' => $pre_article,
                'next_page' => $next_article,
                'relevant_pages' => $tagsArticleList
            ];
            $template = $this->getTemplate('detail', $menu_id, 'article');
            //判断模板是否存在
            if (!$this->fileExists($template)) {
                continue;
            }
            $content = Common::Debug((new View())->fetch($template,
                $data
            ), $data);
            $article_path = sprintf($this->articlepath, $item['id']);
            if (file_put_contents($article_path, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
                //需要把 每一次的都修改下
                array_push($pingurls, $this->siteurl . '/' . $article_path);
                ArticleSyncCount::where($where)->update(['count' => $item['id']]);
            }
        }
        $this->urlsCache($pingurls);
    }


    /**
     * 获取制定文章tag 的列表
     * @access public
     * @param $tags 标签
     * @param $articletype_idstr 该站点选择的文章类型列表
     * @param $node_id 节点的node_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTagArticleList($tags, $articletype_idstr, $article_typearr, $limit = 10)
    {
        $tags = array_filter(explode(',', $tags));
        if (!$tags) {
            //tag 没有选择的情况
            return [];
        }
        $where = " node_id=$this->node_id and articletype_id in ($articletype_idstr) and (%s) ";
        $tagwhere = '';
        foreach ($tags as $k => $v) {
            $seperator = ' ';
            if ($tagwhere) {
                $seperator = ' or ';
            }
            $tagwhere .= $seperator . " tags like '%,$v,%' ";
        }
        $where = sprintf($where, $tagwhere);
        $tagsArticleList = Article::Where($where)->limit($limit)->field(Commontool::$articleListField)->select();
        if ($tagsArticleList) {
            Commontool::formatArticleList($tagsArticleList, $article_typearr);
            return $tagsArticleList;
        }
        return [];
    }


    /**
     * 生成单独的文章内容 预览跟重新生成的时候会
     * @access public
     */
    public function form_perarticle_content(&$item, $keyword_id, $menu_id, $menu_name, $tags)
    {
        //截取出 页面的 description 信息
        $description = mb_substr(strip_tags($item['content']), 0, 200);
        $description = preg_replace('/^&.+\;$/is', '', $description);
        //页面的描述
        $summary = $description ?: $item['summary'];
        //页面的描述
        $keywords = $item['keywords'];
        //获取网站的 tdk 文章列表等相关 公共元素
        $assign_data = Commontool::getEssentialElement('detail', $item['title'], $summary, $keywords, $keyword_id, $menu_id, $menu_name, 'articlelist');
        if ($item['thumbnails_name']) {
            //表示是oss的
            $this->get_osswater_img($item['thumbnails'], $item['thumbnails_name'], $this->waterString,$this->waterImgUrl);
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
                    $imgname = $this->formUniqueString();
                    //有时候有图片没有后缀
                    $filetype = $this->analyseUrlFileType($v);
                    //阿里云图片生成
                    $filepath = $imgname . '.' . $filetype;
                    if ($this->get_osswater_img($v, $filepath, $this->waterString,$this->waterImgUrl)) {
                        $content = str_replace($v, '/images/' . $filepath, $content);
                    }
                }
            }
        }
        return $content;
    }


    /**
     * 获取文章详细信息相关
     * @access public
     */
    public function article_detailinfo($id)
    {
        // 取出指定id的文章
        $articlesql = "id = $id and node_id=$this->node_id";
        $article = Article::where($articlesql)->find()->toArray();
        $type_id = $article['articletype_id'];
        // 获取menu信息
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        list($pre_article, $next_article) = $this->get_article_prenextinfo($id, $type_id);
        //需要去除该站点的tag相关列表
        $article_typearr = array_key_exists('article', $this->typeid_arr) ? $this->typeid_arr['article'] : [];
        $articletype_idstr = implode(',', array_keys($article_typearr));
        $tagsArticleList = $this->getTagArticleList($article['tags'], $articletype_idstr, $article_typearr);
        $tags = Commontool::getTags('article');
        $assign_data = $this->form_perarticle_content($article, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name'], $tags);
        $template = $this->getTemplate('detail', $menuInfo['id'], 'article');
        $data = [
            'd' => $assign_data,
            'page' => $article,
            'pre_page' => $pre_article,
            'next_page' => $next_article,
            'relevant_pages' => $tagsArticleList
        ];
        return [$template, $data];
    }


    /**
     * 获取文章上一页下一页
     * @access private
     */
    private function get_article_prenextinfo($id, $type_id)
    {
        $pre_article_sql = "id <{$id} and node_id=$this->node_id and articletype_id=$type_id";
        $pre_article = Article::where($pre_article_sql)->field("id,title")->order("id", "desc")->find();
        //上一页链接
        if ($pre_article) {
            $pre_article = $pre_article->toArray();
            $pre_article = ['href' => sprintf($this->prearticlepath, $pre_article['id']), 'title' => $pre_article['title']];
        }
        //获取下一篇 的网址
        //最后一条 不需要有 下一页
        $next_article_sql = "id >{$id} and node_id=$this->node_id and articletype_id=$type_id";
        $next_article = Article::where($next_article_sql)->field("id,title")->find();
        //下一页链接
        if ($next_article) {
            $next_article = $next_article->toArray();
            $next_article['href'] = sprintf($this->prearticlepath, $id);
        }
        return [$pre_article, $next_article];
    }


    /**
     * 问答详情页面的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     * @todo 需要指定生成问答数量的数量
     * @param $site_id 站点的id
     * @param $site_name 站点名
     * @param $node_id 节点的id
     * @param $article_type_keyword 问答分类id 所对应的A类关键词
     * @param $type_id 问答的分类id
     * @param $a_keyword_id 栏目所对应的a类 关键词
     */
    private function questionstatic($question_type_keyword, $step_limit, $question_typearr)
    {
        $type_name = "question";
        $where = [
            'type_name' => $type_name,
            "node_id" => $this->node_id,
            "site_id" => $this->site_id
        ];
        $pre_stop = 0;
        //获取 站点 某个栏目同步到的文章id
        $articleCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($articleCount->count) && $articleCount->count >= 0) {
            $pre_stop = $articleCount->count;
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
        $questiontype_idstr = implode(',', array_keys($question_type_keyword));
        //删除掉是否同步功能
        //获取 所有允许同步的sync=20的  还有这个 站点添加的数据20  把 上次的最后一条数据取出来
        $question_list_sql = "id >= $pre_stop and node_id=$this->node_id and type_id in ($questiontype_idstr)";
        // 要 step_limit+1 因为要 获取上次的最后一条 最后一条的下一篇需要重新生成链接
        $question_data = \app\index\model\Question::where($question_list_sql)->order("id", "asc")->limit($step_limit + 1)->select();
        if ($question_data) {
            //获取本次最大的id，用于比对是不是有下一篇
            $max_index = max(array_flip(array_keys($question_data)));
            $max_id = $question_data[$max_index]['id'];
        }
        $pingurls = [];
        $tags = Commontool::getTags('question');
        foreach ($question_data as $key => $item) {
            //判断目录是否存在
            if (!file_exists('question')) {
                $this->make_error("question");
                return false;
            }
            $type_id = $item['type_id'];
            //页面中还需要填写隐藏的 表单 node_id site_id
            //获取上一篇和下一篇
            $pre_question = \app\index\model\Question::where(["id" => ["lt", $item['id']], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->order("id", "desc")->find();
            if ($pre_question) {
                $pre_question = $pre_question->toArray();
                $pre_question['href'] = sprintf($this->prequestionpath, $pre_question['id']);
            }
            $next_question = [];
            if ($key < $step_limit) {
                $next_question = \app\index\model\Question::where(["id" => ["between", [$item['id'], $max_id]], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->find();
            }
            if ($next_question) {
                $next_question = $next_question->toArray();
                $next_question['href'] = sprintf($this->prequestionpath, $next_question['id']);
            }
            $keyword_id = $question_type_keyword[$type_id]['keyword_id'];
            $menu_id = $question_type_keyword[$type_id]['menu_id'];
            $menu_name = $question_type_keyword[$type_id]['menu_name'];
            $tagsQuestionList = $this->getTagQuestionList($item['tags'], $questiontype_idstr, $question_typearr);
            $assign_data = $this->form_perquestion($item, $keyword_id, $menu_id, $menu_name, $tags);
            $template = $this->getTemplate('detail', $menu_id, 'question');
            //判断模板是否存在
            if (!$this->fileExists($template)) {
                continue;
            }
            $data = [
                'd' => $assign_data,
                'page' => $item,
                'pre_page' => $pre_question,
                'next_page' => $next_question,
                'relevant_pages' => $tagsQuestionList
            ];
            $content = Common::Debug((new View())->fetch($template,
                $data
            ), $data);
            //开始同步数据库
            $question_path = sprintf($this->questionpath, $item['id']);
            if (file_put_contents($question_path, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
                array_push($pingurls, $this->siteurl . '/' . $question_path);
                ArticleSyncCount::where($where)->update(['count' => $item['id']]);
            }
        }
        $this->urlsCache($pingurls);
    }

    /**
     * 问答预览重新生成时候调取数据需要的数据
     */
    public function question_detailinfo($id)
    {
        $questionsql = "id = $id and node_id=$this->node_id";
        $question = Question::where($questionsql)->find()->toArray();
        // 获取menu信息
        $type_id = $question['type_id'];
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        list($pre_question, $next_question) = $this->get_question_prenextinfo($id, $type_id);
        //获取站点的tag 信息
        $question_typearr = array_key_exists('question', $this->typeid_arr) ? $this->typeid_arr['question'] : [];
        $questiontype_idstr = implode(',', array_keys($question_typearr));
        $tagsQuestionList = $this->getTagArticleList($question['tags'], $questiontype_idstr, $question_typearr);
        $tags = Commontool::getTags('article');
        $assign_data = $this->form_perquestion($question, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name'], $tags);
        $template = $this->getTemplate('detail', $menuInfo['id'], 'question');
        $data = [
            'd' => $assign_data,
            'page' => $question,
            'pre_page' => $pre_question,
            'next_page' => $next_question,
            'relevant_pages' => $tagsQuestionList
        ];
        return [$template, $data];
    }


    /**
     * 获取文章上一页下一页
     * @access private
     */
    private function get_question_prenextinfo($id, $type_id)
    {
        //获取上一篇和下一篇
        $pre_question = Question::where(["id" => ["lt", $id], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->order("id", "desc")->find();
        if ($pre_question) {
            $pre_question['href'] = sprintf($this->prequestionpath, $pre_question['id']);
        }
        //下一篇可能会导致其他问题
        $next_question = Question::where(["id" => ["gt", $id], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->find();
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
        $tags = array_filter(explode(',', $tags));
        if (!$tags) {
            //tag 没有选择的情况
            return [];
        }
        $where = " node_id=$this->node_id and type_id in ($questiontype_idstr) and (%s) ";
        $tagwhere = '';
        foreach ($tags as $k => $v) {
            $seperator = ' ';
            if ($tagwhere) {
                $seperator = ' or ';
            }
            $tagwhere .= $seperator . " tags like '%,$v,%' ";
        }
        $where = sprintf($where, $tagwhere);
        $tagsQuestionList = Question::where($where)->limit($limit)->field(Commontool::$questionListField)->select();
        if ($tagsQuestionList) {
            Commontool::formatQuestionList($tagsQuestionList, $question_typearr);
            return $tagsQuestionList;
        }
        return [];
    }


    /**
     * 格式化每个问题页面
     * @access public
     */
    public function form_perquestion(&$item, $keyword_id, $menu_id, $menu_name, $tags)
    {
        $description = $item['description'];
        $description = $description ?: mb_substr(strip_tags($item['content_paragraph']), 0, 200);
        //页面的描述
        $keywords = $item['keywords'];
        $item['content_paragraph'] = $this->form_content_img($item['content_paragraph']);
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
        $assign_data = Commontool::getEssentialElement('detail', $item['question'], $description, $keywords, $keyword_id, $menu_id, $menu_name, 'questionlist');
        return $assign_data;
    }


    /**
     * 文章详情页面的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面 产品呢是一次性生成的
     * @param $site_id 站点的id
     * @param $site_name 站点名
     * @param $node_id 节点的id
     * @param $article_type_keyword 文章分类id 所对应的A类关键词
     */
    private function productstatic($product_type_keyword, $step_limit, $product_typearr)
    {
        $type_name = "product";
        $where = [
            'type_name' => $type_name,
            "node_id" => $this->node_id,
            "site_id" => $this->site_id
        ];
        $pre_stop = 0;
        //获取 站点 某个栏目同步到的文章id
        $productCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($productCount->count) && $productCount->count >= 0) {
            $pre_stop = $productCount->count;
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
        $producttype_idstr = implode(',', array_keys($product_type_keyword));
        $productsql = "id >= $pre_stop and node_id=$this->node_id and type_id in ($producttype_idstr)";
        // 要 step_limit+1 因为要 获取上次的最后一条
        $product_data = \app\index\model\Product::where($productsql)->order("id", "asc")->select();
        if ($product_data) {
            $max_index = max(array_flip(array_keys($product_data)));
            $max_id = $product_data[$max_index]['id'];
        }
        $pingurls = [];
        $tags = Commontool::getTags('product');
        foreach ($product_data as $key => $item) {
            if (!file_exists('product')) {
                $this->make_error("product");
                return false;
            }
            $type_id = $item['type_id'];
            // 把 站点的相关的数据写入数据库中
            //获取上一篇和下一篇
            //获取上一篇
            $pre_productcommon_sql = "id <{$item['id']} and node_id=$this->node_id and type_id=$type_id ";
            $pre_product = Product::where($pre_productcommon_sql)->field("id,name,image_name")->order("id", "desc")->find();
            //上一页链接
            if ($pre_product) {
                $pre_product = ['href' => sprintf($this->preproductpath, $pre_product['id']), 'img' => "<img src='/images/{$pre_product['image_name']}' alt='{$pre_product['name']}'>", 'title' => $pre_product['name']];
            }
            if ($key < $step_limit) {
                //最后一条 不需要有 下一页 需要判断下 是不是下一篇包含最大id
                $next_productcommon_sql = "id >{$item['id']} and id<={$max_id} and node_id=$this->node_id and type_id={$type_id} ";
                $next_product = Product::where($next_productcommon_sql)->field("id,name,image_name")->find();
            }
            //下一页链接
            if ($next_product) {
                $next_product = ['href' => sprintf($this->preproductpath, $next_product['id']), 'img' => "<img src='/images/{$next_product['image_name']}' alt='{$next_product['name']}'>", 'title' => $next_product['name']];
            }
            //获取tags 页面相关数据
            $keyword_id = $product_type_keyword[$type_id]['keyword_id'];
            $menu_id = $product_type_keyword[$type_id]['menu_id'];
            $menu_name = $product_type_keyword[$type_id]['menu_name'];
            $tagsProductList = $this->getTagProductList($item['tags'], $producttype_idstr, $product_typearr);
            $assign_data = $this->form_perproduct_content($item, $keyword_id, $menu_id, $menu_name, $tags);
            $data = [
                'd' => $assign_data,
                'page' => $item,
                'pre_page' => $pre_product,
                'next_page' => $next_product,
                'relevant_pages' => $tagsProductList
            ];
            $template = $this->getTemplate('detail', $menu_id, 'product');
            //判断模板是否存在
            if (!$this->fileExists($template)) {
                continue;
            }
            $content = Common::Debug((new View())->fetch($template,
                $data
            ), $data);
            //判断目录是否存在
            //开始同步数据库
            $productpath = sprintf($this->productpath, $item['id']);
            if (file_put_contents($productpath, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
                array_push($pingurls, $this->siteurl . '/' . $productpath);
                ArticleSyncCount::where($where)->update(['count' => $item['id']]);
            }
        }
        $this->urlsCache($pingurls);
    }

    /**
     * 生成单个产品 因为不用考虑定期生成 多少篇
     * @access public
     */
    public function form_perproduct_content($item, $keyword_id, $menu_id, $menu_name, $tags)
    {
        //截取出 页面的 description 信息
        $description = mb_substr(strip_tags($item['summary']), 0, 200);
        $description = preg_replace('/^&.+\;$/is', '', $description);
        $summary = $item['summary'] ?: $description;
        $keywords = $item['keywords'];
        //获取网站的 tdk 文章列表等相关 公共元素
        $assign_data = Commontool::getEssentialElement('detail', $item['name'], $summary, $keywords, $keyword_id, $menu_id, $menu_name, 'productlist');
        if ($item['image_name']) {
            $this->get_osswater_img($item['image'], $item['image_name'], $this->waterString,$this->waterImgUrl);
        }
        //替换图片 base64 为 图片文件
        $item['detail'] = $this->form_content_img($item['detail']);
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
        $tags = array_filter(explode(',', $tags));
        if (!$tags) {
            //tag 没有选择的情况
            return [];
        }
        $where = " node_id=$this->node_id and type_id in ($producttype_idstr) and (%s) ";
        $tagwhere = '';
        foreach ($tags as $k => $v) {
            $seperator = ' ';
            if ($tagwhere) {
                $seperator = ' or ';
            }
            $tagwhere .= $seperator . " tags like '%,$v,%' ";
        }
        $where = sprintf($where, $tagwhere);
        $tagsProductList = Product::where($where)->limit($limit)->field(Commontool::$productListField)->select();
        if ($tagsProductList) {
            Commontool::formatProductList($tagsProductList, $produt_typearr);
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
        $product = Product::where($productsql)->find()->toArray();
        $type_id = $product['type_id'];
        // 获取menu信息
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        list($pre_product, $next_product) = $this->get_product_prenextinfo($id, $type_id);
        //需要去除该站点的tag相关列表
        $product_typearr = array_key_exists('product', $this->typeid_arr) ? $this->typeid_arr['product'] : [];
        $producttype_idstr = implode(',', array_keys($product_typearr));
        $tags = Commontool::getTags('product');
        $tagsArticleList = $this->getTagProductList($product['tags'], $producttype_idstr, $product_typearr);
        $assign_data = $this->form_perproduct_content($product, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name'], $tags);
        $template = $this->getTemplate('detail', $menuInfo['id'], 'product');
        $data = [
            'd' => $assign_data,
            'page' => $product,
            'pre_page' => $pre_product,
            'next_page' => $next_product,
            'relevant_pages' => $tagsArticleList
        ];
        return [$template, $data];
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
        $pre_productcommon_sql = "id <{$id} and node_id=$this->node_id and type_id={$type_id} ";
        $pre_product = Product::where($pre_productcommon_sql)->field("id,name,image_name")->order("id", "desc")->find();
        //上一页链接
        if ($pre_product) {
            $pre_product = $pre_product->toArray();
            $pre_product = ['href' => sprintf($this->preproductpath, $pre_product['id']), 'img' => "<img src='/images/{$pre_product['image_name']}' alt='{$pre_product['name']}'>", 'title' => $pre_product['name']];
        }
        //最后一条 不需要有 下一页 需要判断下 是不是下一篇包含最大id
        $next_productcommon_sql = "id >{$id} and node_id=$this->node_id and type_id={$type_id} ";
        $next_product = Product::where($next_productcommon_sql)->field("id,name,image_name")->find();
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
            if ($this->get_osswater_img($osssrc, $imgname, $this->waterString,$this->waterImgUrl)) {
                array_push($local_imgarr, '/images/' . $imgname);
            }
        }
        return $local_imgarr;
    }

}
