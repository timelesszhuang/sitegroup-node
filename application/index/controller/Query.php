<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\index\model\Article;
use app\tool\controller\Commontool;
use think\Request;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class Query extends Common
{
    /**
     * @param $id 分页等相关信息
     * @param $type 查询的类型
     * @param $keyword
     */
    public function index()
    {
        if (!Request::instance()->isGet()) {
            exit('请求异常');
        }
        $type = Request::instance()->param('type');
        $keyword = Request::instance()->param('q');
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
        }
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
        //获取页面需要的

        echo '<pre>';
        print_r($article);
        exit;
    }

    /**
     * 产品列表
     * @access public
     */
    public function productIndex($page, $keyword)
    {
        $siteinfo = Site::getSiteInfo();

    }

    /**
     * 问答列表
     *
     */
    public function questionIndex($page, $keyword)
    {

    }

    /**
     * 全部查看只会列出制定10条
     *
     */
    public function allIndex($page, $keyword)
    {

    }
}
