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
use think\Db;

class EntryCommon extends Common
{
    use SpiderComefrom;
    use Pv;
    use SearchEngineComefrom;

    public $siteinfo;
    // 默认当前是主站
    public $mainsite = true;
    // 默认当前的区域为0
    public $district_id = 0;
    public $district_name = '';

    //入口的位置执行相关请求

    public function __construct()
    {
        $this->siteinfo = Site::getSiteInfo();
        $domain = $this->siteinfo['domain'];
        parent::__construct();
        //截取下相关的域名
        $host = $_SERVER['HTTP_HOST'];
        $pos = strpos($host, $domain);
        $suffix = '';
        if ($pos) {
            $suffix = substr($host, 0, $pos - 1);
        }
        if ($suffix != '' && $suffix != 'www') {
            $this->mainsite = false;
        }
        $this->getDistrictInfo($suffix);
        print_r($this->district_name);
        print_r($this->district_id);
    }

    /**
     * 获取区域的信息
     * @access public
     */
    public function getDistrictInfo($suffix)
    {
        // 相关后缀获取相关bug
        $info = Db::name('district')->where(['pinyin' => $suffix])->find();
        if ($info) {
            $this->district_id = $info['id'];
            $this->district_name = $info['name'];
            $this->mainsite = false;
        }
    }


    /**
     * 浏览页面之后的公共操作
     * @access public
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
