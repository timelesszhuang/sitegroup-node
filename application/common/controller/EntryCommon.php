<?php

// +----------------------------------------------------------------------
// | Description: 进入页面之后继承的公共元素
// +----------------------------------------------------------------------
// | Author: timelesszhuang <834916321@qq.com>
// +----------------------------------------------------------------------


namespace app\common\controller;


use app\index\traits\Pv;
use app\index\traits\SearchEngineComefrom;
use app\index\traits\SpiderComefrom;

class EntryCommon extends Common
{
    use SpiderComefrom;
    use Pv;
    use SearchEngineComefrom;


    /**
     * 浏览页面之后的公共操作
     * @access public
     */
    public function entryCommon()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $absolutepath = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $uri;
        $this->spidercomefrom($this->siteinfo, $absolutepath);
        $this->pv($this->siteinfo, $absolutepath);
        //获取请求的useragent
        $this->pagecomefrom($this->siteinfo, $absolutepath);
    }


}
