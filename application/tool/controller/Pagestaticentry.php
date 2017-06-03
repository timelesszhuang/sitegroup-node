<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\Cache;
use think\Db;
use think\View;
use think\Config;

/**
 * 页面静态化 入口文件
 * 该文件接收请求重新生成页面
 */
class Pagestaticentry extends Common
{

    /*
        public function index()
        {
            $view = new View();
            $content = $view->fetch('template/index.html');
            echo $content;
            file_put_contents('a.html', $content);
        }*/

    /**
     * 清空缓存相关操作
     * @access public
     */
    public function clearCache()
    {
        Cache::clear();
    }


    /**
     * 页面静态化入口文件  第一次请求过来的 时候会请求
     * @todo 有问题的话 直接 exit 然后更新数据到后台中
     * @access public
     */
    public function index()
    {
        /*
                //首先检查下请求的来源域名
                $this->checkOrigin();
                //检查下站点的id
                $this->check_nodeid();
        */

        /**该站点的相关设置信息获取******************************************/


        //第一次进来的时候就需要获取下全部的栏目 获取全部的关键词
        $site_id = Config::get('site.SITE_ID');
        $info = Site::getSiteInfo();
        print_r($info);
        $node_id = $info['node_id'];
        $site_name = $info['site_name'];
        //站点名

        /********************************************/


        /**获取菜单相关信息**************************************************/

        //菜单相关信息
        $menu = $info['menu'];
        $menuinfo = Menu::getMenuInfo($menu);
        print_r($menuinfo);
        //10 不是主站 20 表示是主站
        $is_mainsite = $info['main_site'];

        /***********************************************************/


        /**获取链轮等相关数据*****************************************/
        //链轮的类型
        $chain_type = '';
        //该站点需要链接到的站点
        $next_site = [];
        //主站是哪个
        $main_site = [];
        if ($is_mainsite == '10') {
            //表示不是主站
            //站点类型 用于取出主站 以及链轮类型 来
            $site_type_id = $info['site_type'];
            list($chain_type, $next_site, $main_site) = Site::getLinkInfo($site_type_id, $site_id, $site_name, $node_id);
        }

        /*echo '链轮类型';
        print_r($chain_type);
        echo '连接到的站点';
        print_r($next_site);
        echo '链接到的主站';
        print_r($main_site);
        */
        /*******************************************`/
         *
         *
         * /**获取关键词等相关数据*****************************************/
        //关键词数据  会组织为 三级的数组结构
        $keyword = Keyword::getKeywordInfo($info['keyword_ids'], $site_id, $site_name, $node_id);
        // print_r($keyword);
        /**获取关键词等相关数据*****************************************/

        /**获取对应的手机站 为空则不需要替换**********************************************************/
        $m_site_info = [];
        //如果是pc 站的话 获取下对应的手机站
        if ($info['is_mobile'] == '10') {
            //是 pc
            $type = 'pc';
            $m_site_id = $info['m_site_id'];
            if ($m_site_id) {
                //这个地方如果取出来不是手机站的话 需要给出提示错误
                $m_site_info = Db::name('site')->where(['id' => $m_site_id])->field('id,url')->find();
            }
        } else {
            $type = 'm';
        }


        /***************************************************************************/


        /**获取友链等相关信息********************************************************/
        //该网站指向友链网站
        $partnersite_info = [];
        if ($info['link_id']) {
            //友链信息
            $partnersite_info = Db::name('links')->where(['id' => ['in', array_filter(explode(',', $info['link_id']))]])->field('id,name,domain')->select();
            print_r($partnersite_info);
        }
        /**************************************************/

        exit;
    }


}
