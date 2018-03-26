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
     * @return bool
     * @throws \think\Exception
     */
    private function articlestatic($id)
    {
        //判断目录是否存在
        if (!file_exists('article')) {
            $this->make_error("article");
            return false;
        }
        $file_name = sprintf($this->articlepath, $id);
        $articleaccess_path = sprintf($this->articleaccesspath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        list($template, $data) = (new Detailstatic())->article_detailinfo($id);
        $content = Common::Debug((new View())->fetch($template,
            $data
        ), $data);
        if (file_put_contents($file_name, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $articleaccess_path]);
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
        $questionaccesspath = sprintf($this->questionaccesspath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        list($template, $data) = (new Detailstatic())->question_detailinfo($id);
        //需要判断下是不是当前模板存在
        $content = Common::Debug((new View())->fetch($template, $data), $data);
        if (file_put_contents($file_name, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $questionaccesspath]);
        }
    }


    /**
     * 文章静态化
     * @access private
     * @param $id  article的id
     * @return bool|int
     * @throws \think\Exception
     * @throws \think\Exception
     */
    private function productstatic($id)
    {
        //判断目录是否存在
        if (!file_exists('product')) {
            $this->make_error("product");
            return false;
        }
        $file_name = sprintf($this->productpath, $id);
        $productaccess_path = sprintf($this->productaccesspath, $id);
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        list($template, $data) = (new Detailstatic())->product_detailinfo($id);
        $content = Common::Debug((new View())->fetch($template,
            $data
        ), $data);
        if (file_put_contents($file_name, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            $this->urlsCache([$this->siteurl . '/' . $productaccess_path]);
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
     * @return void
     * @throws \think\Exception
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
    }    /**
     * 根据id删除文章
     * @param $id
     * @param $searachType
     * @return void
     * @throws \think\Exception
     */
    public function exec_removestatic($id, $searachType,$type_id)
    {
        // 根据类型判断
        switch ($searachType) {
            // 文章
            case "article":
                $this->articleremove($id,$type_id);
                break;
            // 问答
            case "question":
                $this->questionremove($id,$type_id);
                break;
            // 产品
            case "product":
                $this->productremove($id,$type_id);
                break;
        }
        $this->pingEngine();
    }

    /**
     * 文章删除
     * @access private
     * @param $id  article的id
     * @return bool
     * @throws \think\Exception
     */
    private function articleremove($id,$type_id)
    {
        //判断目录是否存在
        if (!file_exists('article')) {
            $this->make_error("article");
            return false;
        }
        $file_name = sprintf($this->articlepath, $id);
        $where['id'] = ['gt',$id];
        $where['articletype_id'] = $type_id;
        $map['id'] = ['lt',$id];
        $map['articletype_id'] = $type_id;
        $pre_article =  (new Article())->where($where)->find()['id'];
        $next_article =  (new Article())->where($map)->order('id desc')->find()['id'];
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        if(unlink($file_name)){
            if($pre_article){
                $this->exec_refilestatic($pre_article,'article');
            }
            if($next_article){
                $this->exec_refilestatic($next_article,'article');
            }
        }
        }


   /**
     * 问答删除
     * @access private
     * @param $id  article的id
     * @return bool
     * @throws \think\Exception
     */
    private function questionremove($id,$type_id)
    {
        //判断目录是否存在
        if (!file_exists('question')) {
            $this->make_error("question");
            return false;
        }
        $file_name = sprintf($this->questionpath, $id);
        $where['id'] = ['gt',$id];
        $where['type_id'] = $type_id;
        $map['id'] = ['lt',$id];
        $map['type_id'] = $type_id;
        $pre_question =  (new Question())->where($where)->find()['id'];
        $next_question=  (new Question())->where($map)->find()['id'];
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        unlink($file_name);
        if($pre_question){
            $this->exec_refilestatic($pre_question,'question');
        }
        if($next_question){
            $this->exec_refilestatic($next_question,'question');
        }
        }


    /**
     * 产品删除
     * @access private
     * @param $id  article的id
     * @return bool
     * @throws \think\Exception
     */
    private function productremove($id,$type_id)
    {
        //判断目录是否存在
        if (!file_exists('product')) {
            $this->make_error("product");
            return false;
        }
        $file_name = sprintf($this->productpath, $id);
        $where['id'] = ['gt',$id];
        $where['type_id'] = $type_id;
        $map['id'] = ['lt',$id];
        $map['type_id'] = $type_id;
        $pre_product =  (new Product())->where($where)->find()['id'];
        $next_product=  (new Product())->where($map)->find()['id'];
        if (!$this->checkhtmlexists($file_name)) {
            return false;
        }
        unlink($file_name);
        if($pre_product){
            $this->exec_refilestatic($pre_product,'product');
        }
        if($next_product){
            $this->exec_refilestatic($next_product,'product');
        }
    }


}