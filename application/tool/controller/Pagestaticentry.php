<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\Cache;
use think\Db;

/**
 * 页面静态化 入口文件
 * 该文件接收请求重新生成页面
 */
class Pagestaticentry extends Common
{

    /**
     * 本地测试开启下 允许跨域ajax 获取数据
     */
    public function __construct()
    {
        Cache::clear();
        parent::__construct();
    }


    /**
     * 全部的页面静态化操作
     * @access public
     * @todo 修改
     */
    public function allstatic()
    {
        // 详情页面生成
        (new Detailstatic())->setStaticCount();
        // 详情类性的页面的静态化
        exit(json_encode(['status' => 'success', 'msg' => '静态化生成完成。']));
    }

    /**
     * 重新从第一条开始重新生成
     * 全部的页面静态化操作
     * @access public
     * @todo 修改
     */
    public function resetall()
    {
        //全部的页面的静态化
        //重置下站点的已经同步到的地方
        Db::name('ArticleSyncCount')->where(['site_id' => $this->site_id, 'node_id' => $this->node_id])->update(['count' => 0]);
        (new Detailstatic())->setStaticCount();
        exit(json_encode(['status' => 'success', 'msg' => '静态化生成完成。']));
    }

}
