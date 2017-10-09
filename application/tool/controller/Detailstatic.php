<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\model\ArticleSyncCount;
use app\index\model\Articletype;
use app\index\model\ScatteredTitle;
use think\Cache;
use think\Db;
use think\View;

/**
 * 详情页 静态化 比如 文章 之类
 * 执行详情页的静态化相关操作
 */
class Detailstatic extends Common
{
    use FileExistsTraits;

    private static $system_default_count;

    public function __construct()
    {
        $data = Db::name('system_config')->where(['name' => 'SYSTEM_DEFAULTSTATIC_COUNT', 'need_auth' => 0])->field('value')->find();
        if ($data && array_key_exists('value', $data)) {
            self::$system_default_count = $data['value'];
        } else {
            //如果没有设置该字段 则默认生成五篇
            self::$system_default_count = 5;
        }
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
        if (array_key_exists('scatteredarticle', $config_sync_info)) {
            foreach ($config_sync_info['scatteredarticle'] as $k => $v) {
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
                    $scattered_count = $v['staticcount'];
                    $scattered_status = true;
                    self::set_laststatic_time($v['id']);
                    break;
                }
            }
        } else {
            //没有相关配置的话 默认是5条
            $scattered_status = true;
        }
        return [$article_status, intval($article_count), $question_status, intval($question_count), $scattered_status, intval($scattered_count)];
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
//        set_time_limit(0);
//        ignore_user_abort();
        // 获取站点的相关的相关信息
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        //获取  文章分类 还有 对应的pageinfo中的 所选择的A类关键词
        //获取 site页面 中 menu 指向的 a_keyword_id
        //从数据库中 获取的菜单对应的a_keyword_id 信息 可能有些菜单 还没有存储到数据库中 如果是第一次请求的话
        $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');
        //菜单 typeid_arr 根据栏目的分类 返回 menu 的信息
        $menu_typeid_arr = Menu::getTypeIdInfo($siteinfo['menu']);
        //验证下 是不是这个时间段内 是不是可以生成
        list($articlestatic_status, $articlestatic_count, $questionstatic_status, $questionstatic_count, $scatteredstatic_status, $scatteredstatic_count) = self::check_static_time($site_id, $requesttype);
        //区分菜单所属栏目是哪种类型  article question scatteredstatic
        $article_type_keyword = [];
        $question_type_keyword = [];
        $scatteredarticle_type_keyword = [];
        $product_type_keyword = [];
        foreach ($menu_typeid_arr as $detail_key => $v) {
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
                    $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $site_id, $site_name, $node_id);
                    //菜单 页面的TDK
                    Commontool::getMenuPageTDK($keyword_info, $menu_info->generate_name, $menu_info->name, $site_id, $site_name, $node_id, $type['menu_id'], $menu_info->name);
                    $menu_akeyword_id_arr = Db::name('SitePageinfo')->where(['site_id' => $site_id, 'menu_id' => ['neq', 0]])->column('menu_id,akeyword_id');
                }
                $a_keyword_id = $menu_akeyword_id_arr[$type['menu_id']];
                switch ($detail_key) {
                    case'article':
                        if ($articlestatic_status) {
                            $article_type_keyword[] = ['type_id' => $type['id'], 'menu_id' => $type['menu_id'], 'menu_name' => $type['menu_name'], 'keyword_id' => $a_keyword_id];
                        }
                        break;
                    case'question':
                        if ($questionstatic_status) {
                            $question_type_keyword[] = ['type_id' => $type['id'], 'menu_id' => $type['menu_id'], 'menu_name' => $type['menu_name'], 'keyword_id' => $a_keyword_id];
                        }
                        break;
                    case'scatteredarticle':
                        if ($scatteredstatic_status) {
                            $scatteredarticle_type_keyword[] = ['type_id' => $type['id'], 'menu_id' => $type['menu_id'], 'menu_name' => $type['menu_name'], 'keyword_id' => $a_keyword_id];
                        }
                        break;
                    case 'product':
                        //产品类型 不需要限制生成数量一次性添加就可
                        $product_type_keyword[] = ['type_id' => $type['id'], 'menu_id' => $type['menu_id'], 'menu_name' => $type['menu_name'], 'keyword_id' => $a_keyword_id];
                        break;
                }
            }
        }
        if ($article_type_keyword && $articlestatic_count) {
            $this->articlestatic($site_id, $site_name, $node_id, $article_type_keyword, $articlestatic_count);
        }
        if ($question_type_keyword && $questionstatic_count) {
            $this->questionstatic($site_id, $site_name, $node_id, $question_type_keyword, $questionstatic_count);
        }
        if ($scatteredarticle_type_keyword && $scatteredstatic_count) {
            $this->scatteredarticlestatic($site_id, $site_name, $node_id, $scatteredarticle_type_keyword, $scatteredstatic_count);
        }
        if ($product_type_keyword) {
            $this->productstatic($site_id, $site_name, $node_id, $product_type_keyword);
        }
    }

    /**
     * 文章详情页面的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     * @todo 需要指定生成文章数量的数量
     * @param $site_id 站点的id
     * @param $site_name 站点名
     * @param $node_id 节点的id
     * @param $article_type_keyword 文章分类id 所对应的A类关键词
     * @param $type_id 文章的分类id
     * @param $a_keyword_id 栏目所对应的a类 关键词
     */
    private function articlestatic($site_id, $site_name, $node_id, $article_type_keyword, $step_limit)
    {
        //判断模板是否存在
        if (!$this->fileExists('template/article.html')) {
            return;
        }
        $static_count = 0;
        foreach ($article_type_keyword as $v) {
            //计算出该栏目需要静态化的数量
            $count = $step_limit - $static_count;
            if ($count > 0) {
                $step_count = $this->exec_articlestatic($site_id, $site_name, $node_id, $v['type_id'], $v['keyword_id'], $v['menu_id'], $v['menu_name'], $count);
                if ($step_count !== false) {
                    $static_count = $static_count + $step_count;
                } else {
                    break;
                }
            }
        }
    }


    /**
     * 执行页面静态化相关操作
     * @access private
     * @return count 返回生成文章的数量
     */
    private function exec_articlestatic($site_id, $site_name, $node_id, $type_id, $keyword_id, $menu_id, $menu_name, $step_limit)
    {
        $siteinfo = Site::getSiteInfo();
        $type_name = "article";
        $where = [
            'type_id' => $type_id,
            'type_name' => $type_name,
            "node_id" => $node_id,
            "site_id" => $site_id
        ];
        $pre_stop = 0;
        //获取 站点 某个栏目同步到的文章id
        $articleCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($articleCount->count) && $articleCount->count > 0) {
            $pre_stop = $articleCount->count;
        } else {
            // 没有获取到 某个栏目静态化到的网址 后续需要添加一个
            $article_sync = new ArticleSyncCount();
        }
        //获取 所有允许同步的sync=20的  还有这个 站点添加的数据20  把 上次的最后一条数据取出来
        $commonsql = "id >= $pre_stop and node_id=$node_id and articletype_id=$type_id and";
        $article_list_sql = "($commonsql is_sync=20 ) or  ($commonsql site_id = $site_id)";
        // 要 step_limit+1 因为要 获取上次的最后一条
        $article_data = \app\index\model\Article::where($article_list_sql)->order("id", "asc")->limit($step_limit + 1)->select();
        // 如果有数据的话清除掉列表的缓存
        if (isset($article_data)) {
            Cache::clear();
        }
        $static_count = 0;
        $pingBaidu=[];
        foreach ($article_data as $key => $item) {
            //截取出 页面的 description 信息
            $description = mb_substr(strip_tags($item->content), 0, 200);
            preg_replace('/^&.+\;$/is', '', $description);
            //获取网站的 tdk 文章列表等相关 公共元素
            $assign_data = Commontool::getEssentialElement('detail', $item->title, $description, $keyword_id, $menu_id, $menu_name, 'articlelist');
            // 把 站点的相关的数据写入数据库中
            // file_put_contents('log/article.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //获取上一篇和下一篇
            //获取上一篇
            $pre_articlecommon_sql = "id <{$item['id']} and node_id=$node_id and articletype_id=$type_id and ";
            $pre_article_sql = "($pre_articlecommon_sql is_sync=20 ) or  ( $pre_articlecommon_sql site_id = $site_id)";
            $pre_article = \app\index\model\Article::where($pre_article_sql)->field("id,title")->order("id", "desc")->find();
            //上一页链接
            if ($pre_article) {
                $pre_article = ['href' => "/article/article{$pre_article['id']}.html", 'title' => $pre_article['title']];
            }
            //获取下一篇
            $next_article = [];
            //获取下一篇 的网址
            if ($key < $step_limit) {
                //最后一条 不需要有 下一页
                $next_articlecommon_sql = "id >{$item['id']} and node_id=$node_id and articletype_id=$type_id and ";
                $next_article_sql = "($next_articlecommon_sql is_sync=20 ) or  ( $next_articlecommon_sql site_id = $site_id)";
                $next_article = \app\index\model\Article::where($next_article_sql)->field("id,title")->find();
            }
            //下一页链接
            if ($next_article) {
                $next_article = $next_article->toArray();
                $next_article['href'] = "/article/article{$next_article['id']}.html";
            }
            // 首先需要把base64 缩略图 生成为 文件
//            $water = $assign_data['site_name'] . ' ' . $assign_data['url'];
            $water = $siteinfo['walterString'];
            if ($item->thumbnails_name) {
                //存在 base64缩略图 需要生成静态页
                preg_match_all('/<img[^>]+src\s*=\\s*[\'\"]([^\'\"]+)[\'\"][^>]*>/i', $item->thumbnails, $match);
                if (!empty($match)) {
                    $this->form_img_frombase64($match[1][0], $item->thumbnails_name, $water);
                }
            }
            //替换图片 base64 为 图片文件
            $temp_content = $this->form_img($item->content, $water);
            // 替换关键词为指定链接 遍历全文和所有关键词
            $temp_content = $this->articleReplaceKeyword($temp_content);
            // 替换关键字
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
            //判断目录是否存在
            if (!file_exists('article')) {
                $this->make_error("article");
                return false;
            }
            $make_web = file_put_contents('article/article' . $item["id"] . '.html', chr(0xEF) . chr(0xBB) . chr(0xBF) . $content);
            //开始同步数据库
            if ($make_web) {
                $articleCountModel = ArticleSyncCount::where($where)->find();
                if (is_null($articleCountModel)) {
                    $article_sync->count = $item["id"];
                    $article_sync->type_id = $type_id;
                    $article_sync->type_name = $type_name;
                    $article_sync->node_id = $node_id;
                    $article_sync->site_id = $site_id;
                    $article_sync->site_name = $site_name;
                    $article_sync->save();
                } else {
                    $articleCountModel->count = $item["id"];
                    $articleCountModel->save();
                }
            }
            // ping baidu 数组存放
            $pingBaidu[]= $siteinfo["url"]."/article/article".$item["id"] . '.html';
            $static_count++;
        }
