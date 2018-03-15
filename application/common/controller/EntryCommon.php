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
use think\Cache;
use think\Db;

class EntryCommon extends Common
{
    use SpiderComefrom;
    use Pv;
    use SearchEngineComefrom;


    //入口的位置执行相关请求

    public function __construct()
    {
        parent::__construct();
        //截取下相关的域名
        $host = $_SERVER['HTTP_HOST'];
        $pos = strpos($host, $this->domain);
        $suffix = '';
        if ($pos) {
            $suffix = substr($host, 0, $pos - 1);
        }
        if ($suffix != '' && $suffix != 'www') {
            $this->suffix = $suffix;
            $this->mainsite = false;
            $this->getDistrictInfo($suffix);
        }
    }

    /**
     * 获取区域的信息
     * @access public
     */
    public function getDistrictInfo()
    {
        $suffix = $this->suffix;
        $info = Cache::remember("{$this->suffix}info", function () use ($suffix) {
            return Db::name('district')->where(['pinyin' => $suffix])->find();
        });
        // 相关后缀获取相关bug
        if ($info) {
            // 后缀存储在缓存中
            $this->district_id = $info['id'];
            $this->district_name = $info['name'];
            $this->mainsite = false;
        } else {
            //表示不存在该子站 展现主站的数据
            $this->mainsite = true;
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
