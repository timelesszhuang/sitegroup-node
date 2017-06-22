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
        foreach($this->article() as $article){
            $article_arr[]=$article;
        }
        foreach($this->question() as $question){
            $question_arr[]=$question;
        }
        foreach($this->scatteredTitle() as $scat){
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
    public function article()
    {
        $article=\app\index\model\Article::where(1)->field("id,title")->select();
        foreach($article as $item){
             yield $this->foreachContent($item);
        }
    }

    /**
     *遍历question
     * @return \Generator
     */
    public function question()
    {
        $question=Question::where(1)->field("id,question as title")->select();
        foreach($question as $item){
             yield $this->foreachContent($item);
        }

    }

    /**
     * 遍历零散段落
     * @return \Generator
     */
    public function scatteredTitle()
    {
        $scat=ScatteredTitle::where(1)->field("id,title")->select();
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