<?php

namespace app\index\controller;

use app\common\controller\EntryCommon;
use app\tool\controller\Site;


/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class Detailenter extends EntryCommon
{


    /**
     * 首页入口
     */
    public function index()
    {
        $filename = 'index.html';
        $siteinfo = Site::getSiteInfo();
        $this->entryCommon();
        exit(file_get_contents($filename));
    }


    /**
     * @param $id
     * 判断文章页面是否存在
     */
    public function article($id)
    {
        $filename = sprintf('article/%s.html', $id);
        $siteinfo = Site::getSiteInfo();
        $this->entryCommon();
        if (file_exists($filename)) {
            exit(file_get_contents($filename));
        } else {
            //如果不存在的话  跳转到首页
            exit(file_get_contents('index.html'));
        }
    }

    /**
     * @param $id
     * 判断问答页面是否存在
     */
    public function question($id)
    {
        $filename = sprintf('question/%s.html', $id);
        $this->entryCommon();
        if (file_exists($filename)) {
            exit(file_get_contents($filename));
        } else {
            exit(file_get_contents('index.html'));
        }
    }

    /**
     * @param $id
     * 判断产品页面是否存在
     */
    public function product($id)
    {
        $filename = sprintf('product/%s.html', $id);
        $this->entryCommon();
        if (file_exists($filename)) {
            exit(file_get_contents($filename));
        } else {
            exit(file_get_contents('index.html'));
        }
    }

}
