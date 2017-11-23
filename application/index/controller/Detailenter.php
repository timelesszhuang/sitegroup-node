<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\tool\controller\Site;


/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class Detailenter extends Common
{
    use SpiderComefrom;

    /**
     * @param $id
     * 判断文章页面是否存在
     */
    public function article($id)
    {
        $filename = 'article/' . $id . ".html";
        $this->spidercomefrom(Site::getSiteInfo());
        if (file_exists($filename)) {
            echo file_get_contents($filename);
            exit;
        } else {
            echo file_get_contents('index.html');
        }
    }

    /**
     * @param $id
     * 判断问答页面是否存在
     */
    public function question($id)
    {
        $filename = 'question/' . $id . ".html";
        $this->spidercomefrom(Site::getSiteInfo());
        if (file_exists($filename)) {
            echo file_get_contents($filename);
            exit;
        } else {
            echo file_get_contents('index.html');
        }
    }

    /**
     * @param $id
     * 判断产品页面是否存在
     */
    public function product($id)
    {
        $filename = 'product/' . $id . ".html";
        $this->spidercomefrom(Site::getSiteInfo());
        if (file_exists($filename)) {
            echo file_get_contents($filename);
            exit;
        } else {
            echo file_get_contents('index.html');
        }
    }

}
