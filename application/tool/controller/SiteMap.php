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
use think\Cache;
use think\View;

class SiteMap extends Common
{
    use FileExistsTraits;

    /**
     * 生成sitemap
     */
    public function index()
    {
        //判断模板是否存在
        if (!$this->fileExists('template/sitemap.html')) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        //去掉逗号
        $trimSite = trim($siteinfo["menu"], ",");
        if (empty($trimSite)) {
            exit("no menu");
        }
        //所有栏目
        $menus = Commontool::getDbArticleListId($trimSite, $siteinfo['id']);
        $arr=[];
        //遍历栏目
        foreach ($this->foreachMenus($menus) as $key => $item) {
            list($title,$data)=$item();
            $arr[$title]=$data;
        }
        $content = (new View())->fetch('template/sitemap.html',
            [
                'd' => $arr,
            ]
        );
        $make_web = file_put_contents('sitemap.html', $content);
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
                $data='';
                $where=[
                    "id"=>["lt",$item["max_id"]]
                ];
                switch ($key) {
//                    问答
                    case "question":
                        $where["type_id"]=$item["type_id"];
                        $data = Question::where($where)->field("id,question as title")->select();
                        if($data){
                            $data=collection($data)->toArray();
                        }
                        break;
//                        文章
                    case "article":
                        $where["articletype_id"]=$item["type_id"];
                        $data = \app\index\model\Article::where($where)->field("id,title")->select();
                        if($data){
                            $data=collection($data)->toArray();
                        }
                        break;
//                        零散段落
                    case "scatteredarticle":
                        $where["articletype_id"]=$item["type_id"];
                        $data = ScatteredTitle::where($where)->field("id,title")->select();
                        if($data){
                            $data=collection($data)->toArray();
                        }
                        break;
                }
                return [$key,$data];
            };
        }
    }

}