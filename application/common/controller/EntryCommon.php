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
use app\tool\controller\Site;

class EntryCommon extends Common
{
    use SpiderComefrom;
    use Pv;
    use SearchEngineComefrom;

    public $siteinfo;

    //入口的位置执行相关请求

    public function __construct()
    {
        $this->siteinfo = Site::getSiteInfo();
        parent::__construct();
        //截取下相关的域名
        $host = $_SERVER['HTTP_HOST'];
        print_r($host);
    }


    /**
     * 浏览页面之后的公共操作
     */
    public function entryCommon()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $absolutepath = $this->siteinfo['url'] . $uri;
        $this->spidercomefrom($this->siteinfo, $absolutepath);
        $this->pv($this->siteinfo, $absolutepath);
        //获取请求的useragent
        $this->pagecomefrom($this->siteinfo, $absolutepath);
    }
}
