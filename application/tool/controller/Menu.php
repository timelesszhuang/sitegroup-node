<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\tool\model\SiteErrorInfo;
use think\Config;
use think\Cache;
use think\Db;


/**
 * 菜单 栏目 相关操作 关键词相关设置
 */
class Menu extends Common
{

    /**
     * 菜单相关操作 返回菜单相关数据 参数是 一系列的id 数据
     * @access public
     * @param string $menu 菜单id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getMenuInfo($menu_ids, $site_id, $site_name, $node_id)
    {
        //首先从缓存中获取数据 缓存中没有的话 再到数据库中获取
        if ($menu = Cache::get(Config::get('site.CACHE_LIST')['MENU'])) {
            return $menu;
        }
        $where['id'] = ['in', array_filter(explode(',', $menu_ids))];
        $field = 'id,name,title,generate_name,flag,type_id,content';
        $menu = Db::name('menu')->where($where)->order("sort", "desc")->field($field)->select();
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
        //利用文件缓存缓存下文件
        Cache::set(Config::get('site.CACHE_LIST')['MENU'], $menu, Config::get('site.CACHE_TIME'));
        return $menu;
    }

    /**
     * 获取详情型 的 菜单信息
     * @access public
     */
    public static function getDetailMenuInfo($menu_ids, $site_id, $site_name, $node_id)
    {
        //获取菜单信息
        $menu = self::getMenuInfo($menu_ids, $site_id, $site_name, $node_id);
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
     * 获取 env 配置中的菜单信息
     * @access public
     */
    public static function getEnvMenuInfo()
    {
        return EnvMenu::getEnv();
    }


    /**
     * 获取合并之后的菜单信息
     * @access public
     */
    public static function getMergedMenu($menu_ids, $site_id, $site_name, $node_id)
    {
        $menu = self::getMenuInfo($menu_ids, $site_id, $site_name, $node_id);
        $env_menu = self::getEnvMenuInfo();
        //把 配置的菜单 跟数据库中的菜单合并为数组
        $merged_menu = [];
        if ($menu && $env_menu) {
            $merged_menu = array_merge($menu, $env_menu);
        } else {
            $merged_menu = $menu;
        }
        foreach ($merged_menu as $k => $v) {
            if (array_key_exists('flag', $v)) {
                //数据库中配置的菜单
                if ($v['flag'] == 1) {
                    $v['generate_name'] = '/' . $v['generate_name'] . '.html';
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
                    $v['generate_name'] = '/' . $type . '/' . $v['id'] . '.html';
                }
            } else {
                //env 中配置的菜单
                $v['generate_name'] = '/' . $v['generate_name'] . '.html';
            }
            $merged_menu[$k] = $v;
        }
        return $merged_menu;
    }


    /**
     * 根据站点的menuids 获取文章所属的 type_id、type_name、
     * 不需要详情型的文章信息
     * @access public
     */
    public static function getTypeIdInfo($menu_ids)
    {
        //首先从缓存中获取数据 缓存中没有的话 再到数据库中获取
        if ($type_id_arr = Cache::get(Config::get('site.CACHE_LIST')['MENUTYPEID'])) {
            return $type_id_arr;
        }
        $menu_id_arr = array_filter(explode(',', $menu_ids));
        $field = 'id,name,flag,flag_name,type_id,type_name';
        $where = [
            'id' => ['in', $menu_id_arr],
            'flag' => ['neq', 1],
        ];
        //获取站点所有的菜单
        $menu = Db::name('menu')->where($where)->field($field)->select();
        $type_id_arr = [];
        foreach ($menu as $k => $v) {
            switch ($v['flag']) {
                case 2:
                    $type = 'question';
                    break;
                case 3:
                    $type = 'article';
                    break;
                case 4:
                    $type = 'scatteredarticle';
                    break;
                case 5:
                    $type = 'product';
                    break;
            }
            $type_arr = [
                //文章类型的id
                'id' => $v['type_id'],
                //文章类型的name
                'name' => $v['type_name'],
                //菜单的id
                'menu_id' => $v['id'],
                //菜单的name
                'menu_name' => $v['name']
            ];
            if (!array_key_exists($type, $type_id_arr)) {
                $type_id_arr[$type] = [];
            }
            $type_id_arr[$type][] = $type_arr;
        }
        //首先从缓存中获取数据 缓存中没有的话 再到数据库中获取
        Cache::set(Config::get('site.CACHE_LIST')['MENUTYPEID'], $type_id_arr, Config::get('site.CACHE_TIME'));
        return $type_id_arr;
    }


}
