<?php
/**
 * Created by PhpStorm.
 * User: qiangbi
 * Date: 17-6-14
 * Time: 上午11:20
 */

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\model\Question;
use app\index\model\ScatteredTitle;
use think\View;

class SiteMap extends Common
{
    use FileExistsTraits;

    /**
     * 生成sitemap
     */
    public function index()
    {
        $siteinfo = Site::getSiteInfo();
        //去掉逗号
        $trimSite = trim($siteinfo["menu"], ",");
        if (empty($trimSite)) {
            exit("no menu");
        }
        $menu_arr = Menu::getMergedMenu($siteinfo["menu"], $siteinfo["id"], $siteinfo["site_name"], $siteinfo["node_id"]);
        //所有栏目
        $menus = Commontool::getDbArticleListId($trimSite, $siteinfo['id']);
        $arr = [];
        //遍历栏目
        foreach ($this->foreachMenus($menus) as $key => $item) {
            list($title, $data) = $item();
            $arr[$title] = $data;
        }
        $d = $arr;
        $nav = $menu_arr;
        $url = $siteinfo["url"];
        $now = date('Y-m-d');
        $one = '';
        $two='';
        $three='';
        $four='';
        //---------------------------
        if (isset($nav)) {
            foreach ($nav as $item) {
                $one .= <<<ONE
    <url>
        <loc>{$url}{$item["generate_name"]}</loc>
        <lastmod>{$now}</lastmod>
        <changefreq>Always</changefreq>
        <priority>1</priority>
    </url>
ONE;
            }
        }
        //------------------------------------
        if(isset($d['question'])){
            foreach($d['question'] as $item){
            $two.=<<<TWO
        <url>
            <loc>{$url}"/question/question"{$item["id"]}".html"</loc>
            <lastmod>{$item["create_time"]}</lastmod>
            <changefreq>Always</changefreq>
            <priority>0.9</priority>
        </url>
TWO;
            }
        }
        //------------------------------------
        if(isset($d['article'])){
            foreach($d['article'] as $item){
        $three=<<<THREE
        <url>
            <loc>{$url}"/article/article"{$item["id"]}".html"</loc>
            <lastmod>{$item["create_time"]}</lastmod>
            <changefreq>Always</changefreq>
            <priority>0.9</priority>
        </url>
THREE;
            }}

        //----------------------------------------
        if(isset($d['scatteredarticle'])){
             foreach($d['scatteredarticle'] as $item){
        $four=<<<FOUR
                <url>
            <loc>{$url}"/news/news"{$item["id"]}".html"</loc>
            <lastmod>{$item["create_time"]}</lastmod>
            <changefreq>Always</changefreq>
            <priority>0.9</priority>
        </url>
FOUR;
             }}

        $hereDoc='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $hereDoc=$hereDoc.$one.$two.$three.$four."</urlset>";
        $make_web = file_put_contents('sitemap.xml', $hereDoc);
    }

    /**
     * 遍历每一个栏目的内容
     * @param $menus
     * @return \Generator
     */
    public function foreachMenus($menus)
    {
        foreach ($menus as $key => list($item)) {
            yield function () use ($key, $item) {
                $data = '';
                $where = [
                    "id" => ["lt", $item["max_id"]]
                ];
                switch ($key) {
//                    问答
                    case "question":
                        $where["type_id"] = $item["type_id"];
                        $data = Question::where($where)->field("id,create_time")->select();
                        if ($data) {
                            $data = collection($data)->toArray();
                        }
                        break;
//                        文章
                    case "article":
                        $where["articletype_id"] = $item["type_id"];
                        $data = \app\index\model\Article::where($where)->field("id,create_time")->select();
                        if ($data) {
                            $data = collection($data)->toArray();
                        }
                        break;
//                        零散段落
                    case "scatteredarticle":
                        $where["articletype_id"] = $item["type_id"];
                        $data = ScatteredTitle::where($where)->field("id,create_time")->select();
                        if ($data) {
                            $data = collection($data)->toArray();
                        }
                        break;
                }
                return [$key, $data];
            };
        }
    }

}