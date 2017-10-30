<?php
/**
 * 页面预览功能
 * User: timeless
 * Date: 17-10-30
 * Time: 上午10:34
 */

namespace app\tool\controller;


use app\index\model\Product;
use app\tool\model\SitePageinfo;
use app\tool\traits\FileExistsTraits;
use app\index\model\Article;
use app\index\model\Question;
use app\tool\model\Menu;
use think\View;

class Preview
{
    use FileExistsTraits;

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
        //判断模板是否存在
        if (!$this->fileExists('template/article.html')) {
            $this->make_error("template/article.html");
            return false;
        }
        $generate_html = "article/article";
        $file_name = $generate_html . $id . ".html";
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        // 取出指定id的文章
        $articlesql = "id = $id and node_id=$node_id";
        $article = Article::where($articlesql)->find()->toArray();
        if (empty($article)) {
            exit('您请求的文章不存在');
        }
        $type_id = $article['articletype_id'];
        // 获取menu信息
        $menuInfo = Menu::where([
            "node_id" => $node_id,
            "type_id" => $type_id
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $node_id,
            "site_id" => $site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();

        $pre_article_sql = "id <{$id} and node_id=$node_id and articletype_id=$type_id";
        $pre_article = Article::where($pre_article_sql)->field("id,title")->find();

        //上一页链接
        if ($pre_article) {
            $pre_article = $pre_article->toArray();
            $pre_article['href'] = "/article/article{$pre_article['id']}.html";
        }
        //获取下一篇 的网址
        //最后一条 不需要有 下一页
        $next_article_sql = "id >{$id} and node_id=$node_id and articletype_id=$type_id";
        $next_article = Article::where($next_article_sql)->field("id,title")->find();
        //下一页链接
        if ($next_article) {
            $next_article = $next_article->toArray();
            $next_article['href'] = "/article/article{$next_article['id']}.html";
        }
        $water = $siteinfo['walterString'];
        $assign_data = (new Detailstatic())->form_perarticle_content($article, $node_id, $site_id, $water, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        $content = (new View())->fetch('template/article.html',
            [
                'd' => $assign_data,
                'article' => $article,
                'pre_article' => $pre_article,
                'next_article' => $next_article,
            ]
        );
        echo $content;
//        return $content;
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
        if (!$this->fileExists('template/product.html')) {
            $this->make_error("template/product.html");
            return false;
        }
        $generate_html = "product/product";
        $file_name = $generate_html . $id . ".html";
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        // 取出指定id的产品
        $productsql = "id = $id and node_id=$node_id";
        $product = Product::where($productsql)->find()->toArray();
        $type_id = $product['type_id'];
        // 获取menu信息
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $node_id,
            "type_id" => $type_id
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $node_id,
            "site_id" => $site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        $water = $siteinfo['walterString'];
        $content = (new Detailstatic())->form_perproduct($product, $node_id, $type_id, $water, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        echo $content;
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
        if (!$this->fileExists('template/question.html')) {
            $this->make_error("template/question.html");
            return false;
        }
        $generate_html = "question/question";
        $file_name = $generate_html . $id . ".html";
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        $questionsql = "id = $id and node_id=$node_id";
        $question = Question::where($questionsql)->find()->toArray();
        $type_id = $question['type_id'];
        // 获取menu信息
        $menuInfo = Menu::where([
            "node_id" => $node_id,
            "type_id" => $type_id
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $node_id,
            "site_id" => $site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        //获取上一篇和下一篇
        $pre_question = Question::where(["id" => ["lt", $id], "node_id" => $node_id, "type_id" => $type_id])->field("id,question as title")->find();
        if ($pre_question) {
            $pre_question = $pre_question->toArray();
            $pre_question['href'] = "/question/question{$pre_question['id']}.html";
        }
        //下一篇可能会导致其他问题
        $next_question = Question::where(["id" => ["gt", $id], "node_id" => $node_id, "type_id" => $type_id])->field("id,question as title")->find();
        if ($next_question) {
            $next_question = $next_question->toArray();
            $next_question['href'] = "/question/question{$next_question['id']}.html";
        }
        $water = $siteinfo['walterString'];
        $assign_data = (new Detailstatic())->form_perquestion($question, $water, $sitePageInfo['akeyword_id'], $menuInfo['id'], $menuInfo['name']);
        $content = (new View())->fetch('template/question.html',
            [
                'd' => $assign_data,
                'question' => $question,
                //为了兼容之前的错误
                'pre_article' => $pre_question,
                'next_article' => $next_question,
                //////
                'pre_question' => $pre_question,
                'next_question' => $next_question,
            ]
        );
        echo $content;
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
    public function preview($id = 0, $type = '')
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
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