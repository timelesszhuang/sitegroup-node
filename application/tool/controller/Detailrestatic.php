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
     * @return bool
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function articlestatic($id)
    {
        //判断目录是否存在
        if (!file_exists('article')) {
            $this->make_error("article");
            return false;
        }
        $file_name = sprintf($this->articlepath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        list($template, $data) = (new Detailstatic())->article_detailinfo();
        $content = Common::Debug((new View())->fetch($template,
            $data
        ), $data);
        $article_path = sprintf($this->articlepath, $id);
        if (file_put_contents($article_path, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $article_path]);
        }
    }

    /**
     * 问题重新生成
     * @access private
     */
    private function questionstatic($id)
    {
        if (!file_exists('question')) {
            $this->make_error("question");
            return false;
        }
        $file_name = sprintf($this->questionpath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        list($data, $template) = (new Detailstatic())->question_detailinfo($id);
        $content = Common::Debug((new View())->fetch($template,
            $data
        ), $data);
        $questionpath = sprintf($this->questionpath, $id);
        if (file_put_contents($questionpath, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $questionpath]);
        }
    }


    /**
     * 文章静态化
     * @access private
     * @param $id  article的id
     * @param $type_id  类型的id
     * @return bool|int
     */
    private function productstatic($id)
    {
        //判断目录是否存在
        if (!file_exists('product')) {
            $this->make_error("product");
            return false;
        }
        $file_name = sprintf($this->productpath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        list($template, $data) = (new Detailstatic())->product_detailinfo($id);
        $content = Common::Debug((new View())->fetch($template,
            $data
        ), $data);
        $product_path = sprintf($this->productpath, $id);
        if (file_put_contents($product_path, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $product_path]);
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
    public function exec_refilestatic($id, $searachType)
    {
        // 根据类型判断
        switch ($searachType) {
            // 文章
            case "article":
                $this->articlestatic($id);
                break;
            // 问答
            case "question":
                $this->questionstatic($id);
                break;
            // 产品
            case "product":
                $this->productstatic($id);
                break;
        }
        $this->pingEngine();
    }

}