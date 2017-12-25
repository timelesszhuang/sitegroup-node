<?php
/**
 * 搜索引擎来源统计
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-8-28
 * Time: 下午2:05
 */

namespace app\index\traits;

use app\index\model\AccessKeyword;
use app\index\model\BrowseRecord;

trait SearchEngineComefrom
{

    public function pagecomefrom($siteinfo, $page)
    {
        $obj = [];
        //好搜 搜索引擎代码
//      $referer = 'http://www.so.com/link?m=aIjHi90jlllh509tAJvrBiVNF9dVNvhGeQsHl9nNmX4oN6Qt83eNcF1rWAjgf%2BlZ5drDgPkja4SKOlA7u2Z1HbDiSXmbIqlRW';
//      $referer='https://www.so.com/s?q=%E7%BD%91%E6%98%93%E4%BC%81%E4%B8%9A%E9%82%AE%E7%AE%B1+4006360163.com&src=res-sug-local&fr=none&psid=2eb56110281dfd757f71132da2f20ae4';
        //谷歌测试代码
//        $referer = "https://www.baidu.com/link?url=sIm0XICud_40rlDVsP1a1GPPRt9Y_wTTpBKORwPgh9Jfij44vQjUWMFn5N_0hd8kE7P09jM9II0Rp7ByxcEkPa&wd=&eqid=ee648f38000103dc0000000259a3e28d";
//      $refer=https://www.google.com.hk/?gws_rd=cr,ssl#newwindow=1&safe=strict&q=4006360163‘
        //搜狗测试代码
//      https://www.sogou.com/link?url=DSOYnZeCC_qreBQraQMXQyWqk8w48k9CG_H1iKm2_78.&amp;query=4006360163+%E7%BD%91%E6%98%93%E4%BC%81%E4%B8%9A%E9%82%AE%E7%AE%B1
//      https://www.sogou.com/web?query=4006360163+%E7%BD%91%E6%98%93%E4%BC%81%E4%B8%9A%E9%82%AE%E7%AE%B1&_asf=www.sogou.com&_ast=&w=01015002&p=40040108&ie=utf8&from=index-nologin&s_from=index&oq=&ri=0&sourceid=sugg&suguuid=&sut=0&sst0=1499652984891&lkt=0%2C0%2C0&sugsuv=1497665587331141&sugtime=1499652984891
        if (!array_key_exists('HTTP_REFERER', $_SERVER)) {
            return;
        }
        $referer = $_SERVER['HTTP_REFERER'];
        //当前页面
        $origin_web = $page;
        $eqid = "";
        if (stripos($referer, 'sogou.com') or stripos($referer, 'sogo.com')) {
            $arr = explode('&', $referer);
            foreach ($arr as $k => $v) {
                $refererdata = explode('=', $v);
                $obj = [
                    $refererdata[0] => $refererdata[1]
                ];
            }
            if (array_key_exists("query", $obj)) {
                $keyword = urldecode($obj['query']);
            } else {
                $keyword = "搜狗关键词";
            }
            $engine = "sogou";
        } else if (stripos($referer, 'so.com')) {
            echo $referer;
            $arr = explode('&', $referer);
            foreach ($arr as $k => $v) {
                $refererdata = explode('=', $v);
                $obj = [
                    $refererdata[0] => $refererdata[1]
                ];
            }
            if (array_key_exists("q", $obj)) {
                $keyword = urldecode($obj['q']);
            } else {
                $keyword = "好搜关键词";
            }
            $engine = "haosou";
        } else if (stripos($referer, 'google.com')) {
            if (array_key_exists("q", $obj)) {
                $keyword = urldecode($obj['q']);
            } else {
                $keyword = "谷歌关键词";
            }
            $engine = "google";
        } else if (stripos($referer, 'baidu.com')) {
            parse_str($referer, $parr);
            if (isset($parr['eqid'])) {
                $eqid = $parr['eqid'];
            }
            $keyword = "百度关键词";
            $engine = "baidu";
        } else if (stripos($referer, 'bing.com')) {
            if (array_key_exists("q", $obj)) {
                $keyword = urldecode($obj['q']);
            } else {
                $keyword = "必应关键词";
            }
            $engine = "bing";
        } else if (stripos($referer, 'yahoo.com')) {
            if (array_key_exists("p", $obj)) {
                $keyword = urldecode($obj['p']);
            } else {
                $keyword = "雅虎关键词";
            }
            $engine = "yahoo";
        } else {
            $keyword = "其他关键词";
            $engine = "other";
        }
        $data = [
            'keyword' => $keyword,
            'referrer' => $referer,
            'engine' => $engine,
            'origin_web' => $origin_web,
            'node_id' => $siteinfo['node_id'],
            'site_id' => $siteinfo['id'],
            //专指百度的特殊字符
            'eqid' => $eqid,
        ];
        $browse = new BrowseRecord($data);
        $browse->allowField(true)->save();
        if (!empty($keyword)) {
            $where = [
                "keyword" => $keyword,
                "site_id" => $data['site_id'],
                "node_id" => $data['node_id']
            ];
            $access = AccessKeyword::where($where)->find();
            if ($access) {
                $access->count = ++$access->count;
                $access->save();
                return;
            }
            unset($data['referrer']);
            unset($data['origin_web']);
            unset($data['eqid']);
            unset($data['engine']);
            $access_model = new AccessKeyword($data);
            $access_model->allowField(true)->save();
        }

    }
}