<?php

namespace app\index\controller;

use app\common\controller\Common;
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
        $keyword = Request::instance()->param('keyword');
        $page = Request::instance()->param('page');
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
     */
    public function articleIndex($type, $page,$keyword)
    {

    }

    /**
     * 产品列表
     */
    public function productIndex($type, $page,$keyword)
    {

    }

    /**
     * 问答列表
     */
    public function questionIndex($type, $page,$keyword)
    {

    }

}
