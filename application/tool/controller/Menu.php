<?php

namespace app\tool\controller;

use app\common\controller\Common;
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
    public static function getMenuInfo($menu_ids)
    {
        //首先从缓存中获取数据 缓存中没有的话 再到数据库中获取
        if ($menu = Cache::get(Config::get('site.CACHE_LIST')['MENU'])) {
            return $menu;
        }
        $where['id'] = ['in', array_filter(explode(',', $menu_ids))];
        $menu = Db::name('menu')->where($where)->select();
        //利用文件缓存缓存下文件
        Cache::set(Config::get('site.CACHE_LIST')['MENU'], $menu, Config::get('site.CACHE_TIME'));
        return $menu;
    }


    /**
     * 获取 env 配置中的菜单信息
     * @access public
     */
    public static function getEnvMenuInfo()
    {
        return EnvMenu::getEnv();
    }


}
