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

class SiteMap extends Common{
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
        $article_arr=[];
        $question_arr=[];
        $scat_arr=[];
        $siteinfo =Site::getSiteInfo();
        //去掉逗号
        $trimSite=trim($siteinfo["menu"],",");
        if(empty($trimSite)){
            exit("no menu");
        }
        $menus=\app\tool\model\Menu::all($trimSite);
        foreach($this->foreachMenu($menus) as $key=>$item){

        }





        $where=[
            "site_id" => $siteinfo['id'],
            "node_id" => $siteinfo['node_id']
        ];
        foreach($this->article($where) as $article){
            $article_arr[]=$article;
        }
        foreach($this->question($where) as $question){
            $question_arr[]=$question;
        }
        foreach($this->scatteredTitle($where) as $scat){
            $scat_arr[]=$scat;
        }
        $arr=[
            "article"=>$article_arr,
            "question"=>$question_arr,
            "news"=>$scat_arr
        ];
        $content = (new View())->fetch('template/sitemap.html',
            [
                'd' => $arr,
            ]
        );
        $make_web = file_put_contents('sitemap.html', $content);
    }



    /**
     * 遍历article
     * @return \Generator
     */
    public function article($where)
    {
        $article=\app\index\model\Article::where($where)->field("id,title")->select();
        foreach($article as $item){
             yield $this->foreachContent($item);
        }
    }

    /**
     *遍历question
     * @return \Generator
     */
    public function question($where)
    {
        $question=Question::where($where)->field("id,question as title")->select();
        foreach($question as $item){
             yield $this->foreachContent($item);
        }

    }

    /**
     * 遍历零散段落
     * @return \Generator
     */
    public function scatteredTitle($where)
    {
        $scat=ScatteredTitle::where($where)->field("id,title")->select();
        foreach($scat as $item){
            yield $this->foreachContent($item);
        }
    }

    /**
     * 返回article数组
     * @param $article
     * @return array
     */
    public function foreachContent($content)
    {
        return [
            "id"=>$content->id,
            "title"=>$content->title
        ];
    }

}