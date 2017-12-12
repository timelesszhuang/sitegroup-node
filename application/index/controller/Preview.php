<?php
/**
 * 页面预览功能
 * User: timeless
 * Date: 17-10-30
 * Time: 上午10:34
 */

namespace app\index\controller;


use app\common\controller\EntryCommon;
use app\index\model\Product;
use app\tool\controller\Detailstatic;
use app\tool\model\SitePageinfo;
use app\index\model\Article;
use app\index\model\Question;
use app\tool\model\Menu;
use think\View;

class Preview extends EntryCommon
{
    /**
     * 文章静态化
     * @access private
     * @param $id  article的id
     */
    private function articlepreview($id)
    {
        //判断目录是否存在
        if (!file_exists('article')) {
            $this->make_error("article");
            return false;
        }
        $file_name = sprintf($this->articlepath, $id);
        if ($this->checkhtmlexists($file_name)) {
            //文件存在直接展现出来不需要重新请求生成
            return;
        }
        //判断模板是否存在
        if (!$this->fileExists($this->articletemplatepath)) {
            $this->make_error($this->articletemplatepath);
            return false;
        }
        // 取出指定id的文章
        $articlesql = "id = $id and node_id=$this->node_id";
        $article = Article::where($articlesql)->find();
        if (!$article) {
            exit('文章不存在');
        }
        if (empty($article)) {
            exit('您请求的文章不存在');
        }
        $type_id = $article['articletype_id'];
        // 获取menu信息
        $menuInfo = Menu::where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();

        $pre_article_sql = "id <{$id} and node_id=$this->node_id and articletype_id=$type_id";
        $pre_article = Article::where($pre_article_sql)->field("id,title")->find();

        //上一页链接
        if ($pre_article) {
            $pre_article = $pre_article->toArray();
            $pre_article['href'] = sprintf($this->prearticlepath, $pre_article['id']);
        }
        //获取下一篇 的网址
        //最后一条 不需要有 下一页
        $next_article_sql = "id >{$id} and node_id=$this->node_id and articletype_id=$type_id";
        $next_article = Article::where($next_article_sql)->field("id,title")->find();
        //下一页链接
        if ($next_article) {
            $next_article = $next_article->toArray();
            $next_article['href'] = sprintf($this->prearticlepath, $next_article['id']);
        }
        $assign_data = (new Detailstatic())->form_perarticle_content($article, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        //根据配置来选择模板
        $template = $this->getTemplate('detail', $menuInfo['id'], 'article');
        if (!$this->fileExists($template)) {
            exit('该栏目设置的模板页不存在');
        }
        $content = (new View())->fetch($template,
            [
                'd' => $assign_data,
                'article' => $article,
                'pre_article' => $pre_article,
                'next_article' => $next_article,
            ]
        );
        exit($content);
    }


    /**
     * 文章静态化
     * @access private
     * @param $id  产品的id
     * @param $type_id  类型的id
     */
    private function productpreview($id)
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
        if ($this->checkhtmlexists($file_name)) {
            //文件存在直接展现出来不需要重新请求生成
            return;
        }
        // 取出指定id的产品
        $productsql = "id = $id and node_id=$this->node_id";
        $product = Product::where($productsql)->find();
        if (!$product) {
            exit('该产品不存在');
        }
        $type_id = $product['type_id'];
        // 获取menu信息
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        print_r($menuInfo);
        exit;
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        $content = (new Detailstatic())->form_perproduct($product, $type_id, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        exit($content);
    }

    /**
     * 问题重新生成
     * @access private
     * @param $id product
     * @return bool
     */
    private function questionpreview($id)
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
        if ($this->checkhtmlexists($file_name)) {
            //文件存在直接展现出来不需要重新请求生成
            return;
        }
        $questionsql = "id = $id and node_id=$this->node_id";
        $question = Question::where($questionsql)->find();
        if (!$question) {
            exit('该问答不存在');
        }
        $type_id = $question['type_id'];
        // 获取menu信息
        $menuInfo = Menu::where([
            "node_id" => $this->node_id,
            "type_id" => ['like', "%,$type_id,%"]
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        //获取上一篇和下一篇
        $pre_question = Question::where(["id" => ["lt", $id], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->find();
        if ($pre_question) {
            $pre_question = $pre_question->toArray();
            $pre_question['href'] = sprintf($this->prequestionpath, $pre_question['id']);
            //"/question/question{$pre_question['id']}.html";
        }
        //下一篇可能会导致其他问题
        $next_question = Question::where(["id" => ["gt", $id], "node_id" => $this->node_id, "type_id" => $type_id])->field("id,question as title")->find();
        if ($next_question) {
            $next_question = $next_question->toArray();
            $next_question['href'] = sprintf($this->prequestionpath, $next_question['id']);
            //"/question/question{$next_question['id']}.html";
        }
        $assign_data = (new Detailstatic())->form_perquestion($question, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        //根据配置来选择模板
        $template = $this->getTemplate('detail', $menuInfo['id'], 'question');
        if (!$this->fileExists($template)) {
            exit('该栏目设置的模板页不存在');
        }
        $content = (new View())->fetch($template,
            [
                'd' => $assign_data,
                'question' => $question,
                'pre_question' => $pre_question,
                'next_question' => $next_question,
            ]
        );
        exit($content);
    }


    /**
     * htmlexists
     * 如果文件存在的话直接展现已经生成的页面
     */
    private function checkhtmlexists($htmlfilename)
    {
        //判断文件是否存在
        if (file_exists($htmlfilename)) {
            //直接返回静态页
            $code = file_get_contents($htmlfilename);
            exit($code);
        }
        return false;
    }


    /**
     * 根据id重新生成文章  重新生成页面暂时有问题
     * @param $id
     * @param $searachType
     * @param $type_id
     * @return bool
     */
    public function preview($id = 0, $type = '')
    {
        // 根据类型判断
        switch ($type) {
            // 文章
            case "article":
                $this->articlepreview($id);
                break;
            // 问答
            case "question":
                $this->questionpreview($id);
                break;
            // 产品
            case "product":
                $this->productpreview($id);
                break;
        }
    }

}