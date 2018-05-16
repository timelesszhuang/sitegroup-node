<?php
/**
 * Created by PhpStorm.
 * User: qiangbi
 * Date: 17-6-14
 * Time: 上午11:20
 */

namespace app\tool\controller;

use app\common\controller\Common;
use think\Cache;

class SiteMap extends Common
{

    //公共操作对象
    public $commontool;

    public function __construct()
    {
        parent::__construct();
        $this->commontool = new Commontool();
        $this->commontool->tag = '';
    }


    /**
     * 用于生成站点的sitemap
     * @access public
     */
    public function index()
    {
        header("Content-type: text/xml");
        $host = $this->realsiteurl;
        $xmldata = Cache::remember($host . 'sitemap', function () use ($host) {
            //首先获取全部链接的路径 从menu 中
            $menu = (new Menu)->getMergedMenu();
            //获取最新的200篇文章 各个分类 生成sitemap
            list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
            $article_list = $this->commontool->getArticleList($typeid_arr, 200);
            $question_list = $this->commontool->getQuestionList($typeid_arr, 200);
            $product_list = $this->commontool->getProductList($typeid_arr, 200);
            $sitemap = [];
            foreach ($menu as $v) {
                $sitemap[] = [
                    'loc' => $host . $v['href'],
                    'lastmod' => date('Y-m-d', time()),
                    'changefreq' => 'daily',
                    'priority' => '0.9',
                ];
            }
            foreach ($article_list['list'] as $v) {
                $sitemap[] = [
                    'loc' => $host . $v['href'],
                    'lastmod' => date('Y-m-d', time()),
                    'changefreq' => 'daily',
                    'priority' => '0.7',
                ];
            }
            foreach ($product_list['list'] as $v) {
                $sitemap[] = [
                    'loc' => $host . $v['href'],
                    'lastmod' => date('Y-m-d', time()),
                    'changefreq' => 'daily',
                    'priority' => '0.9',
                ];
            }
            foreach ($question_list['list'] as $v) {
                $sitemap[] = [
                    'loc' => $host . $v['href'],
                    'lastmod' => date('Y-m-d', time()),
                    'changefreq' => 'daily',
                    'priority' => '0.6',
                ];
            }
            $xml_wrapper = <<<XML
<?xml version='1.0' encoding='utf-8'?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
</urlset>
XML;
            $xml = new \SimpleXMLElement($xml_wrapper);
            foreach ($sitemap as $data) {
                $item = $xml->addChild('url'); //使用addChild添加节点
                if (is_array($data)) {
                    foreach ($data as $key => $row) {
                        $item->addChild($key, $row);
                    }
                }
            }
            $xmldata = $xml->asXML(); //用asXML方法输出xml，默认只构造不输出。
            return $xmldata;
        });
        exit($xmldata);
    }

}