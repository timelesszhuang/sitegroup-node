<?php
/**
 * 主要是 爬虫的相关数据
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-8-28
 * Time: 下午2:05
 */

namespace app\index\controller;

use app\index\model\Useragent;

trait SpiderComefrom
{
    public function spidercomefrom($siteinfo)
    {
        $data['node_id'] = $siteinfo['node_id'];
        $data['site_id'] = $siteinfo['id'];
        $data['useragent'] = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match("/Baiduspider/i", $_SERVER['HTTP_USER_AGENT'])) {
            //百度搜索
            $data['engine'] = "baidu";
            Useragent::create($data);
        } elseif (preg_match("/Sogou/i", $_SERVER['HTTP_USER_AGENT'])) {
            //搜狗搜索
            $data['engine'] = "Sogou";
            Useragent::create($data);
        } elseif (preg_match("/360/i", $_SERVER['HTTP_USER_AGENT'])) {
            $data['engine'] = "360haosou";
            Useragent::create($data);
        } elseif (preg_match("/Googlebot/i", $_SERVER['HTTP_USER_AGENT'])) {
            $data['engine'] = 'google';
            Useragent::create($data);
        }
    }
}