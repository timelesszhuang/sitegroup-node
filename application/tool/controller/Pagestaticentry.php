<?php

namespace app\tool\controller;

use app\common\controller\Common;


/**
 * 页面静态化 入口文件
 * 该文件接收请求重新生成页面
 */
class Pagestaticentry extends Common
{


    /**
     * 全部的页面静态化操作
     * @access public
     */
    public function allstatic()
    {
        //全部的页面的静态化
        (new Detailstatic())->index();
        (new Envpagestatic())->index();
        (new Detailmenupagestatic())->index();
        (new Indexstatic())->index();
        exit(['status' => 'success', 'msg' => '首页静态化生成完成。']);
    }


    /**
     * 首页生成相关操作
     * @access public
     */
    public function indexstatic()
    {
        // 首先首页更新
        if ((new Indexstatic())->index()) {
            exit(['status' => 'success', 'msg' => '首页静态化生成完成。']);
        }
        exit(['status' => 'failed', 'msg' => '首页静态化生成失败。']);
    }


    /**
     * 文章静态化
     * @access public
     */
    public function articlestatic()
    {
        //文章页面的静态化
        (new Detailstatic())->index();
        exit(['status' => 'success', 'msg' => '文章页面生成完成。']);
    }


    /**
     * 菜单 静态化入口
     * @access public
     */
    public function menustatic()
    {
        //菜单详情页面 静态化 配置页面静态化
        if ((new Detailmenupagestatic())->index() && (new Envpagestatic())->index()) {
            exit(['status' => 'success', 'msg' => '栏目页静态化生成完成。']);
        }
        exit(['status' => 'failed', 'msg' => '栏目页静态化生成完成。']);
    }


}
