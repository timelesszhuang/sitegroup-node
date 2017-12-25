<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\controller\QuestionList;
use app\index\model\Articletype;
use app\index\model\Producttype;
use app\index\model\QuestionType;
use app\tool\model\Activity;
use think\Cache;
use think\Config;
use think\Db;


/**
 * 公共的相关操作的 工具
 */
class Commontool extends Common
{

    //各个列表的路径规则
    private static $articleListPath = '/articlelist/%s.html';
    public static $articlePath = '/article/article%s.html';
    private static $productListPath = '/productlist/%s.html';
    public static $productPath = '/product/product%s.html';
    private static $questionListPath = '/questionlist/%s.html';
    public static $questionPath = '/question/question%s.html';

    public static $articleListField = 'id,title,title_color,articletype_name,articletype_id,thumbnails,thumbnails_name,summary,create_time';
    public static $questionListField = 'id,question,type_id,type_name,create_time';
    public static $productListField = 'id,name,image_name,sn,payway,type_id,type_name,summary,create_time';

    /**
     * 清除缓存 信息
     * @access public
     */
    public function clearCache()
    {
        if (Cache::clear()) {
            exit(['status' => 'success', 'msg' => '清除缓存成功。']);
        }
        exit(['status' => 'failed', 'msg' => '清除缓存失败。']);
    }

    /**
     * 获取手机站的域名 跟 跳转的js 存储在缓存中
     * @access public
     */
    public static function getMobileSiteInfo()
    {
        $mobileinfo = Cache::remember('mobileinfo', function () {
            //获取手机相关信息
            $siteinfo = Site::getSiteInfo();
            $m_site_url = '';
            //手机重定向的站点
            $m_redirect_code = '';
            //如果是pc 站的话 获取下对应的手机站
            if ($siteinfo['is_mobile'] == '10') {
                //是 pc
                $m_site_id = $siteinfo['m_site_id'];
                if ($m_site_id) {
                    //这个地方如果取出来不是手机站的话 需要给出提示错误
                    $m_site_url = Db::name('site')->where(['id' => $m_site_id])->field('url')->find()['url'];
                    $m_redirect_code = self::getRedirectCode($m_site_url);
                }
            }
            $mobileinfo = [$m_site_url, $m_redirect_code];
            return $mobileinfo;
        });
        return $mobileinfo;
    }


    /**
     * 获取其他的数据
     * @access public
     */
    public static function getRedirectCode($url)
    {
        $code = Db::name('system_config')->where(["name" => 'SYSTEM_PCTOM_REDIRECT_CODE'])->field('value')->find()['value'];
        $redirect_code = '';
        if ($code) {
            $redirect_code = str_replace('{{mobile_path}}', $url, $code);
        }
        return $redirect_code;
    }


