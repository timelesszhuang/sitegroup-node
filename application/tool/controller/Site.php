<?php

namespace app\tool\controller;

use app\index\model\Pv;
use app\tool\model\SiteErrorInfo;
use app\common\controller\Common;
use think\Cache;
use think\Config;
use think\Db;
use think\Request;


/**
 * 站点相关操作
 * 链轮类型
 * 获取主站链接
 * 友联获取
 * js 公共代码获取
 * 联系方式获取
 */
class Site extends Common
{

    /**
     * 获取链轮的相关信息
     *  两种链轮类型  1 循环链轮  需要返回  next_site 也就是本网站需要链接到的网站  main_site  表示主节点 从id 小的 链接到比较大的  最大的id 链接到最小的id 上
     *              2 金字塔型  需要返回要指向的 主节点  二级节点之间不需要互相链
     * @access public
     * @return mixed  第一个字段是 链轮的类型 10 表示 循环链轮 20 表示 金字塔型链轮
     * @todo  还要考虑到手机站的情况 手机站的互链情况
     */
    public static function getLinkInfo($site_type_id, $site_id, $site_name, $node_id)
    {
        //首先获取当前的节点id
        $site_type = Db::name('site_type')->where('id', $site_type_id)->find();
        $chain_type = $site_type['chain_type'];
        //10表示循环链轮 20 表示 金字塔型链轮
        //获取主节点////////////////////////////////////////////
        //返回 主站的域名 id 等
        //有可能没有设置主站  需要有个地方记录下错误信息
        $main_site = Db::name('site')->where(['site_type' => $site_type_id, 'main_site' => '20'])->field('id,site_name,url')->find();
        if (!$main_site) {
            //没有设置主节点 需要提示下错误信息
            $site_info = new SiteErrorInfo();
            $site_info->addError([
                'msg' => $site_type['name'] . "站点分类没有设置主站点",
                'operator' => '页面静态化',
                'site_id' => $site_id,
                'site_name' => $site_name,
                'node_id' => $node_id,
            ]);
            //错误信息
            exit;
        }
        //判断主节点是不是当前的节点
        if ($site_id == $main_site['id']) {
            $main_site = [];
        }
        $next_site = [];
        if ($chain_type == '10' && Db::name('site')->where(['site_type' => $site_type_id, 'main_site' => '10'])->count() > 2) {
            //如果该分类下的非主节点的数量小于 3个 则 不需要互相链接  否则形成的 互链 bug，容易被搜索引擎 K掉
            //链轮的时候为 id 小的 链接到id 大的，然后最终 id 最大的连接到 最小的id
            $chain_site = Db::name('site')->where(['site_type' => $site_type_id, 'main_site' => '10', 'id' => ['gt', $site_id]])->field('url,site_name')->find();
            if ($chain_site) {
                $next_site = $chain_site;
            } else {
                //说明没有取到id 比较大的
                //取下id 最小的
                $chain_site = Db::name('site')->where(['site_type' => $site_type_id, 'main_site' => '10'])->order('id asc')->field('url,site_name')->find();
                $next_site = $chain_site;
            }
        } else if ($chain_type == '20') {
            //表示金字塔型的 链轮
            //不需要返回 其他信息
        }
        return [$chain_type, $next_site, $main_site];
    }


    /**
     * 获取站点相关配置信息
     * @access public
     */
    public static function getSiteInfo()
    {
        if ($info = Cache::get(Config::get('site.CACHE_LIST')['SITEINFO'])) {
            return $info;
        }
        $site_id = Config::get('site.SITE_ID');
        //第一次进来的时候就需要获取下全部的栏目 获取全部的关键词
        $info = Db::name('site')->where('id', $site_id)->find();
        if (empty($info)) {
            //如果为空的话 处理方式
            //表示该
            exit("未找到站点id {$site_id} 的配置信息");
        }
        Cache::set(Config::get('site.CACHE_LIST')['SITEINFO'], $info, Config::get('site.CACHE_TIME'));
        return $info;
    }

    /**
     * @param $ip
     * @return mixed
     * 调用接口根据ip查询地址
     */
    public function get_ip_info($ip)
    {
        $curl = curl_init(); //这是curl的handle
        $url = "http://ip.taobao.com/service/getIpInfo.php?ip=$ip";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl, CURLOPT_HEADER, 0); //don't show header
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //相当关键，这句话是让curl_exec($ch)返回的结果可以进行赋值给其他的变量进行，json的数据操作，如果没有这句话，则curl返回的数据不可以进行人为的去操作（如json_decode等格式操作）
        curl_setopt($curl, CURLOPT_TIMEOUT, 2);
        $data = curl_exec($curl);
        return json_decode($data, true);
    }

    /**
     * 页面浏览一次存储一次
     * pv存储
     * @access public
     */
    public function pv()
    {
        $request = Request::instance();
        $nowip = "113.128.93.40";
        $data = $this->get_ip_info($nowip);
//        $pvdata = $request->post();
        $siteinfo = Site::getSiteInfo();
        $pvdata['node_id'] = $siteinfo['node_id'];
        $pvdata['site_id'] = $siteinfo['id'];
        //国家
        $pvdata['country'] = $data['data']['country'];
        $pvdata['country_id'] = $data['data']['country_id'];
        $pvdata['area_id'] = $data['data']['area_id'];
        $pvdata['region'] = $data['data']['region'];
        $pvdata['region_id'] = $data['data']['region_id'];
        $pvdata['city'] = $data['data']['city'];
        $pvdata['city_id'] = $data['data']['city_id'];
        $pvdata['ip'] = $data['data']['ip'];
        $pvdata['create_time'] = time();
        $pvdata['referer'] = '';
        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            $pvdata['referer'] = $_SERVER['HTTP_REFERER'];
        }
        Pv::create($pvdata);
    }

}
