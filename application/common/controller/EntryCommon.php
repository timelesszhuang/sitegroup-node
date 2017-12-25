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
use think\Config;

class EntryCommon extends Common
{
    use SpiderComefrom;
    use Pv;
    use SearchEngineComefrom;

    /**
     * 浏览页面之后的公共操作
     */
    public function entryCommon()
    {
        $siteinfo = Site::getSiteInfo();
        $uri = $_SERVER['REQUEST_URI'];
        $absolutepath = $siteinfo['url'] . $uri;
        $this->spidercomefrom($siteinfo, $absolutepath);
        $this->pv($siteinfo, $absolutepath);
        //获取请求的useragent
        $this->pagecomefrom($siteinfo, $absolutepath);
    }
}
