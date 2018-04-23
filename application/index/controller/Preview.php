<?php
/**
 * 页面预览功能
 * User: timeless
 * Date: 17-10-30
 * Time: 上午10:34
 */

namespace app\index\controller;


use app\common\controller\Common;
use app\common\controller\EntryCommon;
use app\index\model\Product;
use app\tool\controller\Detailstatic;
use think\View;

class Preview extends EntryCommon
{


    /**
     * 文章静态化
     * @access private
     * @param $id  article的id
     * @return bool|void
     * @throws \think\Exception
     * @throws \Exception
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
        list($template, $data) = (new Detailstatic())->article_detailinfo($id);
        $content = $this->Debug((new View())->fetch($template,
            $data
        ), $data);
        exit($content);
    }


    /**
     * 问题重新生成
     * @access private
     * @param $id product
     * @return bool
     * @throws \Exception
     * @throws \think\Exception
     */
    private function questionpreview($id)
    {
        if (!file_exists('question')) {
            $this->make_error("question");
            return false;
        }
        $file_name = sprintf($this->questionpath, $id);
        if ($this->checkhtmlexists($file_name)) {
            //文件存在直接展现出来不需要重新请求生成
            return;
        }
        list($template, $data) = (new Detailstatic())->question_detailinfo($id);
        $content = $this->Debug((new View())->fetch($template,
            $data
        ), $data);
        exit($content);
    }


    /**
     * 文章静态化
     * @access private
     * @param $id  产品的id
     * @return bool|void
     * @throws \Exception
     */
    private function productpreview($id)
    {
        //判断目录是否存在
        if (!file_exists('product')) {
            $this->make_error("product");
            return false;
        }
        $file_name = sprintf($this->productpath, $id);
        if ($this->checkhtmlexists($file_name)) {
            //文件存在直接展现出来不需要重新请求生成
            return;
        }
        list($template, $data) = (new Detailstatic())->product_detailinfo($id);

        $content = $this->Debug((new View())->fetch($template,
            $data
        ), $data);
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
     * @param int $id
     * @param string $type
     * @return void
     * @throws \Exception
     * @throws \think\Exception
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