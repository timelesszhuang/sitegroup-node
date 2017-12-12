<?php
/**
 * 主要是 爬虫的相关数据
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-8-28
 * Time: 下午2:05
 */

namespace app\index\traits;

use think\Request;

trait Pv
{
    /**
     * @param $ip
     * @return mixed
     * 调用接口根据ip查询地址
     */
    public function get_ip_info($ip)
    {
        $curl = curl_init();
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
    public function pv($siteinfo, $page)
    {
        session_write_close();
        $request = Request::instance();
        $nowip = $request->ip();
        $data = $this->get_ip_info($nowip);
        if ($data) {
            $pvdata['node_id'] = $siteinfo['node_id'];
            $pvdata['site_id'] = $siteinfo['id'];
            $pvdata['country'] = $data['data']['country'];
            $pvdata['country_id'] = $data['data']['country_id'];
            $pvdata['area_id'] = $data['data']['area_id'];
            $pvdata['region'] = $data['data']['region'];
            $pvdata['region_id'] = $data['data']['region_id'];
            $pvdata['city'] = $data['data']['city'];
            $pvdata['city_id'] = $data['data']['city_id'];
            $pvdata['ip'] = $data['data']['ip'];
            $pvdata['create_time'] = time();
            $pvdata['referer'] = $page;
            \app\index\model\Pv::create($pvdata);
            return;
        }
        $pvdata['node_id'] = $siteinfo['node_id'];
        $pvdata['site_id'] = $siteinfo['id'];
        $pvdata['country'] = '';
        $pvdata['country_id'] = 0;
        $pvdata['area_id'] = 0;
        $pvdata['region'] = '';
        $pvdata['region_id'] = 0;
        $pvdata['city'] = '';
        $pvdata['city_id'] = 0;
        $pvdata['ip'] = $nowip;
        $pvdata['create_time'] = time();
        $pvdata['referer'] = $page;
        \app\index\model\Pv::create($pvdata);
    }
}