//        $this->pingBaidu($pingBaidu);
        // 请求当前网站列表页 提前生成列表静态化页面
//        $curl=$siteinfo["url"]."/".$type_name.'/'.$type_id."html";
//        $this->curl_get($curl);
        return $static_count - 1;
    }

    /**
     * 根据内容生成图片
     * @access public
     */
    public function form_img($content, $water)
    {
        //从中提取出 base64 中的内容
        //使用正则匹配
        //匹配base64 文件类型
        preg_match_all('/<img[^>]+src\s*=\\s*[\'\"]([^\'\"]+)[\'\"][^>]*>/i', $content, $match);
        if (!empty($match)) {
            if (array_key_exists(1, $match)) {
                foreach ($match[1] as $k => $v) {
                    $img_name = md5(uniqid(rand(), true));
                    list($file_name, $status) = $this->form_img_frombase64($v, $img_name, $water);
                    //需要替换掉内容中的数据
                    if ($status) {
                        $content = str_replace($v, $file_name, $content);
                    }
                }
            }
        }
        return $content;
    }


    /**
     * 根据base64 生成图片
     * @access public
     * @param $base64img data:image/png;base64,***********
     * @param $img_name ***.jpg  ***.png
     * @param $water 水印字符串站点名+域名
     */
    private function form_img_frombase64($base64img, $img_name, $water)
    {

        //保存base64字符串为图片
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64img, $result)) {
            //匹配出 图片的类型
            $new_file = "images/$img_name";
            if (strpos($img_name, '.') === false) {
                $type = $result[2];
                $new_file = "images/$img_name" . '.' . $type;
            } else {
                //生成缩略图
                $info = pathinfo($img_name);
                $type = $info['extension'];
            }
            //获取图片的位置
            $ttcPath = dirname(THINK_PATH) . '/6.ttc';
            $dst = imagecreatefromstring(base64_decode(str_replace($result[1], '', $base64img)));
            //水印颜色
//            $color = imagecolorallocatealpha($dst, 255, 255, 255, 30);
            $color = imagecolorallocatealpha($dst, 197, 37, 19, 30);
            //添加水印
            imagettftext($dst, 12, 0, 10, 22, $color, $ttcPath, $water);
            $func = "image{$type}";
            $create_status = $func($dst, $new_file);
            imagedestroy($dst);
            if ($create_status) {
                return ['/' . $new_file, true];
            }
            return ['/' . $new_file, false];
        }
    }


    /**
     * 文章详情页面的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     * @todo 需要指定生成文章数量的数量
     * @param $site_id 站点的id
     * @param $site_name 站点名
     * @param $node_id 节点的id
     * @param $article_type_keyword 文章分类id 所对应的A类关键词
     * @param $type_id 文章的分类id
     * @param $a_keyword_id 栏目所对应的a类 关键词
     */
    private function scatteredarticlestatic($site_id, $site_name, $node_id, $article_type_keyword, $step_limit)
    {
        //判断模板是否存在
        if (!$this->fileExists('template/news.html')) {
            return;
        }
        $static_count = 0;
        foreach ($article_type_keyword as $v) {
            //计算出该栏目需要静态化的数量
            $count = $step_limit - $static_count;
            if ($count > 0) {
                $step_count = $this->exec_scatteredarticlestatic($site_id, $site_name, $node_id, $v['type_id'], $v['keyword_id'], $v['menu_id'], $v['menu_name'], $count);
                if ($step_count !== false) {
                    $static_count = $static_count + $static_count;
                } else {
                    break;
                }
            }
        }
    }

    /**
     * 零散文章的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     * @param $type_id 零散段落的分类id
     * @param $keyword_id 栏目所对应的a类 关键词
     * @param $site_id 站点id
     * @param $site_name 站点name
     * @param $node_id 节点id
     */
    public function exec_scatteredarticlestatic($site_id, $site_name, $node_id, $type_id, $keyword_id, $menu_id, $menu_name, $step_limit)
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
        $pre_stop = 0;
        $articleCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($articleCount->count) && $articleCount->count > 0) {
            $pre_stop = $articleCount->count;
        } else {
            $article_temp = new ArticleSyncCount();
        }
        $scatTitleArray = (new ScatteredTitle())->where(["id" => ["egt", $pre_stop], "articletype_id" => $type_id])->limit($step_limit + 1)->select();
        if(isset($scatTitleArray)){
            Cache::clear();
        }
        $static_count = 0;
        foreach ($scatTitleArray as $key => $item) {
            $scatArticleArray = Db::name('ScatteredArticle')->where(["id" => ["in", $item->article_ids]])->column('content_paragraph');
            $temp_arr = $item->toArray();
            $temp_arr['content'] = implode('<br/>', $scatArticleArray);
            $temp_content = mb_substr(strip_tags($temp_arr['content']), 0, 200);
            $assign_data = Commontool::getEssentialElement('detail', $temp_arr["title"], $temp_content, $keyword_id, $menu_id, $menu_name, 'newslist');
            //file_put_contents('log/scatteredarticle.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //页面中还需要填写隐藏的 表单 node_id site_id
            //获取上一篇和下一篇
            $pre_article = \app\index\model\ScatteredTitle::where(["id" => ["lt", $item["id"]], "node_id" => $node_id, "articletype_id" => $type_id])->field("id,title")->order("id", "desc")->find();
            if ($pre_article) {
                $pre_article['href'] = "/news/news{$pre_article['id']}.html";
            }
            $next_article = [];
            if ($key < $step_limit) {
                $next_article = \app\index\model\ScatteredTitle::where(["id" => ["gt", $item["id"]], "node_id" => $node_id, "articletype_id" => $type_id])->field("id,title")->find();
            }
            if ($next_article) {
                $next_article['href'] = "/news/news{$next_article['id']}.html";
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
                return false;
            }
            $make_web = file_put_contents('news/news' . $item["id"] . '.html', chr(0xEF) . chr(0xBB) . chr(0xBF) . $content);
            //开始同步数据库
            if ($make_web) {
                $articleCountModel = ArticleSyncCount::where($where)->find();
                if (is_null($articleCountModel)) {
                    //之前 栏目生成配置 记录没有添加过
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
            $static_count++;
        }
        return $static_count - 1;
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
    private function questionstatic($site_id, $site_name, $node_id, $question_type_keyword, $step_limit)
    {
        //判断模板是否存在
        if (!$this->fileExists('template/question.html')) {
            return;
        }
        $static_count = 0;
        foreach ($question_type_keyword as $v) {
            //计算出该栏目需要静态化的数量
            $count = $step_limit - $static_count;
            if ($count > 0) {
                $step_count = $this->exec_questionstatic($site_id, $site_name, $node_id, $v['type_id'], $v['keyword_id'], $v['menu_id'], $v['menu_name'], $count);
                if ($step_count !== false) {
                    $static_count = $static_count + $step_count;
                } else {
                    break;
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
    public function exec_questionstatic($site_id, $site_name, $node_id, $type_id, $keyword_id, $menu_id, $menu_name, $step_limit)
    {
        $siteinfo = Site::getSiteInfo();
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
        $pre_stop = 0;
        $questionCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型  需要减去1 因为要将以前最后一页重新生成
        if (isset($questionCount->count) && $questionCount->count > 0) {
            $pre_stop = $questionCount->count;
        } else {
            $question_sync = new ArticleSyncCount();
        }
        $question_data = \app\index\model\Question::where(["id" => ["egt", $pre_stop], "type_id" => $type_id, "node_id" => $node_id])->order("id", "asc")->limit($step_limit + 1)->select();
        if(isset($question_data)){
            Cache::clear();
        }
        $static_count = 0;
        foreach ($question_data as $key => $item) {
            $description = mb_substr(strip_tags($item->content_paragraph), 0, 200);
            $assign_data = Commontool::getEssentialElement('detail', $item->question, $description, $keyword_id, $menu_id, $menu_name, 'questionlist');
            //file_put_contents('log/question.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //页面中还需要填写隐藏的 表单 node_id site_id
            //获取上一篇和下一篇
            $pre_question = \app\index\model\Question::where(["id" => ["lt", $item->id], "node_id" => $node_id, "type_id" => $type_id])->field("id,question as title")->order("id", "desc")->find();
            if ($pre_question) {
                $pre_question['href'] = "/question/question{$pre_question['id']}.html";
            }
            $next_question = [];
            if ($key < $step_limit) {
                $next_question = \app\index\model\Question::where(["id" => ["gt", $item->id], "node_id" => $node_id, "type_id" => $type_id])->field("id,question as title")->find();
            }
            if ($next_question) {
                $next_question['href'] = "/question/question{$next_question['id']}.html";
            }
            $content = (new View())->fetch('template/question.html',
                [
                    'd' => $assign_data,
                    'question' => $item,
                    'pre_article' => $pre_question,
                    'next_article' => $next_question
                ]
            );
            //判断目录是否存在
            if (!file_exists('question')) {
                $this->make_error("question");
                return false;
            }
            $make_web = file_put_contents('question/question' . $item["id"] . '.html', chr(0xEF) . chr(0xBB) . chr(0xBF) . $content);
            //开始同步数据库
            if ($make_web) {
                $articleCountModel = ArticleSyncCount::where($where)->find();
                if (is_null($articleCountModel)) {
                    $question_sync->count = $item["id"];
                    $question_sync->type_id = $type_id;
                    $question_sync->type_name = $type_name;
                    $question_sync->node_id = $node_id;
                    $question_sync->site_id = $site_id;
                    $question_sync->site_name = $site_name;
                    $question_sync->save();
                } else {
                    $articleCountModel->count = $item["id"];
                    $articleCountModel->save();
                }
            }
            $static_count++;
        }
        // 请求当前网站列表页 提前生成列表静态化页面
//        $curl=$siteinfo["url"]."/".$type_name.'/'.$type_id."html";
//        $this->curl_get($curl);
        return $static_count - 1;
    }


    /**
     * 文章详情页面的静态化
     * @access public
     * @todo 需要比对 哪个已经生成静态页面了  哪个没有生成静态页面
     * @todo 需要指定生成文章数量的数量
     * @param $site_id 站点的id
     * @param $site_name 站点名
     * @param $node_id 节点的id
     * @param $article_type_keyword 文章分类id 所对应的A类关键词
     * @param $type_id 文章的分类id
     * @param $a_keyword_id 栏目所对应的a类 关键词
     */
    private function productstatic($site_id, $site_name, $node_id, $article_type_keyword)
    {
        //判断模板是否存在
        if (!$this->fileExists('template/product.html')) {
            return;
        }
        foreach ($article_type_keyword as $v) {
            $this->exec_productstatic($site_id, $site_name, $node_id, $v['type_id'], $v['menu_id'], $v['menu_name'], $v['keyword_id']);
        }
    }


    /**
     * 执行页面静态化相关操作
     * @access private
     * @return count 返回生成文章的数量
     */
    private function exec_productstatic($site_id, $site_name, $node_id, $type_id, $menu_id, $menu_name, $keyword_id)
    {
        $siteinfo = Site::getSiteInfo();
        $type_name = "product";
        $where = [
            'type_id' => $type_id,
            'type_name' => $type_name,
            "node_id" => $node_id,
            "site_id" => $site_id
        ];
        $pre_stop = 0;
        //获取 站点 某个栏目同步到的文章id
        $productCount = ArticleSyncCount::where($where)->find();
        //判断下是否有数据 没有就创建模型
        if (isset($productCount->count) && $productCount->count > 0) {
            $pre_stop = $productCount->count;
        } else {
            // 没有获取到 某个栏目静态化到的网址 后续需要添加一个
            $article_sync = new ArticleSyncCount();
        }
        //获取 所有允许同步的sync=20的  还有这个 站点添加的数据20  把 上次的最后一条数据取出来
        $productsql = "id >= $pre_stop and node_id=$node_id and type_id=$type_id";
        // 要 step_limit+1 因为要 获取上次的最后一条
        $product_data = \app\index\model\Product::where($productsql)->order("id", "asc")->select();
        // 如果有数据的话清除掉列表的缓存
        if (isset($product_data)) {
            Cache::clear();
        }
        foreach ($product_data as $key => $item) {
            //截取出 页面的 description 信息
            $description = mb_substr(strip_tags($item->summary), 0, 200);
            preg_replace('/^&.+\;$/is', '', $description);
            //获取网站的 tdk 文章列表等相关 公共元素
            $assign_data = Commontool::getEssentialElement('detail', $item->name, $description, $keyword_id, $menu_id, $menu_name, 'productlist');
            // 把 站点的相关的数据写入数据库中
            // file_put_contents('log/article.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //获取上一篇和下一篇
            //获取上一篇
            $pre_product = [];
            $pre_productcommon_sql = "id <{$item['id']} and node_id=$node_id and type_id=$type_id ";
            $pre_product = \app\index\model\Product::where($pre_productcommon_sql)->field("id,name,image_name")->order("id", "desc")->find();
            //上一页链接
            if ($pre_product) {
                $pre_product = ['href' => "/product/product{$pre_product['id']}.html", 'img' => "<img src='/images/{$pre_product['image_name']}' alt='{$pre_product['name']}'>", 'title' => $pre_product['name']];
            }
            //获取下一篇
            $next_product = [];
            $next_productcommon_sql = "id >{$item['id']} and node_id=$node_id and type_id=$type_id ";
            $next_product = \app\index\model\Product::where($next_productcommon_sql)->field("id,name,image_name")->find();
            //下一页链接
            if ($next_product) {
                $next_product = ['href' => "/product/product{$next_product['id']}.html", 'img' => "<img src='/images/{$next_product['image_name']}' alt='{$next_product['name']}'>", 'title' => $next_product['name']];
            }
            // 首先需要把base64 缩略图 生成为 文件
//            $water = $assign_data['site_name'] . ' ' . $assign_data['url'];
            $water = $siteinfo['walterString'];
            if ($item->base64) {
                //存在 base64缩略图 需要生成静态页
                $this->form_img_frombase64($item->base64, $item->image_name, $water);
            }
            $content = (new View())->fetch('template/product.html',
                [
                    'd' => $assign_data,
                    'product' => ["name" => $item->name, "image" => "<img src='/images/{$item->image_name}' alt='{$item->name}'>", 'sn' => $item->sn, 'type_name' => $item->type_name, "summary" => $item->summary, "detail" => $item->detail, "create_time" => $item->create_time],
                    'pre_article' => $pre_product,
                    'next_article' => $next_product,
                ]
            );
            //判断目录是否存在
            if (!file_exists('product')) {
                $this->make_error("product");
                return false;
            }
            $make_web = file_put_contents('product/product' . $item["id"] . '.html', chr(0xEF) . chr(0xBB) . chr(0xBF) . $content);
            //开始同步数据库
            if ($make_web) {
                $articleCountModel = ArticleSyncCount::where($where)->find();
                if (is_null($articleCountModel)) {
                    $article_sync->count = $item["id"];
                    $article_sync->type_id = $type_id;
                    $article_sync->type_name = $type_name;
                    $article_sync->node_id = $node_id;
                    $article_sync->site_id = $site_id;
                    $article_sync->site_name = $site_name;
                    $article_sync->save();
                } else {

                    $articleCountModel->count = $item["id"];
                    $articleCountModel->save();
                }
            }
        }
        // 请求当前网站列表页 提前生成列表静态化页面
//        $curl=$siteinfo["url"]."/".$type_name.'/'.$type_id."html";
//        $this->curl_get($curl);
    }
}
