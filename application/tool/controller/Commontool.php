<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\Cache;
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
    public static function clearCache()
    {
        if (Cache::clear()) {
            exit(['status' => 'success', 'msg' => '清除缓存成功。']);
        }
        exit(['status' => 'failed', 'msg' => '清除缓存失败。']);
    }

    /**
     * 获取手机站的域名 跟 跳转的js
     * @access public
     */
    public static function getMobileSiteInfo()
    {
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
        return [$m_site_url, $m_redirect_code];
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
    public static function getIndexPageTDK($keyword_info, $site_id, $node_id, $com_name)
    {
        $title = '';
        $keyword = '';
        $description = '';
        list($title, $keyword, $description) = self::getDbPageTDK('index', $node_id, $site_id);
        if (empty($title)) {
            //tdk 是空的 需要 重新 从关键词中获取
            //首页的title ： A类关键词1_A类关键词2_A类关键词3-公司名
            //     keyword  ： A类关键词1,A类关键词2,A类关键词3
            //     description : A类关键词拼接
            $a_keywordname_arr = array_column($keyword_info, 'name');
            $title = implode('_', $a_keywordname_arr) . '-' . $com_name;
            $keyword = implode(',', $a_keywordname_arr);
            $description = implode('，', $a_keywordname_arr) . '，' . $com_name;
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
    public static function getMenuPageTDK($keyword_info, $page_id, $site_id, $node_id, $menu_name)
    {
        list($title, $keyword, $description) = self::getDbPageTDK($page_id, $node_id, $site_id);
        if (empty($title)) {
            // 栏目页面的 TDK 获取 A类关键词随机选择
            //栏目页的 title：B类关键词多个_A类关键词1-栏目名
            //        keyword：B类关键词多个,A类关键词
            //        description:拼接一段就可以栏目名
            $a_keyword_key = array_rand($keyword_info, 1);
            $a_child_info = $keyword_info[$a_keyword_key];
            $a_name = $a_child_info['name'];
            if (!array_key_exists('children', $a_child_info)) {
                return ['', '', ''];
            }
            $b_keyword_info = $a_child_info['children'];

            $b_keywordname_arr = array_column($b_keyword_info, 'name');
            $title = implode('_', $b_keywordname_arr) . '_' . $a_name . '-' . $menu_name;
            $keyword = implode(',', $b_keywordname_arr) . ',' . $a_name;
            $description = implode('，', $b_keywordname_arr) . '，' . $a_name . '，' . $menu_name;
        }
        return [$title, $keyword, $description];
    }


    /**
     * 详情页 的TDK 获取   需要有固定的关键词
     * @param $keyword_info 关键词相关
     * @param $page_id 页面的id  比如 contactme
     * @param $site_id 站点的id
     * @param $node_id 节点的id
     * @param $title
     * @param $content
     * @return array
     */
    public static function getDetailPageTDK($keyword_info, $page_id, $site_id, $node_id, $articletitle, $articlecontent)
    {

    }


    /**
     * 从数据库中获取页面的相关信息
     * @access public
     */
    public static function getDbPageTDK($page_id, $node_id, $site_id)
    {
        $title = '';
        $page_info = Db::name('site_pageinfo')->where(['page_id' => $page_id, 'node_id' => $node_id, 'site_id' => $site_id])->field('title,keyword,description')->find();
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
    public static function getArticleList($type_id, $site_id)
    {
        $article = Db::name('Article')->where(['articletype_id' => $type_id])->field('id,title')->order('id desc')->select();
        return $article;
    }


    /**
     * 获取 问题列表 获取十条　文件名如 question1 　question2
     * @access public
     */
    public static function getQuestionList($type_id, $site_id)
    {
        $question = Db::name('Question')->where(['type_id' => $type_id])->field('id,question')->order('id desc')->select();
        return $question;
    }


    /**
     * 获取 零散段落 分类  文件名如 article1 　article2
     * @access public
     */
    public static function getScatteredArticleList($type_id, $site_id)
    {
        $article = Db::name('Scattered_title')->where(['articletype_id' => $type_id])->field('id,title')->order('id desc')->select();
        return $article;
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

}
