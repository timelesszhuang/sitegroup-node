<?php
/**
 * Created by PhpStorm.
 * User: qiangbi
 * Date: 17-6-14
 * Time: 上午11:20
 */

namespace app\tool\controller;

use app\common\controller\Common;

class SiteMap extends Common
{
    /**
     * 用于生成站点的sitemap
     * @access public
     */
    public function index()
    {
        $siteinfo = Site::getSiteInfo();
        $host = $this->siteurl;
        //首先获取全部链接的路径 从menu 中
        $menu = Menu::getMergedMenu($siteinfo['menu'], $this->site_id, $this->site_name, $this->node_id);
        //然后获取相关的文章链接 产品链接 问答链接
        $sync_info = Commontool::getDbArticleListId($this->site_id);
        //获取最新的200篇文章 各个分类 生成sitemap
        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($siteinfo['menu']);
        list($article_list, $article_more) = Commontool::getArticleList($sync_info, $typeid_arr, 200);
        list($question_list, $question_more) = Commontool::getQuestionList($sync_info, $typeid_arr, 200);
        list($product_list, $product_more) = Commontool::getProductList($sync_info, $typeid_arr, 200);
        $sitemap = [];
        foreach ($menu as $v) {
            $sitemap[] = [
                'loc' => $host . $v['href'],
                'lastmod' => date('Y-m-d', time()),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ];
        }
        foreach ($article_list as $v) {
            $sitemap[] = [
                'loc' => $host . $v['href'],
                'lastmod' => date('Y-m-d', time()),
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];
        }
        foreach ($product_list as $v) {
            $sitemap[] = [
                'loc' => $host . $v['href'],
                'lastmod' => date('Y-m-d', time()),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ];
        }
        foreach ($question_list as $v) {
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
        file_put_contents('sitemap.xml', $xmldata);
    }

}