<?php
/**
 * Created by PhpStorm.
 * oss 相关操作封装
 * User: 赵兴壮
 * Date: 17-6-12
 * Time: 上午9:44
 */

namespace app\tool\traits;


use app\tool\controller\Site;
use think\Cache;

trait Pingbaidu
{

    /**
     * ping 搜索引擎 主动收录
     * @access public
     */
    public function pingEngine()
    {
        $siteinfo = Site::getSiteInfo();
        $baidu = $siteinfo['pingbaiduurl'];
        $urls = Cache::get($this->urlskey);
        if ($baidu && $urls) {
            $this->pingBaidu($baidu, $urls);
        }
    }


    /**
     * 网址ping
     * @access public
     */
    public function urlsCache($urls)
    {
        $allurls = Cache::get($this->urlskey);
        if (!$allurls) {
            $allurls = $urls;
        } else {
            $allurls = array_merge($allurls, $urls);
        }
        Cache::set($this->urlskey, $allurls);
    }


    /**
     * ping百度程序
     * @param $data
     */
    private function pingBaidu($api, $urls)
    {
//        print_r($api);
//        print_r($urls);
//        exit;
//        $urls = array(
//            'http://www.example.com/1.html',
//            'http://www.example.com/2.html',
//        );
//        $api = 'http://data.zz.baidu.com/urls?site=jinseyulin.cc&token=lS8Clzeqh2U9hPpa';
        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $api,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => implode("\n", $urls),
            CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        file_put_contents('pingsearchengine.txt', $result);
    }
}