    /**
     * 获取首页面的 tdk 相关数据
     * @access public
     * @param $keyword  关键词数组
     * @param $page_id 页面的id  index
     * @param $site_id 站点的id
     * @param $node_id 节点的id
     * @param $tag 标志tag  index 表示首页  栏目 column  详情页 detail
     * @return array
     */
    public static function getIndexPageTDK($keyword_info, $site_id, $site_name, $node_id, $com_name)
    {
        $title = '';
        $keyword = '';
        $description = '';
        $page_id = 'index';
        //默认首页的menu_id 为零
        $menu_id = 0;
        $page_name = '首页';
        $page_type = 'index';
        list($pageinfo_id, $akeyword_id, $change_status, $title, $keyword, $description) = self::getDbPageTDK($menu_id, $node_id, $site_id, $page_type);
        if (empty($title)) {
            //tdk 是空的 需要 重新 从关键词中获取
            //首页的title ： A类关键词1_A类关键词2_A类关键词3-公司名
            //     keyword  ： A类关键词1,A类关键词2,A类关键词3
            //     description : A类关键词拼接
            $a_keywordname_arr = array_column($keyword_info, 'name');
            $title = implode('_', $a_keywordname_arr) . '-' . $com_name;
            $keyword = implode(',', $a_keywordname_arr);
            $description = implode('，', $a_keywordname_arr) . '，' . $com_name;
            Db::name('SitePageinfo')->insert([
                'menu_id' => 0,
                'site_id' => $site_id,
                'site_name' => $site_name,
                'page_type' => $page_type,
                'node_id' => $node_id,
                'page_id' => $page_id,
                'page_name' => $page_name,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'akeyword_id' => 0,
                'pre_akeyword_id' => 0,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }
        return [$title, $keyword, $description];
    }


    /**
     * 获取栏目页面的 tdk 相关数据
     * @param $keyword_info 关键词相关
     * @param $page_id 页面的id  比如 contactme
     * @param $site_id 站点的id
     * @param $node_id 节点的id
     * @param $menu_name 栏目名
     * @return array
     */
    public static function getMenuPageTDK($keyword_info, $page_id, $page_name, $site_id, $site_name, $node_id, $menu_id, $menu_name)
    {
        $page_type = 'menu';
        //首先从数据库中获取当前站点设置的 tdk 等 相关数据
        /**
         * 表示页面是不是已经生成过
         * 如果没有生成  则随机选择一个A类 关键词 按照规则拼接关键词
         * 如果已经生成过 则需要比对现在的关键词是不是已经更换过 更换过的需要重新生成
         */
        list($pageinfo_id, $akeyword_id, $change_status, $title, $keyword, $description) = self::getDbPageTDK($menu_id, $node_id, $site_id, $page_type);
        if (empty($title)) {
            // 栏目页面的 TDK 获取 A类关键词随机选择
            //栏目页的 title：B类关键词多个_A类关键词1-栏目名
            //        keyword：B类关键词多个,A类关键词
            //        description:拼接一段就可以栏目名
            $a_keyword_key = array_rand($keyword_info, 1);
            $a_child_info = $keyword_info[$a_keyword_key];
            $a_name = $a_child_info['name'];
            $a_keyword_id = $a_child_info['id'];
            if (!array_key_exists('children', $a_child_info)) {
                return ['', '', ''];
            }
            $b_keyword_info = $a_child_info['children'];
            $b_keywordname_arr = array_column($b_keyword_info, 'name');
            $title = implode('_', $b_keywordname_arr) . '_' . $a_name . '-' . $menu_name;
            $keyword = implode(',', $b_keywordname_arr) . ',' . $a_name;
            $description = implode('，', $b_keywordname_arr) . '，' . $a_name . '，' . $menu_name;
            //选择好了 之后需要添加到数据库中 一定是新增
            Db::name('SitePageinfo')->insert([
                'menu_id' => $menu_id,
                'site_id' => $site_id,
                'site_name' => $site_name,
                'page_type' => $page_type,
                'node_id' => $node_id,
                'page_id' => $page_id,
                'page_name' => $page_name,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'pre_akeyword_id' => $a_keyword_id,
                'akeyword_id' => $a_keyword_id,
                'create_time' => time(),
                'update_time' => time()
            ]);
        } elseif ($change_status) {
            //需要验证下
            $a_child_info = [];
            foreach ($keyword_info as $k => $v) {
                if ($v['id'] == $akeyword_id) {
                    $a_child_info = $keyword_info[$k];
                }
            }
            if (empty($a_child_info)) {
                return ['', '', ''];
            }
            $a_name = $a_child_info['name'];
            $a_keyword_id = $a_child_info['id'];
            if (!array_key_exists('children', $a_child_info)) {
                return ['', '', ''];
            }
            $b_keyword_info = $a_child_info['children'];
            $b_keywordname_arr = array_column($b_keyword_info, 'name');
            $title = implode('_', $b_keywordname_arr) . '_' . $a_name . '-' . $menu_name;
            $keyword = implode(',', $b_keywordname_arr) . ',' . $a_name;
            $description = implode('，', $b_keywordname_arr) . '，' . $a_name . '，' . $menu_name;
            Db::name('SitePageinfo')->update([
                'id' => $pageinfo_id,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'pre_akeyword_id' => $a_keyword_id
            ]);
        }
        return [$title, $keyword, $description];
    }


    /**
     * 详情页 的TDK 获取   需要有固定的关键词
     * @param $keyword_info 关键词相关
     * @param $site_id 站点的id
     * @param $node_id 节点的id
     * @param $articletitle
     * @param $articlecontent
     * @param $a_keyword_key
     * @return array
     * @todo 详情页不需要 存储在数据库中 TDK定死就行
     */
    public static function getDetailPageTDK($keyword_info, $site_id, $node_id, $articletitle, $articlecontent, $keywords, $a_keyword_id)
    {
        //需要知道 栏目的关键词等
        //$keyword_info, $site_id, $node_id, $articletitle, $articlecontent
        // 详情页页面的 TDK 获取 A类关键词随机选择
        // 详情页的 title：C类关键词多个_A类关键词1-文章标题
        //        keyword：C类关键词多个,A类关键词
        //        description:拼接一段就可以栏目名
        $a_child_info = [];
        foreach ($keyword_info as $k => $v) {
            if ($v['id'] == $a_keyword_id) {
                $a_child_info = $v['children'];
                break;
            }
        }
        // 该A类下 没有关键词 需要更换
        if (empty($a_child_info)) {
            //需要处理下 如果没有的话 怎么处理 也就是说该网站取消选择某个关键词
            $a_keyword_key = array_rand($keyword_info, 1);
            $new_a_keyword_id = $keyword_info[$a_keyword_key]['id'];
            //更新一下数据库中的页面的a类 关键词
            Db::name('SitePageinfo')->where([
                'site_id' => $site_id,
                'node_id' => $node_id,
                'akeyword_id' => $a_keyword_id,
            ])->update([
                'akeyword_id' => $new_a_keyword_id,
                'update_time' => time()
            ]);
            foreach ($keyword_info as $k => $v) {
                if ($v['id'] == $new_a_keyword_id) {
                    $a_child_info = $v['children'];
                    break;
                }
            }
        }
        //随机取 一个a类下的b类
        $b_child_info = $a_child_info[array_rand($a_child_info)];
        $c_keyword_arr = [];
        if (array_key_exists('children', $b_child_info)) {
            $c_child_info = $b_child_info['children'];
            $length = count($c_child_info);
            $randamcount = $length > 3 ? 3 : $length;
            $c_rand_key = array_rand($c_child_info, $randamcount);
            if (is_array($c_rand_key)) {
                foreach ($c_rand_key as $v) {
                    $c_keyword_arr[] = $c_child_info[$v];
                }
            } else {
                $c_keyword_arr[] = $c_child_info[$c_rand_key];
            }
        }
        $c_keywordname_arr = array_column($c_keyword_arr, 'name');
        $title = $articletitle . '-' . implode('_', $c_keywordname_arr);
        $keyword = $keywords ? $keywords : implode(',', $c_keywordname_arr);
        $description = $articlecontent;
        return [$title, $keyword, $description];
    }


    /**
     * 从数据库中获取页面的相关信息
     * @access public
     */
    public static function getDbPageTDK($menu_id, $node_id, $site_id, $page_type)
    {
        $page_info = Db::name('site_pageinfo')->where(['menu_id' => $menu_id, 'node_id' => $node_id, 'site_id' => $site_id, 'page_type' => $page_type])->field('id,title,keyword,description,pre_akeyword_id,akeyword_id')->find();
        $akeyword_changestatus = false;
        $akeyword_id = 0;
        if ($page_info) {
            if ($page_info['pre_akeyword_id'] != $page_info['akeyword_id']) {
                $akeyword_changestatus = true;
                $akeyword_id = $page_info['akeyword_id'];
            }
            return [$page_info['id'], $akeyword_id, $akeyword_changestatus, $page_info['title'], $page_info['keyword'], $page_info['description']];
        }
        return [0, $akeyword_id, $akeyword_changestatus, '', '', ''];
    }


    /**
     * 获取 文章列表 获取十条　文件名如 article1 　article2
     * @access public
     * @param $sync_info 该站点所有文章分类的 静态化状况
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getArticleList($sync_info, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        //随机取值
        //测试
        if (!($max_id && $article_typearr)) {
            return ['list' => [], 'more' => $more];
        }
        $articlelist = self::getTypesArticleList($max_id, $article_typearr, $limit);
        //随机取值来生成静态页
        $rand_key = array_rand($article_typearr);
        $rand_type = $article_typearr[$rand_key];
        if ($more['href'] == '/') {
            $more = ['title' => $rand_type['menu_name'], 'href' => sprintf(self::$articleListPath, $rand_type['menu_enname']), 'text' => '更多', 'menu_name' => $rand_type['menu_name'], 'type_name' => $rand_type['type_name']];
        }
        // 这个地方有问题
        return ['list' => $articlelist, 'more' => $more];
    }

    /**
     * 获取多个分类下的文章列表
     * @access public
     */
    public static function getTypesArticleList($max_id, $article_typearr, $limit)
    {
        $typeid_str = implode(',', array_keys($article_typearr));
        $where = " `id`<= {$max_id} and articletype_id in ({$typeid_str})";
        $article = Db::name('Article')->where($where)->field(self::$articleListField)->order('id desc')->limit($limit)->select();
        self::formatArticleList($article, $article_typearr);
        return $article;
    }


    /**
     * 获取文章分类下的文章列表 比如 行业新闻直接调取分类下的id 调取的时候建议使用 array_key_exists
     * @access public
     */
    public static function getArticleTypeList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_type_aliasarr = array_key_exists('article', $type_aliasarr) ? $type_aliasarr['article'] : [];
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        if (!($max_id && $article_type_aliasarr)) {
            //表示还没有数据 直接跳出来
        }
        $articlealias_list = [];
        foreach ($article_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $articlelist = self::getTypeArticleList($type_id, $max_id, $article_typearr, $limit);
            $more = ['title' => $v['menu_name'], 'href' => sprintf(self::$articleListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
            //组织数据
            $articlealias_list[$type_alias] = [
                'list' => $articlelist,
                'more' => $more,
                'menu_name' => $v['menu_name'],
                'type_name' => $v['type_name']
            ];
        }
        return $articlealias_list;
    }


    /**
     * 获取单个分类下的文章列表
     * @access public
     */
    public static function getTypeArticleList($type_id, $max_id, $article_typearr, $limit)
    {
        $where = " `id`<= {$max_id} and articletype_id = {$type_id}";
        //后期可以考虑置顶之类操作
        $article = Db::name('Article')->where($where)->field(self::$articleListField)->order('id desc')->limit($limit)->select();
        self::formatArticleList($article, $article_typearr);
        return $article;
    }


    /**
     * 根据取出来的文章list 格式化为指定的格式
     * @access public
     */
    public static function formatArticleList(&$article, $article_typearr)
    {
        foreach ($article as $k => $v) {
            //防止标题中带着% 好的引起程序问题
            $v['title'] = str_replace('%', '', $v['title']);
            $img_template = "<img src='%s' alt='{$v['title']}' title='{$v['title']}'>";
            //格式化标题的颜色
            $v['color_title'] = $v['title_color'] ? sprintf('<span style="color:%s">%s</span>', $v['title_color'], $v['title']) : $v['title'];
            unset($v['title_color']);
            //默认缩略图的
            $img = sprintf($img_template, '/templatestatic/default.jpg');
            if (!empty($v["thumbnails_name"])) {
                //如果有本地图片则 为本地图片
                $src = "/images/" . $v['thumbnails_name'];
                $img = sprintf($img_template, $src);
            } else if (!empty($v["thumbnails"])) {
                $img = sprintf($img_template, $v['thumbnails']);
            }
            //列出当前文章分类来
            if (array_key_exists($v['articletype_id'], $article_typearr)) {
                $type = [
                    'name' => $v['articletype_name'],
                    'href' => $article_typearr[$v['articletype_id']]['href']
                ];
            }
            unset($v['articletype_id']);
            unset($v['articletype_name']);
            $v['href'] = sprintf(self::$articlePath, $v['id']);
            if (is_array($v)) {
                $v['create_time'] = date('Y-m-d', $v['create_time']);
            }
            $v['thumbnails'] = $img;
            $v['type'] = $type;
            $article[$k] = $v;
        }
    }


    /**
     * 获取 产品列表 获取十条产品
     * @access public
     * @param $sync_info 该站点所有文章分类的 静态化状况
     * @param $site_id 如果是 detail 的话 应该给
     * @param int $limit
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getProductList($sync_info, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('product', $sync_info) ? $sync_info['product'] : 0;
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        if (!($max_id && $product_typearr)) {
            return ['list' => [], 'more' => $more];
        }
        $productlist = self::getTypesProductList($max_id, $product_typearr, $limit);
        //随机取值来生成静态页
        $rand_key = array_rand($product_typearr);
        $rand_type = $product_typearr[$rand_key];
        if ($more['href'] == '/') {
            $more = ['title' => $rand_type['menu_name'], 'href' => sprintf(self::$productListPath, $rand_type['menu_enname']), 'text' => '更多'];
        }
        return ['list' => $productlist, 'more' => $more];
    }

    /**
     * 获取多个类型types 产品列表
     * @access public
     */
    public static function getTypesProductList($max_id, $product_typearr, $limit)
    {
        $typeid_str = implode(',', array_keys($product_typearr));
        $where = " `type_id` in ($typeid_str) and `id`<= {$max_id}";
        $product = Db::name('Product')->where($where)->field(self::$productListField)->order('id desc')->limit($limit)->select();
        self::formatProductList($product, $product_typearr);
        return $product;
    }


    /**
     * 获取产品分类下的文章列表 比如 行业新闻直接调取分类下的id 调取的时候建议使用 array_key_exists
     * @access public
     */
    private static function getProductTypeList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('product', $sync_info) ? $sync_info['product'] : 0;
        $product_type_aliasarr = array_key_exists('product', $type_aliasarr) ? $type_aliasarr['product'] : [];
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        if (!($max_id && $product_type_aliasarr)) {
            //表示还没有数据 直接跳出来
            return [];
        }
        $productalias_list = [];
        foreach ($product_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $productlist = self::getTypeProductList($type_id, $max_id, $product_typearr, $limit);
            $more = ['title' => $v['menu_name'], 'href' => sprintf(self::$productListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
            //组织数据
            $productalias_list[$type_alias] = [
                'list' => $productlist,
                'more' => $more,
                'menu_name' => $v['menu_name'],
                'type_name' => $v['type_name']
            ];
        }
        return $productalias_list;
    }

    /**
     * 获取单个类型type 产品列表
     * @access public
     */
    public static function getTypeProductList($type_id, $max_id, $product_typearr, $limit)
    {
        $where = " `id`<= {$max_id} and type_id = {$type_id}";
        //后期可以考虑置顶之类操作
        $product = Db::name('Product')->where($where)->field(self::$productListField)->order('id desc')->limit($limit)->select();
        self::formatProductList($product, $product_typearr);
        return $product;
    }


    /**
     * 格式化产品信息数据
     * @access private
     */
    public static function formatProductList(&$product, $product_typearr)
    {
        foreach ($product as $k => $v) {
            $src = "/images/" . $v['image_name'];
            $img = "<img src='{$src}' alt= '{$v['name']}'>";
            //列出当前文章分类来
            $type = [
                'name' => '',
                'href' => ''
            ];
            if (array_key_exists($v['type_id'], $product_typearr)) {
                $type = [
                    'name' => $v['type_name'],
                    'href' => $product_typearr[$v['type_id']]['href']
                ];
            }
            if (is_array($v)) {
                $v['create_time'] = date('Y-m-d', $v['create_time']);
            }
            unset($v['type_id']);
            unset($v['type_name']);
            unset($v['image_name']);
            $v['href'] = sprintf(self::$productPath, $v['id']);
            $v['thumbnails'] = $img;
            $v['type'] = $type;
            $product[$k] = $v;
        }
    }


    /**
     * 获取 问题列表 获取十条　文件名如 question1 　question2
     * @access public
     * @param $sync_info
     * @param $site_id
     * @param int $limit
     * @return array
     */
    public static function getQuestionList($sync_info, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('question', $sync_info) ? $sync_info['question'] : 0;
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        if (!($max_id && $question_typearr)) {
            return ['list' => [], 'more' => $more];
        }
        $questionlist = self::getTypesQuestionList($max_id, $question_typearr, $limit);
        $rand_key = array_rand($question_typearr);
        $rand_type = $question_typearr[$rand_key];
        if ($more['href'] == '/') {
            $more = ['title' => $rand_type['menu_name'], 'href' => sprintf(self::$questionListPath, $rand_type['menu_enname']), 'text' => '更多'];
        }
        return ['list' => $questionlist, 'more' => $more];
    }


    /**
     * 获取多个类型types 问答列表
     * @access public
     */
    public static function getTypesQuestionList($max_id, $question_typearr, $limit)
    {
        $typeid_str = implode(',', array_keys($question_typearr));
        $where = "`type_id` in ($typeid_str)  and `id`<= {$max_id}";
        $question = Db::name('Question')->where($where)->field(self::$questionListField)->order('id desc')->limit($limit)->select();
        self::formatQuestionList($question, $question_typearr);
        return $question;
    }


    /**
     * 获取问答分类的相关列表数据
     * @access public
     */
    public static function getQuestionTypeList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('question', $sync_info) ? $sync_info['question'] : 0;
        $question_type_aliasarr = array_key_exists('question', $type_aliasarr) ? $type_aliasarr['question'] : [];
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        if (!($max_id && $question_type_aliasarr)) {
            //表示还没有数据 直接跳出来
            return [];
        }
        $questionalias_list = [];
        foreach ($question_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $questionlist = self::getTypeQuestionList($type_id, $max_id, $question_typearr, $limit);
            $more = ['title' => $v['menu_name'], 'href' => sprintf(self::$questionListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
            //组织数据
            $questionalias_list[$type_alias] = [
                'list' => $questionlist,
                'more' => $more,
                'menu_name' => $v['menu_name'],
                'type_name' => $v['type_name']
            ];
        }
        return $questionalias_list;
    }

    /**
     * 获取单个类型type 问答列表
     * @access public
     */
    public static function getTypeQuestionList($type_id, $max_id, $question_typearr, $limit)
    {
        $where = " `id`<= {$max_id} and type_id = {$type_id}";
        //后期可以考虑置顶之类操作
        $question = Db::name('Question')->where($where)->field(self::$questionListField)->order('id desc')->limit($limit)->select();
        self::formatQuestionList($question, $question_typearr);
        return $question;
    }

    /**
     * 根据问答分类格式化列表数据
     * @access private
     */
    public static function formatQuestionList(&$question, $question_typearr)
    {
        foreach ($question as $k => $v) {
            $type = [
                'name' => '',
                'href' => ''
            ];
            if (array_key_exists($v['type_id'], $question_typearr)) {
                $type = [
                    'name' => $v['type_name'],
                    'href' => $question_typearr[$v['type_id']]['href']
                ];
            }
            $v['question'] = $v['question'];
            $v['href'] = sprintf(self::$questionPath, $v['id']);
            if (is_array($v)) {
                // model对象调用的时候会自动格式化
                $v['create_time'] = date('Y-m-d', $v['create_time']);
            }
            $v['type'] = $type;
            $question[$k] = $v;
        }
    }



    /**
     * 零散段落这块暂时有问题 功能模块需要重构
     */

    /**
     * 获取 零散段落 分类  文件名如 article1 　article2
     * @access public
     * @param $sync_info
     * @param $site_id
     * @param int $limit
     * @return array
     */
    public static function getScatteredArticleList($sync_info, $site_id, $limit = 10)
    {
//        $scattered_sync_info = array_key_exists('scatteredarticle', $sync_info) ? $sync_info['scatteredarticle'] : [];
//        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
//        if ($scattered_sync_info) {
//            $where = '';
//            foreach ($scattered_sync_info as $k => $v) {
//                if ($k == 0) {
//                    $where .= "(`articletype_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
//                } else {
//                    $where .= ' or' . " (`articletype_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
//                }
//                if ($more['href'] == '/') {
//                    $more = ['title' => $v['menu_name'], 'href' => "/news/{$v['menu_id']}.html", 'text' => '更多'];
//                }
//            }
//            $scattered_article = Db::name('Scattered_title')->where($where)->field('id,title,create_time')->order('id desc')->limit($limit)->select();
//            $articlelist = [];
//            foreach ($scattered_article as $k => $v) {
//                $articlelist[] = [
//                    'href' => '/news/news' . $v['id'] . '.html',
//                    'title' => $v['title'],
//                    'create_time' => date('Y-m-d', $v['create_time'])
//                ];
//            }
//            return [$articlelist, $more];
//        }
//        return [[], $more];
    }


    /**
     * 获取公共代码
     * @access public
     */
    public static function getCommonCode($code_ids)
    {
        $code = Db::name('code')->where(['id' => ['in', array_filter(explode(',', $code_ids))]])->field('code,position')->select();
        $pre_head_code_list = [];
        $after_head_code_list = [];
        foreach ($code as $k => $v) {
            if ($v['position'] == 1) {
                $pre_head_code_list[] = $v['code'];
            } else {
                $after_head_code_list[] = $v['code'];
            }
        }
        return [$pre_head_code_list, $after_head_code_list];
    }


    /**
     * 获取分类中已经静态化到的地方 需要的文章列表  内容列表 问答列表
     * @access public
     * @param $site_id 站点的id 信息
     * @return array
     */
    public static function getDbArticleListId($site_id)
    {
        //
        return Cache::remember('sync_info', function () use ($site_id) {

            //文章同步表中获取文章同步到的位置 需要考虑到 一个站点新建的时候会是空值
            $article_sync_info = Db::name('ArticleSyncCount')->where(['site_id' => $site_id])->field('type_name,count')->select();
            $article_sync_list = [];
            if ($article_sync_info) {
                foreach ($article_sync_info as $v) {
                    if (!array_key_exists($v['type_name'], $article_sync_list)) {
                        $article_sync_list[$v['type_name']] = $v['count'];
                    }
                }
            }
            return $article_sync_list;//文章同步表中获取文章同步到的位置 需要考虑到 一个站点新建的时候会是空值
        });
    }

    /**
     * 获取活动列表
     * @access public
     */
    public static function getActivity($sync_id)
    {
        $where["id"] = ['in', explode(',', $sync_id)];
        $where["status"] = 10;
        $activity = Activity::where($where)->field('id,title,img_name,url,summary')->select();
        $activity_list = [];
        foreach ($activity as $k => $v) {
            $activity = [];
            $activity['name'] = $v['title'];
            $activity['summary'] = $v['summary'];
            $activity['src'] = "/images/{$v['img_name']}";
            $activity['href'] = $v['url'] ?: "/activity/activity{$v['id']}.html";
            $activity_list[] = $activity;
        }
        return $activity_list;
    }

    /**
     * 获取搜索引擎的 referer 不支持百度 谷歌 现仅支持 搜狗 好搜
     * @access public
     * @todo 这个地方有bug 会有安全隐患 太low
     */
    public static function getRefereerDemo()
    {
        return <<<CODE
                <script>
                    var referrer = document.referrer;
                    var sendInfo = {};
                    sendInfo.referrer = referrer;
                    sendInfo.origin_web = window.location.href
                    $(function () {
                        var url = "/index.php/externalAccess";
                        $.ajax({
                                type: "post",
                                url: url,
                                data: sendInfo,
                                success: function () {
                                }
                            }
                        )
                    })
                </script>
CODE;
    }


    /**
     * 获取版本控制　软件
     * ＠access public
     */
    public static function getSiteCopyright($com_name)
    {
        //返回copyright
        return '© 2015-' . date('Y') . '  ' . $com_name . ' All Rights Reserved.';
    }


    /**
     * 获取网站powerby相关 信息
     * @access private
     */
    private static function getSitePowerBy()
    {
        return ['text' => '技术支持：北京易至信科技有限公司', 'href' => 'http://www.salesman.cc'];
    }

    /**
     * 获取站点的js 公共代码
     * @access public
     */
    public static function getSiteJsCode($siteinfo)
    {
        //获取公共代码
        list($pre_head_jscode, $after_head_jscode) = self::getCommonCode($siteinfo['public_code']);
        //head前后的代码
        $before_head = $siteinfo['before_header_jscode'];
        $after_head = $siteinfo['other_jscode'];
        if ($before_head) {
            array_push($pre_head_jscode, $before_head);
        }
        if ($after_head) {
            array_push($after_head_jscode, $after_head);
        }
        $refere_code = self::getRefereerDemo();
        if ($refere_code) {
            array_push($after_head_jscode, $refere_code);
        }
        $pre_head_js = '';
        foreach ($pre_head_jscode as $v) {
            $pre_head_js = $pre_head_js . $v;
        }
        $after_head_js = '';
        foreach ($after_head_jscode as $v) {
            $after_head_js = $after_head_js . $v;
        }
        return [$pre_head_js, $after_head_js];
    }

    /**
     * 获取备案信息
     * @access public
     */
    public static function getBeianInfo($siteinfo)
    {
        //公司备案
        $beian_link = 'http://www.miitbeian.gov.cn';
        $beian = ['beian_num' => '', 'link' => $beian_link];
        $domain_id = $siteinfo['domain_id'];
        if ($domain_id) {
            //这个地方应该也添加缓存
            $domain_info = Cache::remember('domain_info', function () use ($domain_id) {
                return Db::name('domain')->where('id', $domain_id)->find();
            });
            if ($domain_info) {
                $beian_num = $domain_info['filing_num'];
                $beian = ['text' => $beian_num, 'href' => $beian_link];
            }
        }
        return $beian;
    }

    /**
     * 获取站点的logo 相关信息
     * @access private
     * @param $site_info 站点相关数据
     * @return string
     */
    private static function getSiteLogo($site_info)
    {
        $id = $site_info['sitelogo_id'];
        $site_name = $site_info['site_name'];
        if (!$id) {
            return $site_name;
        }
        $site_id = $site_info['id'];
        $site_logoinfo = Cache::remember('sitelogoinfo', function () use ($id) {
            return Db::name('site_logo')->where('id', $id)->find();
        });
        return Cache::remember('sitelogo', function () use ($site_logoinfo, $site_id, $site_name) {
            if ($site_logoinfo) {
                $oss_file_path = $site_logoinfo['oss_logo_path'];
                $pathinfo = pathinfo(parse_url($oss_file_path)['path']);
                $ext = '';
                if (array_key_exists('extension', $pathinfo)) {
                    $ext = '.' . $pathinfo['extension'];
                }
                return "<img src='/images/logo{$site_id}{$ext}' title='$site_name' alt='$site_name'>";
            }
            return $site_name;
        });

    }


    /**
     * 获取联系人信息
     * @access public
     */
    public static function getContactInfo($siteinfo)
    {
        if (!empty($siteinfo["site_contact"])) {
            return $siteinfo["site_contact"];
        }
        $contact_way_id = $siteinfo['support_hotline'];
        $contact_info = [];
        if ($contact_way_id) {
            //缓存中有 则用缓存中的
            $contact_info = Cache::remember('contactway', function () use ($contact_way_id) {
                return Db::name('contactway')->where('id', $contact_way_id)->field('html as contact,detail as title')->find();
            });
        }
        return $contact_info;
    }


    /**
     * 获取外链
     * @access public
     */
    public static function getPatternLink($siteinfo)
    {
        //友链信息
        $link_info = Cache::remember('linkinfo', function () use ($siteinfo) {
            return Db::name('links')->where(['id' => ['in', array_filter(explode(',', $siteinfo['link_id']))]])->field('id,name,domain')->select();
        });
        $partnersite = [];
        foreach ($link_info as $k => $v) {
            $partnersite[$v['domain']] = $v['name'];
        }
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
            list($chain_type, $next_site, $main_site) = Site::getLinkInfo($site_type_id, $siteinfo['id'], $siteinfo['site_name'], $siteinfo['node_id']);
        }
        if ($next_site) {
            $partnersite[$next_site['url']] = $next_site['site_name'];
        }
        if ($main_site) {
            $partnersite[$main_site['url']] = $main_site['site_name'];
        }
        return $partnersite;
    }

    /**
     * 获取站点内容调取列表 比如相关站点
     * @access private
     * @param $siteinfo 站点相关数据
     * @return mixed
     */
    private static function getSiteGetContent($siteinfo)
    {
        $node_id = $siteinfo['node_id'];
        return Cache::remember('contentlist', function () use ($node_id) {
            $contentlist = Db::name('content_get')->where('node_id', $node_id)->field('en_name,name,content,href')->select();
            $list = [];
            foreach ($contentlist as $v) {
                $list[$v['en_name']] = [
                    'name' => $v['name'],
                    'content' => $v['content'],
                    'href' => $v['href']
                ];
            }
            return $list;
        });

    }


    /**
     * 获取 页面中必须的元素
     *
     * @param string $tag index 或者 menu detail
     * @param string $param 如果是   index  第二第三个参数没用
     *                              menu 第二个参数$param表示   $page_id 也就是菜单的英文名 第三个参数 $param2 表示 菜单名 menu_name   $param3 是 menu_id  $param4 为type_id菜单下的id  $param5 表示菜单类型 articlelist newslist  questionlist  productlist
     *                              detail   第二个参数$param表示  $articletitle 用来获取文章标题 第三个参数 $param2 表示 文章的内容 $param3 表示文章设置的keywords  $param4 是 a_keyword_id  $para5  表示 menu_id  $param6 表示 menu_name $param7 用于生成面包屑的时候 获取 栏目菜单的url
     *                              activity
     *                              query  查询相关tdk获取
     * @param string $param2
     * @return array
     */
    public static function getEssentialElement($tag = 'index', $param = '', $param2 = '', $param3 = '', $param4 = '', $param5 = '', $param6 = '', $param7 = '')
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        //获取站点的logo
        $logo = self::getSiteLogo($siteinfo);
        $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $site_id, $site_name, $node_id);
        //菜单如果是 详情页面 也就是 文章内容页面  详情类型的 需要 /
        //该站点的网址
        $url = $siteinfo['url'];
        $imgset = self::getImgList($node_id);
        //菜单的返回也需要修改
        $menu = self::getTreeMenuInfo($siteinfo['menu'], $site_id, $site_name, $node_id, $url, $tag, $param2, $param3);
        //用于生成面包屑
        $allmenu = Menu::getMergedMenu($siteinfo['menu'], $site_id, $site_name, $node_id);
        //获取网站中每个分类的 $type_aliasarr
        /*
          [
               'article'=>[
                    //支持多个
                   '栏目英文名alias'=>[
                        栏目信息
                    ]
                ],
               'question'=>[],
               'product'=>[],
          ]
        */
        //获取每个菜单的menu_idarr
        /*
         [
               'menu_id'=>[
                    //支持多个
                   '栏目英文名alias'=>[
                        栏目信息
                    ],
                    ''=>[],
                ],
               'menu_id2'=>[],
               'menu_id3'=>[],
          ]
        */
        // 获取每种分类的 typeid_arr
        /*
         [
               'article'=>[
                    //支持多个
                   '栏目id'=>[
                        栏目信息
                    ],
                    ''=>[],
                ],
               'product'=>[],
               'question'=>[],
          ]
        */
        list($type_aliasarr, $typeid_arr) = self::getTypeIdInfo($siteinfo['menu']);
        //活动创意相关操作
        $activity = self::getActivity($siteinfo['sync_id']);
        //获取站点的类型 手机站的域名 手机站点的跳转链接
        list($m_url, $redirect_code) = self::getMobileSiteInfo();
        //这个不需要存到缓存中
        $sync_info = self::getDbArticleListId($site_id);
        $breadcrumb = [];
        //每个页面特殊的变量存在
        switch ($tag) {
            case 'index':
                $page_id = 'index';
                //然后获取 TDK 等数据  首先到数据库
                list($title, $keyword, $description) = self::getIndexPageTDK($keyword_info, $site_id, $site_name, $node_id, $siteinfo['com_name']);
                //获取首页面包屑
                //Breadcrumb 面包屑
                $breadcrumb = self::getBreadCrumb($tag, $url, $allmenu);
                $menu_name = '首页';
                break;
            case 'menu':
                //菜单 页面的TDK 分为两种 一种是已经存在的 另外一种为详情形式的列表 栏目的英文名
                $page_id = $param;
                //栏目名
                $menu_name = $param2;
                //栏目的id
                $menu_id = $param3;
                //文章分类的id
                $type_id = $param4;
                //type 为 productlist articlelist questionlist
                $type = $param5;
                list($title, $keyword, $description) = self::getMenuPageTDK($keyword_info, $page_id, $menu_name, $site_id, $site_name, $node_id, $menu_id, $menu_name);
                //获取菜单的 面包屑 导航
                //需要注意下 详情型的菜单 没有type
                $breadcrumb = self::getBreadCrumb($tag, $url, $allmenu, $menu_id);
                break;
            case 'detail':
                //详情页面
                $page_id = '';
                //文章的标题
                $articletitle = $param;
                //文章的summary
                $articlecontent = $param2;
                //关键词 文章中的关键词
                $keywords = $param3;
                //该文章所属的父类的a类关键词
                $a_keyword_id = $param4;
                //当前栏目的id
                $menu_id = $param5;
                //当前栏目的name
                $menu_name = $param6;
                $type = $param7;
                list($title, $keyword, $description) = self::getDetailPageTDK($keyword_info, $site_id, $node_id, $articletitle, $articlecontent, $keywords, $a_keyword_id);
                //获取详情页面的面包屑
                $breadcrumb = self::getBreadCrumb($tag, $url, $allmenu, $menu_id);
                break;
            case 'activity':
                //活动相关获取
                $title = $param;
                $keyword = $param2;
                $description = $param3;
                //面包屑是空的
                $breadcrumb = self::getBreadCrumb($tag, $url, $allmenu);
                $menu_name = '活动创意';
                break;
            case 'query':
                //查询操作
                $title = $param;
                $keyword = $param2;
                $description = $param3;
                $breadcrumb = self::getBreadCrumb($tag, $url, $allmenu);
                $menu_name = '查询结果';
                break;
        }
        //获取不分类的文章 全部分类的都都获取到
        $article_list = self::getArticleList($sync_info, $typeid_arr, 25);
        //获取不分类的文章 全部分类都获取到
        $question_list = self::getQuestionList($sync_info, $typeid_arr, 25);
        //获取零散段落类型  全部分类都获取到
        //list($scatteredarticle_list, $news_more) = self::getScatteredArticleList($artiletype_sync_info, $typeid_arr);
        //产品类型 列表获取 全部分类都获取到
        $product_list = self::getProductList($sync_info, $typeid_arr, 25);
        //根据文章分类展现列表以及more
        $article_typelist = self::getArticleTypeList($sync_info, $type_aliasarr, $typeid_arr, 25);
        //根据文章分类展现列表以及more
        $question_typelist = self::getQuestionTypeList($sync_info, $type_aliasarr, $typeid_arr, 25);
        //根据文章分类展现列表以及more
        $product_typelist = self::getProductTypeList($sync_info, $type_aliasarr, $typeid_arr, 25);
        //获取友链
        $partnersite = self::getPatternLink($siteinfo);
        //获取公共代码
        list($pre_head_js, $after_head_js) = self::getSiteJsCode($siteinfo);
        //获取公司联系方式等 会在右上角或者其他位置添加  这个应该支持小后台能自己修改才对
        $contact_info = self::getContactInfo($siteinfo);
        //获取备案信息
        $beian = self::getBeianInfo($siteinfo);
        $tdk = self::form_tdk_html($title, $keyword, $description);
        $share = self::get_share_code();
        //公司名称
        $com_name = $siteinfo['com_name'];
        //版本　copyright
        $copyright = self::getSiteCopyright($com_name);
        //技术支持
        $powerby = self::getSitePowerby();
        //调取页面中的相关组件
        $getcontent = self::getSiteGetContent($siteinfo);
        $site_name = $siteinfo['site_name'];
        //其中tdk是已经嵌套完成的html代码title keyword description为单独的代码。
        return compact('breadcrumb', 'com_name', 'url', 'site_name', 'menu_name', 'logo', 'contact_info', 'beian', 'copyright', 'powerby', 'getcontent', 'tdk', 'title', 'keyword', 'description', 'share', 'm_url', 'redirect_code', 'menu', 'imgset', 'activity', 'partnersite', 'pre_head_js', 'after_head_js', 'article_list', 'question_list', 'scatteredarticle_list', 'product_list', 'article_more', 'article_typelist', 'question_typelist', 'product_typelist');
    }


    /**
     * 获取每个menu下的所有分类id数组
     * @access public
     */
    public static function getMenuChildrenMenuTypeid($menu_id, $ptypeidarr)
    {
        // 因为会选择三级栏目所以需要选出子栏目的type_id 来
        return Cache::remember('menu_children' . $menu_id, function () use ($menu_id, $ptypeidarr) {
            $menulist = \app\tool\model\Menu::Where('path', 'like', "%,$menu_id,%")->select();
            foreach ($menulist as $menu) {
                //子孙栏目选择type_id
                $ptype_idstr = $menu['type_id'];
                $typeidarr = array_filter(explode(',', $ptype_idstr));
                $ptypeidarr = array_merge($ptypeidarr, $typeidarr);
            }
            $ptypeidarr = array_unique($ptypeidarr);
            return $ptypeidarr;
        });
    }


    /**
     * 获取同一级别的菜单
     * @access public
     */
    public static function getMenuSiblingMenuTypeid($menu_id)
    {
        //当前菜单的父亲菜单
        $pidinfo = \app\tool\model\Menu::Where('id', $menu_id)->field('p_id')->find();
        $pid = $pidinfo['p_id'];
        if (!$pid) {
            return [];
        }
        $menulist = \app\tool\model\Menu::Where('p_id', $pid)->select();
        $typeidlist = [];
        foreach ($menulist as $menu) {
            //子孙栏目选择type_id
            $ptype_idstr = $menu['type_id'];
            $typeidarr = array_filter(explode(',', $ptype_idstr));
            $typeidlist = array_merge($typeidlist, $typeidarr);
        }
        return $typeidlist;
    }


    /**
     * 获取类型 id
     * @access private
     */
    public static function getTypeIdInfo($menu_idstr)
    {
        return Cache::remember('TypeId', function () use ($menu_idstr) {
            //站点所选择的菜单
            $menu_idarr = array_filter(explode(',', $menu_idstr));
            //获取每个菜单下的type_id
            $menulist = \app\tool\model\Menu::Where('id', 'in', $menu_idarr)->field('id,generate_name,name,flag,type_id')->select();
            //组织两套数据 菜单对应的id 数据
            $type_aliasarr = [];
            $typeid_arr = [];
            self::getTypeInfo($menulist, $type_aliasarr, $typeid_arr);
            //第一个是菜单对应的 type  第二个是 article question product等类型对应的别名type 第三个是 类型对应的type_id type
            return [$type_aliasarr, $typeid_arr];
        });
    }

    /**
     * 递归获取网站type_aliasarr typeid_arr
     */
    public static function getTypeInfo($menulist, &$type_aliasarr, &$typeid_arr)
    {
        foreach ($menulist as $k => $v) {
            //栏目类型
            $flag = $v['flag'];
            if ($flag == 1) {
                //详情型号的直接跳过
                continue;
            }
            if ($flag == 4) {
                //零散段落暂时跳过 后期需要重写
                continue;
            }
            $menu_id = $v['id'];
            $menu_name = $v['name'];
            $menu_enname = $v['generate_name'];
            $type_idstr = $v['type_id'];
            //网站typeidarr
            $typeidarr = array_filter(explode(',', $type_idstr));
            //获取每个类型信息
            $typelist = [];
            $listpath = '';
            switch ($flag) {
                case '2':
                    $type = 'question';
                    $listpath = self::$questionListPath;
                    //问答形式
                    $typelist = Questiontype::where('id', 'in', $typeidarr)->select();
                    break;
                case '3':
                    //文章形式
                    $type = 'article';
                    $listpath = self::$articleListPath;
                    $typelist = Articletype::where('id', 'in', $typeidarr)->select();
                    break;
                case '4':
                    //段落形式直接跳过就行暂时不考虑
                    break;
                case '5':
                    $type = 'product';
                    $listpath = self::$productListPath;
                    $typelist = Producttype::where('id', 'in', $typeidarr)->select();
                    break;
            }
            foreach ($typelist as $val) {
                $key = $val['alias'] ?: $val['id'];
                $value = [
                    'menu_id' => $menu_id,
                    'menu_name' => $menu_name,
                    'menu_enname' => $menu_enname,
                    'type_id' => $val['id'],
                    'type_name' => $val['name'],
                    'href' => sprintf($listpath, "{$menu_enname}_t{$val['id']}")
                ];

                if (!array_key_exists($type, $type_aliasarr)) {
                    $type_aliasarr[$type] = [];
                }
                //如果存在 alias 英文名 则用alias 不存在的话用type_id
                //要求同类下不能有重名alias 的
                $type_aliasarr[$type][$key] = $value;

                if (!array_key_exists($type, $typeid_arr)) {
                    $typeid_arr[$type] = [];
                }
                $typeid_arr[$type][$val['id']] = $value;
            }
            //查询当前的menu_id
            self::getTypeInfo(\app\tool\model\Menu::Where('p_id', $menu_id)->field('id,generate_name,name,flag,type_id')->select(), $type_aliasarr, $typeid_arr);
        }
    }


    /**
     * 获取页面的分享代码
     * @access private
     */
    private static function get_share_code()
    {
        return <<<code
    <div class="bdsharebuttonbox">
        <a href="#" class="bds_more" data-cmd="more"></a>
        <a href="#" class="bds_qzone" data-cmd="qzone" title="分享到QQ空间"></a>
        <a href="#" class="bds_tsina" data-cmd="tsina" title="分享到新浪微博"></a>
        <a href="#" class="bds_weixin" data-cmd="weixin" title="分享到微信"></a>
        <a href="#" class="bds_ibaidu" data-cmd="ibaidu" title="分享到百度中心"></a>
        <a href="#" class="bds_bdhome" data-cmd="bdhome" title="分享到百度新首页"></a>
        <a href="#" class="bds_tieba" data-cmd="tieba" title="分享到百度贴吧"></a>
    </div>
    <script>
        window._bd_share_config={"common":{"bdSnsKey":{},"bdText":"","bdMini":"2","bdMiniList":false,"bdPic":"","bdStyle":"0","bdSize":"16"},"share":{}};with(document)0[(getElementsByTagName('head')[0]||body).appendChild(createElement('script')).src='http://bdimg.share.baidu.com/static/api/js/share.js?v=89860593.js?cdnversion='+~(-new Date()/36e5)];
    </script>
code;
    }


    /**
     * 生成tdk 相关html
     * @access private
     */
    private static function form_tdk_html($title, $keyword, $description)
    {
        $title_template = "<title>%s</title>";
        $keywords_template = "<meta name='keywords' content='%s'>";
        $description_template = "<meta name='description' content='%s'>";
        return sprintf($title_template, $title) . sprintf($keywords_template, $keyword) . sprintf($description_template, $description);
    }

    /**
     * 获取图片集调用链接
     * @access public
     * @param $node_id
     * @return mixed
     */
    private static function getImgList($node_id)
    {
        return Cache::remember('imglist', function () use ($node_id) {
            $imglist = Db::name('imglist')->where('node_id', $node_id)->where('status', '10')->select();
            $imgset = [];
            foreach ($imglist as $imgs) {
                $en_name = $imgs['en_name'];
                $imgser = $imgs['imgser'];
                $perimgset = [];
                if ($imgser) {
                    foreach (unserialize($imgser) as $val) {
                        $perimgset[] = [
                            'src' => '/images/' . $val['imgname'],
                            'title' => $val['title'],
                            'alt' => $val['title'],
                            //默认没有链接的话跳转到首页
                            'href' => $val['link'] ?: '/',
                        ];
                    }
                }
                $imgset[$en_name] = $perimgset;
            }
            return $imgset;
        });
    }


    /**
     * 获取当前栏目的菜单信息
     * @access private
     * @todo 如果是menu或者是index  需要优化当前栏目比如前段需要表示出来当前页 并且给出有区别的样式
     * @param $menu_ids 栏目的ids
     * $param $site_id 站点的id
     * $param $site_name 站点的name
     * $param $node_id 节点的id
     * $param $url 网站的根目录
     * $param $tag 标志  index、menu、 envmenu、detail
     * $param $generate_name 生成菜单的英文名
     * $param $menu_id 菜单的id
     * @return array|false|\PDOStatement|string|\think\Collection
     */
    private static function getTreeMenuInfo($menu_ids, $site_id, $site_name, $node_id, $url, $tag, $generate_name, $menu_id)
    {
        //需要把首页链接追加进来 而且需要在首位
        $menu = Menu::getMergedMenu($menu_ids, $site_id, $site_name, $node_id);
        //循环为树状结构
        $tree = array();
        //创建基于主键的数组引用
        $refer = array();
        foreach ($menu as $key => $data) {
            unset($data['generate_name']);
            unset($data['flag']);
            unset($data['type_id']);
            unset($data['detailtemplate']);
            unset($data['listtemplate']);
            unset($data['covertemplate']);
            $menu[$key] = $data;
            $refer[$data['id']] = &$menu[$key];
        }
        //循环中还需要设置下当前menu相关信息
        foreach ($menu as $key => $data) {
            // 判断是否存在parent
            $is_current = false;
            if ($menu_id == $data['id']) {
                //表示当前选中该菜单
                $is_current = true;
            }
            $parentId = $data['p_id'];
            $menu[$key]['current'] = $is_current;
            if ($parentId == 0) {
                //根节点元素
                $tree[] = &$menu[$key];
            } else {
                //需要把上级的文件也设置为相关选中操作；
                if ($is_current) {
                    $path = $menu[$key]['path'];
                    $menu_idarr = array_filter(explode(',', $path));
                    foreach ($menu_idarr as $menu_id) {
                        $menu[$menu_id]['current'] = $is_current;
                    }
                }
                if (isset($refer[$parentId])) {
                    //当前正在遍历的父亲节点的数据
                    $parent = &$refer[$parentId];
                    //把当前正在遍历的数据赋值给父亲类的  children
                    $parent['child'][] = &$menu[$key];
                }
            }
        }
        if ($tag == 'index') {
            //首页默认选中的
            $is_current = true;
        }
        array_unshift($tree, ['id' => 0, 'name' => '首页', 'path' => '', 'p_id' => 0, 'title' => $site_name, 'href' => $url, 'current' => $is_current]);
        return $tree;
    }


    /**
     * 截取中文字符串  utf-8
     * @param String $str 要截取的中文字符串
     * @param $len
     * @return mixed
     */
    public static function utf8chstringsubstr($str, $len)
    {
        for ($i = 0; $i < $len; $i++) {
            $temp_str = substr($str, 0, 1);
            if (ord($temp_str) > 127) {
                $i++;
                if ($i < $len) {
                    $new_str[] = substr($str, 0, 3);
                    $str = substr($str, 3);
                }
            } else {
                $new_str[] = substr($str, 0, 1);
                $str = substr($str, 1);
            }
        }
        //把数组元素组合为string
        return join($new_str);
    }


    /**
     * 获取面包屑 相关信息
     * @access
     */
    public static function getBreadCrumb($tag, $url, $allmenu, $menu_id = 0)
    {
        $breadcrumb = [
            ['text' => '首页', 'href' => $url, 'title' => '首页'],
        ];
        switch ($tag) {
            case 'index':
                break;
            case 'menu':
            case 'detail':
                //详情 或者 列表页面
                $menu_path = \app\tool\model\Menu::Where('id', $menu_id)->field('path')->find();
                $path = $menu_path['path'];
                $p_idarr = array_filter(explode(',', $path));
                array_push($p_idarr, $menu_id);
                foreach ($p_idarr as $v) {
                    $pmenu = $allmenu[$v];
                    $perbreadcrumb = [
                        'text' => $pmenu['name'],
                        'href' => $pmenu['href'],
                        'title' => $pmenu['title']
                    ];
                    array_push($breadcrumb, $perbreadcrumb);
                }
                break;
            case 'query':
                array_push($breadcrumb, [
                    'text' => '查询结果',
                    'href' => '/',
                    'title' => '查询'
                ]);
                break;
            case 'activity':
                break;
        }
        return $breadcrumb;
    }

    /**
     *
     */
    public static function getsiblingMenu($url, $allmenu, $menu_id = 0)
    {

    }


}
