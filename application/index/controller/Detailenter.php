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

}
