<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\index\model\Article;
use app\index\model\Product;
use app\index\model\Question;
use app\tool\controller\Commontool;
use think\Request;
use think\View;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class Query extends Common
{

    private $goback = <<<demo
                    <script language="JavaScript">
                    function goback()
                    {
                        history.go(-1)
                    }
                    setTimeout('goback()',1500); //指定1秒刷新一次
                    </script>
demo;


    /**
     * @param $id 分页等相关信息
     * @param $type 查询的类型
     * @param $keyword
     */
    public function index()
    {
        if (!Request::instance()->isGet()) {
            exit('请求异常' . $this->goback);
        }
        //大部分的变量需要过来下内容
        $type = Request::instance()->param('type');
        //需要防止数据库相关注入等操作 没有设置为其他相关
        $keyword = Request::instance()->param('q', '', 'htmlspecialchars,strip_tags,addslashes');
        if (!$keyword) {
            //如果查询为空的话怎么处理
            exit('请填写关键词' . $this->goback);
        }
        $page = Request::instance()->param('p');
        switch ($type) {
            case 'article':
                $this->articleIndex($type, $page, $keyword);
                break;
            case 'product':
                $this->productIndex($type, $page, $keyword);
                break;
            case 'question':
                $this->questionIndex($type, $page, $keyword);
                break;
            case 'all':
                exit('暂时不支持' . $this->goback);
                $this->allIndex($type, $page, $keyword);
                break;
        }
    }

    /**
     * 文章列表
     * @access public
     */
    public function articleIndex($type, $page, $keyword)
    {
        $siteinfo = \app\tool\controller\Site::getSiteInfo();
        //该网站已经同步到的id
        $sync_info = Commontool::getDbArticleListId($siteinfo['id']);
        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($siteinfo['menu']);
        //获取当前的 typeid_arr
        $articlemax_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $typeidarr = [];
        if ($article_typearr) {
            $typeidarr = array_column($article_typearr, 'type_id');
        }
        if (!$typeidarr) {
            //没有分类的情况下
            $article = [];
        } else {
            $where = [
                'id' => ['elt', $articlemax_id],
                'articletype_id' => ['in', $typeidarr],
                'title|content' => ['like', "%$keyword%"],
            ];
            //多少条记录
            $article = Article::order('id', "desc")->field(Commontool::$articleListField)->where($where)
                ->paginate(10, false, [
                    'path' => url('/query', '', '') . "?type={$type}&q={$keyword}&p=[PAGE]",
                    'page' => $page
                ]);
        }
        //获取查询页面必须的元素
        $assign_data = Commontool::getEssentialElement('query', $keyword . '查询', $keyword . '查询', $keyword . '查询');
        Commontool::formatArticleList($article, $article_typearr);
        $assign_data['list'] = $article;
        $template = $this->articlesearchlist;
        //判断模板是否存在
        if (!$this->fileExists($template, '查询模板页不存在')) {
            exit('查询模板页不存在' . $this->goback);
        }
        exit((new View())->fetch($template,
            [
                'd' => $assign_data
            ]
        ));
    }

    /**
     * 产品列表
     * @access public
     */
    public function productIndex($type, $page, $keyword)
    {
        $siteinfo = \app\tool\controller\Site::getSiteInfo();
        //该网站已经同步到的id
        $sync_info = Commontool::getDbArticleListId($siteinfo['id']);
        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($siteinfo['menu']);
        //获取当前的 typeid_arr
        $productmax_id = array_key_exists('article', $sync_info) ? $sync_info['product'] : 0;
        $product_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['product'] : [];
        $typeidarr = [];
        if ($product_typearr) {
            $typeidarr = array_column($product_typearr, 'type_id');
        }
        if (!$typeidarr) {
            //没有分类的情况下
            $product = [];
        } else {
            $where = [
                'id' => ['elt', $productmax_id],
                'type_id' => ['in', $typeidarr],
                'title|summary|detail' => ['like', "%$keyword%"],
            ];
            //多少条记录
            $product = Product::order('id', "desc")->field(Commontool::$productListField)->where($where)
                ->paginate(10, false, [
                    'path' => url('/query', '', '') . "?type={$type}&q={$keyword}&p=[PAGE]",
                    'page' => $page
                ]);
        }
        //获取查询页面必须的元素
        $assign_data = Commontool::getEssentialElement('query', $keyword . '查询', $keyword . '查询', $keyword . '查询');
        Commontool::formatProductList($product, $product_typearr);
        $assign_data['list'] = $product;
        $template = $this->productsearchlist;
        //判断模板是否存在
        if (!$this->fileExists($template, '查询模板页不存在')) {
            exit('查询模板页不存在 ' . $this->goback);
        }
        exit((new View())->fetch($template,
            [
                'd' => $assign_data
            ]
        ));
    }

    /**
     * 问答列表
     */
    public function questionIndex($type, $page, $keyword)
    {
        $siteinfo = \app\tool\controller\Site::getSiteInfo();
        //该网站已经同步到的id
        $sync_info = Commontool::getDbArticleListId($siteinfo['id']);
        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($siteinfo['menu']);
        //获取当前的 typeid_arr
        $questionmax_id = array_key_exists('article', $sync_info) ? $sync_info['product'] : 0;
        $question_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['product'] : [];
        $typeidarr = [];
        if ($question_typearr) {
            $typeidarr = array_column($question_typearr, 'type_id');
        }
        if (!$typeidarr) {
            //没有分类的情况下
            $question = [];
        } else {
            $where = [
                'id' => ['elt', $questionmax_id],
                'type_id' => ['in', $typeidarr],
                'question|content_paragraph' => ['like', "%$keyword%"],
            ];
            //多少条记录
            $question = Question::order('id', "desc")->field(Commontool::$questionListField)->where($where)
                ->paginate(10, false, [
                    'path' => url('/query', '', '') . "?type={$type}&q={$keyword}&p=[PAGE]",
                    'page' => $page
                ]);
        }
        //获取查询页面必须的元素
        $assign_data = Commontool::getEssentialElement('query', $keyword . '查询', $keyword . '查询', $keyword . '查询');
        Commontool::formatQuestionList($question, $question_typearr);
        $assign_data['list'] = $question;
        $template = $this->questionsearchlist;
        //判断模板是否存在
        if (!$this->fileExists($template, '查询模板页不存在')) {
            exit('查询模板页不存在' . $this->goback);
        }
        exit((new View())->fetch($template,
            [
                'd' => $assign_data
            ]
        ));
    }

    /**
     * 全部查看只会列出制定10条
     * @todo 全局查询实现 暂时有问题 原因是整站查询会涉及到多张表 查询分页有难度
     */
    public function allIndex($type, $page, $keyword)
    {

    }
}
