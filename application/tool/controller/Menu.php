<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\tool\model\SiteErrorInfo;
use think\Cache;
use think\Config;
use think\Db;


/**
 * 菜单 栏目 相关操作 关键词相关设置
 */
class Menu extends Common
{

    /**
     * 菜单相关操作 返回菜单相关数据 参数是 一系列的id 数据
     * @access public
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getMenuInfo()
    {
        $menu_ids = $this->menu_ids;
        $site_id = $this->site_id;
        $site_name = $this->site_name;
        $node_id = $this->node_id;
        $key = 'menu';
        $menu = Cache::remember($key, function () use ($menu_ids, $site_id, $site_name, $node_id) {
            $menu_idarr = array_filter(explode(',', $menu_ids));
            $where['node_id'] = $node_id;
            $where['id'] = ['in', $menu_idarr];
            $field = 'id,name,path,p_id,title,generate_name,flag,type_id,content,detailtemplate,listtemplate,covertemplate,visibility';
            $menu = Db::name('menu')->where($where)->order("sort", "desc")->field($field)->select();
            //获取下边的子孙菜单
            foreach ($menu_idarr as $v) {
                $pmenulist = Db::name('menu')->Where('path', 'like', "%,$v,%")->where('node_id', $node_id)->order("sort", "desc")->field($field)->select();
                $menu = array_merge($menu, $pmenulist);
            }
            //还需要获取
            if (empty($menu)) {
                //如果 bc 类关键词没有的话 应该提示 bc 类关键词不足等
                $site_info = new SiteErrorInfo();
                $site_info->addError([
                    'msg' => "{$site_name} 站点没有选择菜单。",
                    'operator' => '页面静态化',
                    'site_id' => $site_id,
                    'site_name' => $site_name,
                    'node_id' => $node_id,
                ]);
            }
            return $menu;
        });
        Cache::tag(self::$clearableCacheTag, [$key]);
        return $menu;
    }

    /**
     * 获取详情型 的 菜单信息
     * @access public
     */
    public function getDetailMenuInfo()
    {
        //获取菜单信息
        $menu = $this->getMenuInfo();
        $detail_menu = [];
        foreach ($menu as $k => $v) {
            //需要获取详情型的页面
            if ($v['flag'] == 1) {
                $detail_menu[] = $v;
            }
        }
        return $detail_menu;
    }


    /**
     * 获取合并之后的菜单信息
     * @access public
     */
    public function getMergedMenu()
    {
        $menulist = $this->getMenuInfo();
        $menu = [];
        foreach ($menulist as $k => $v) {
            //数据库中配置的菜单
            if ($v['flag'] == 1) {
                $v['href'] = '/' . $v['generate_name'] . '.html';
            } else {
                $type = '';
                switch ($v['flag']) {
                    case '2':
                        //问答分类
                        $type = 'questionlist';
                        break;
                    case '3':
                        //文章分类
                        $type = 'articlelist';
                        break;
                    case '4':
                        //零散段落分类
                        $type = 'newslist';
                        break;
                    case '5':
                        //产品分类
                        $type = 'productlist';
                }
                $v['href'] = "/{$type}/{$v['generate_name']}.html";
            }
            $menu[$v['id']] = $v;
        }
        return $menu;
    }

}
