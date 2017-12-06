<?php
/**
 * 页面重新生成
 * User: timeless
 * Date: 17-10-30
 * Time: 上午10:34
 */

namespace app\tool\controller;


use app\common\controller\Common;
use app\index\model\Product;
use app\index\model\Question;
use app\tool\model\SitePageinfo;
use app\index\model\Article;
use think\View;

class Detailrestatic extends Common
{

    /**
     * 文章静态化
     * @access private
     * @param $id  article的id
     * @param $type_id  类型的id
     */
    private function articlestatic($id, $type_id)
    {
        //判断目录是否存在
        if (!file_exists('article')) {
            $this->make_error("article");
            return false;
        }
        //判断模板是否存在
        if (!$this->fileExists($this->articletemplatepath)) {
            $this->make_error($this->articletemplatepath);
            return false;
        }
        $file_name = sprintf($this->articlepath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
//
//        $siteinfo = Site::getSiteInfo();

        // 获取menu信息
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $this->node_id,
            "type_id" => $type_id
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        // 取出指定id的文章
        $articlesql = "id = $id and node_id=$this->node_id and articletype_id=$type_id";
        $article = Article::where($articlesql)->find()->toArray();
        $pre_article_sql = "id <{$id} and node_id=$this->node_id and articletype_id=$type_id";
        $pre_article = Article::where($pre_article_sql)->field("id,title")->order("id", "desc")->find();
        //上一页链接
        if ($pre_article) {
            $pre_article = $pre_article->toArray();
            $pre_article = ['href' => sprintf($this->prearticlepath, $pre_article['id']), 'title' => $pre_article['title']];
        }
        //获取下一篇 的网址
        //最后一条 不需要有 下一页
        $next_article_sql = "id >{$id} and node_id=$this->node_id and articletype_id=$type_id";
        $next_article = Article::where($next_article_sql)->field("id,title")->find();
        //下一页链接
        if ($next_article) {
            $next_article = $next_article->toArray();
            $next_article['href'] = sprintf($this->prearticlepath, $id);
        }
        $assign_data = (new Detailstatic())->form_perarticle_content($article, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        $content = (new View())->fetch('template/article.html',
            [
                'd' => $assign_data,
                'article' => $article,
                'pre_article' => $pre_article,
                'next_article' => $next_article,
            ]
        );
        $article_path = sprintf($this->articlepath, $id);
        if (file_put_contents($article_path, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $article_path]);
        }
    }


    /**
     * 文章静态化
     * @access private
     * @param $id  article的id
     * @param $type_id  类型的id
     * @return bool|int
     */
    private function productstatic($id, $type_id)
    {
        //判断目录是否存在
        if (!file_exists('product')) {
            $this->make_error("product");
            return false;
        }
        //判断模板是否存在
        if (!$this->fileExists($this->producttemplatepath)) {
            $this->make_error($this->producttemplatepath);
            return false;
        }
        $file_name = sprintf($this->productpath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        // 获取menu信息
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $this->node_id,
            "type_id" => $this->type_id
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        // 取出指定id的文章
        $productsql = "id = $id and node_id=$this->node_id and type_id=$type_id";
        $product = Product::where($productsql)->find()->toArray();
        $content = (new Detailstatic())->form_perproduct($product, $type_id, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        $product_path = sprintf($this->productpath, $id);
        if (file_put_contents($product_path, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $product_path]);
        }
    }

    /**
     * 问题重新生成
     * @access private
     */
    private function questionstatic($id, $type_id)
    {
        if (!file_exists('question')) {
            $this->make_error("question");
            return false;
        }
        //判断模板是否存在
        if (!$this->fileExists($this->questiontemplatepath)) {
            $this->make_error($this->questiontemplatepath);
            return false;
        }
        $file_name = sprintf($this->questionpath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        // 获取menu信息
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $this->node_id,
            "type_id" => $type_id
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        //获取上一篇和下一篇
        $pre_question = Question::where(["id" => ["lt", $id], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->order("id", "desc")->find();
        if ($pre_question) {
            $pre_question['href'] = sprintf($this->prequestionpath, $pre_question['id']);
            //"/question/question{$pre_question['id']}.html"
        }
        //下一篇可能会导致其他问题
        $next_question = Question::where(["id" => ["gt", $id], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->find();
        if ($next_question) {
            $next_question['href'] = "/question/question{$next_question['id']}.html";
        }
        $questionsql = "id = $id and node_id=$this->node_id and type_id=$type_id";
        $question = Question::where($questionsql)->find()->toArray();
        $assign_data = (new Detailstatic())->form_perquestion($question, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        $content = (new View())->fetch('template/question.html',
            [
                'd' => $assign_data,
                'question' => $question,
                'pre_question' => $pre_question,
                'next_question' => $next_question,
            ]
        );
        $questionpath = sprintf($this->questionpath, $id);
        if (file_put_contents($questionpath, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $questionpath]);
        }
    }


    /**
     * htmlexists
     *
     */
    private function checkhtmlexists($htmlfilename)
    {
        //判断文件是否存在
        if (!file_exists($htmlfilename)) {
            return false;
        }
        return true;
    }


    /**
     * 根据id重新生成文章  重新生成页面暂时有问题
     * @param $id
     * @param $searachType
     * @param $type_id
     * @return bool
     */
    public function exec_refilestatic($id, $searachType, $type_id)
    {
        // 根据类型判断
        switch ($searachType) {
            // 文章
            case "article":
                $this->articlestatic($id, $type_id);
                break;
            // 问答
            case "question":
                $this->questionstatic($id, $type_id);
                break;
            // 产品
            case "product":
                $this->productstatic($id, $type_id);
                break;
        }
        $this->pingEngine();
    }

}