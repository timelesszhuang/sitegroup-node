<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\Cache;
use think\Config;
use think\Db;


/**
 * 公共的相关操作的 工具
 */
class Commontool extends Common
{

    /**
     * 清除缓存 信息
     * @access public
     */
    public function clearCache()
    {
        file_put_contents("111.txt", "dddd");
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
        //首先从缓存中获取数据 缓存中没有的话 再到数据库中获取
        if ($mobile_info = Cache::get(Config::get('site.CACHE_LIST')['MOBILE_SITE_INFO'])) {
            return $mobile_info;
        }
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
        $mobile_info = [$m_site_url, $m_redirect_code];
        Cache::set(Config::get('site.CACHE_LIST')['MOBILE_SITE_INFO'], $mobile_info, Config::get('site.CACHE_TIME'));
        return $mobile_info;
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
        $page_name = '首页';
        $page_type = 'index';
        list($title, $keyword, $description) = self::getDbPageTDK($page_id, $node_id, $site_id, $page_type);
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
                'akeyword_id' => '',
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
        list($title, $keyword, $description) = self::getDbPageTDK($page_id, $node_id, $site_id, $page_type);
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
                'akeyword_id' => $a_keyword_id,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }
        return [$title, $keyword, $description];
    }


    /**
     * 获取配置文件中的栏目页面的 tdk 相关数据
     * @param $keyword_info 关键词相关
     * @param $page_id 页面的id  比如 contactme
     * @param $site_id 站点的id
     * @param $node_id 节点的id
     * @param $menu_name 栏目名
     * @return array
     */
    public static function getEnvMenuPageTDK($keyword_info, $page_id, $page_name, $site_id, $site_name, $node_id, $menu_name)
    {
        $page_type = 'envmenu';
        list($title, $keyword, $description) = self::getDbPageTDK($page_id, $node_id, $site_id, $page_type);
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
                'akeyword_id' => $a_keyword_id,
                'create_time' => time(),
                'update_time' => time()
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
    public static function getDetailPageTDK($keyword_info, $site_id, $node_id, $articletitle, $articlecontent, $a_keyword_id)
    {
        //需要知道 栏目的关键词等
        //$keyword_info, $site_id, $node_id, $articletitle, $articlecontent
        // 栏目页面的 TDK 获取 A类关键词随机选择
        //栏目页的 title：C类关键词多个_A类关键词1-文章标题
        //        keyword：C类关键词多个,A类关键词
        //        description:拼接一段就可以栏目名
        $a_child_info = [];
        foreach ($keyword_info as $k => $v) {
            if ($v['id'] == $a_keyword_id) {
                $a_child_info = $v['children'];
                break;
            }
        }
        if (!$a_child_info) {
            //需要处理下 如果没有的话 怎么处理
            return ['', '', ''];
        }
        $b_child_info = $a_child_info[array_rand($a_child_info)];
        $c_keyword_arr = [];
        if (array_key_exists('children', $b_child_info)) {
            $c_child_info = $b_child_info['children'];
            $c_rand_key = array_rand($c_child_info, 3);
            foreach ($c_rand_key as $v) {
                $c_keyword_arr[] = $c_child_info[$v];
            }
        }
        $c_keywordname_arr = array_column($c_keyword_arr, 'name');
        $title = implode('-', $c_keywordname_arr) . $articletitle;
        $keyword = implode(',', $c_keywordname_arr);
        $description = $articlecontent;
        return [$title, $keyword, $description];
    }


    /**
     * 从数据库中获取页面的相关信息
     * @access public
     */
    public static function getDbPageTDK($page_id, $node_id, $site_id, $page_type)
    {
        $title = '';
        $page_info = Db::name('site_pageinfo')->where(['page_id' => $page_id, 'node_id' => $node_id, 'site_id' => $site_id, 'page_type' => $page_type])->field('title,keyword,description')->find();
        if ($page_info) {
            return [$page_info['title'], $page_info['keyword'], $page_info['description']];
        }
        return ['', '', ''];
    }


    /**
     * 获取 文章列表 获取十条　文件名如 article1 　article2
     * @access public
     * @param $type_id
     * @param $site_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getArticleList($type_id, $site_id, $limit = 10)
    {
        //  首先从数据库中获取 该站点已经静态化到的文章的 id 防止出现 404 问题
        $static_id = SELF::getStaticRecordId($site_id, $type_id, 'article');
        if (!$static_id) {
            return [];
        }
        $article = Db::name('Article')->where(['articletype_id' => $type_id, 'id' => ['ELT', $static_id]])->field('id,title')->order('id desc')->limit($limit)->select();
        return $article;
    }


    /**
     * 获取 问题列表 获取十条　文件名如 question1 　question2
     * @access public
     */
    public static function getQuestionList($type_id, $site_id, $limit = 10)
    {
        //  首先从数据库中获取 该站点已经静态化到的问题的文章 id 防止出现 404 问题
        $static_id = SELF::getStaticRecordId($site_id, $type_id, 'question');
        if (!$static_id) {
            return [];
        }
        $question = Db::name('Question')->where(['type_id' => $type_id, 'id' => ['ELT', $static_id]])->field('id,question')->order('id desc')->limit($limit)->select();
        return $question;
    }


    /**
     * 获取 零散段落 分类  文件名如 article1 　article2
     * @access public
     */
    public static function getScatteredArticleList($type_id, $site_id, $limit = 10)
    {
        //  首先从数据库中获取 该站点已经静态化到的零散段落的 id 防止出现 404 问题
        $static_id = SELF::getStaticRecordId($site_id, $type_id, 'scatteredarticle');
        if (!$static_id) {
            return [];
        }
        $article = Db::name('Scattered_title')->where(['articletype_id' => $type_id, 'id' => ['ELT', $static_id]])->field('id,title')->order('id desc')->limit($limit)->select();
        return $article;
    }


    /**
     * 获取某个分类已经静态到的页面
     * @access public
     */
    public static function getStaticRecordId($site_id, $type_id, $type_name)
    {
        //获取下该目录已经静态化到的目录
        $count_arr = Db::name('ArticleSyncCount')->where(['site_id' => $site_id, 'type_id' => $type_id, 'type_name' => $type_name])->field('count')->find();
        if ($count_arr) {
            return $count_arr['count'];
        }
        return 0;
    }


    /**
     * 获取友链
     * @param $link_id 站点中设置的友链ids 多个数据
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getPatternLink($link_id)
    {
        //友链信息
        $partnersite_info = Db::name('links')->where(['id' => ['in', array_filter(explode(',', $link_id))]])->field('id,name,domain')->select();
        return $partnersite_info;
    }


    /**
     * 获取公共代码
     * @access public
     */
    public static function getCommonCode($code_ids)
    {
        $code = Db::name('code')->where(['id' => ['in', array_filter(explode(',', $code_ids))]])->field('code')->select();
        return $code;
    }


    /**
     * 获取页面中 需要的文章列表 内容列表 问答列表
     * @access public
     */
    public static function getDbArticleListId($menu_ids, $site_id, $tag, $page_id)
    {
        if ($tag != 'detail') {
            //数据库中取出数据
            $list = Db::name('SitePageinfo')->where(['site_id' => $site_id, 'page_type' => $tag, 'page_id' => $page_id])->field('articletype_id,questiontype_id,scatteredarticletype_id')->find();
            if ($list['articletype_id']) {
                return array_values($list);
            }
        }
        //获取页面中  会用到的 文章列表 问题列表 零散段落列表
        //配置的菜单信息  用于获取 文章的列表
        $type_id_arr = Menu::getTypeIdInfo($menu_ids);
        //获取十条　文章　问答　断句
        if (array_key_exists('article', $type_id_arr)) {
            $key = array_rand($type_id_arr['article']);
            $article_id = $type_id_arr['article'][$key]['id'];
        }
        if (array_key_exists('question', $type_id_arr)) {
            $key = array_rand($type_id_arr['question']);
            $questiontype_id = $type_id_arr['question'][$key]['id'];
        }
        if (array_key_exists('scatteredarticle', $type_id_arr)) {
            $key = array_rand($type_id_arr['scatteredarticle']);
            $scatteredarticletype_id = $type_id_arr['scatteredarticle'][$key]['id'];
        }
        if ($tag != 'detail') {
            //把获取到的数据存储到数据库中
            Db::name('SitePageinfo')->where(['site_id' => $site_id, 'page_type' => $tag, 'page_id' => $page_id])->update([
                'articletype_id' => $article_id,
                'questiontype_id' => $questiontype_id,
                'scatteredarticletype_id' => $scatteredarticletype_id
            ]);
        }
        return [$article_id, $questiontype_id, $scatteredarticletype_id];
    }


    /**
     * 获取 页面中必须的元素
     *
     * @param string $tag index 或者 menu detail
     * @param string $param 如果是  index  第二第三个参数没用
     *                              menu 第二个参数$param表示   $page_id 也就是菜单的英文名 第三个参数 $param2 表示 菜单名 menu_name   $param3 是 menu_id
     *                              envmenu 第二个参数$param表示   $page_id 也就是菜单的英文名 第三个参数 $param2 表示 菜单名 menu_name
     *                              detail   第二个参数$param表示  $articletitle 用来获取文章标题 第三个参数 $param2 表示 文章的内容   $param3 是 a_keyword_id
     * @param string $param2
     * @return array
     */
    public static function getEssentialElement($tag = 'index', $param = '', $param2 = '', $param3 = '')
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $site_id, $site_name, $node_id);
        $menu = Menu::getMergedMenu($siteinfo['menu'], $site_id, $site_name, $node_id);
        //获取站点的类型 手机站的域名 手机站点的跳转链接
        list($m_url, $redirect_code) = self::getMobileSiteInfo();
        switch ($tag) {
            case 'index':
                $page_id = 'index';
                //然后获取 TDK 等数据  首先到数据库
                list($title, $keyword, $description) = self::getIndexPageTDK($keyword_info, $site_id, $site_name, $node_id, $siteinfo['com_name']);
                break;
            case 'menu':
                //菜单 页面的TDK
                $page_id = $param;
                $menu_name = $param2;
                $menu_id = $param3;
                list($title, $keyword, $description) = self::getMenuPageTDK($keyword_info, $page_id, $menu_name, $site_id, $site_name, $node_id, $menu_id, $menu_name);
                break;
            case 'detail':
                //详情页面
                $page_id = '';
                $articletitle = $param;
                $articlecontent = $param2;
                $a_keyword_id = $param3;
                list($title, $keyword, $description) = self::getDetailPageTDK($keyword_info, $site_id, $node_id, $articletitle, $articlecontent, $a_keyword_id);
                //需要考虑到一个问题  如果前台取消了选择的关键词的话  a_keyword_id 取出对应的关键词会取不到
                if (!$title) {
                    $a_keyword_key = array_rand($keyword_info, 1);
                    $new_a_keyword_id = $keyword_info[$a_keyword_key]['id'];
                    list($title, $keyword, $description) = self::getDetailPageTDK($keyword_info, $site_id, $node_id, $articletitle, $articlecontent, $new_a_keyword_id);
                    //更新一下数据库中的页面的a类 关键词
                    Db::name('SitePageinfo')->where([
                        'site_id' => $site_id,
                        'node_id' => $node_id,
                        'akeyword_id' => $a_keyword_id,
                    ])->update([
                        'akeyword_id' => $new_a_keyword_id,
                        'update_time' => time()
                    ]);
                }
                break;
            case 'envmenu':
                //.env 文件中的配置菜单信息
                $page_id = $param;
                $menu_name = $param2;
                list($title, $keyword, $description) = self::getEnvMenuPageTDK($keyword_info, $page_id, $menu_name, $site_id, $site_name, $node_id, $menu_name);
                break;
        }

        //获取页面中  会用到的 文章列表 问题列表 零散段落列表
        //配置的菜单信息  用于获取 文章的列表
        list($article_id, $question_id, $scatteredarticle_id) = self::getDbArticleListId($siteinfo['menu'], $site_id, $tag, $page_id);
        $article_list = self::getArticleList($article_id, $site_id);
        $question_list = self::getQuestionList($question_id, $site_id);
        $scatteredarticle_list = self::getScatteredArticleList($scatteredarticle_id, $site_id);

        //获取友链
        $partnersite = self::getPatternLink($siteinfo['link_id']);

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
        $commonjscode = self::getCommonCode($siteinfo['public_code']);
        //head前后的代码
        $before_head = $siteinfo['before_header_jscode'];
        $after_head = $siteinfo['other_jscode'];
        //公司名称
        $com_name = $siteinfo['com_name'];
        return [
            $com_name, $title, $keyword, $description,
            $m_url, $redirect_code, $menu, $before_head,
            $after_head, $chain_type, $next_site,
            $main_site, $partnersite, $commonjscode,
            $article_list, $question_list, $scatteredarticle_list
        ];
    }


}
