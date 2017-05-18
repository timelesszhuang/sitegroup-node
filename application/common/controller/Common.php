<?php
// +----------------------------------------------------------------------
// | Description: 基础类，无需验证权限。
// +----------------------------------------------------------------------
// | Author: timelesszhuang <834916321@qq.com>
// +----------------------------------------------------------------------

namespace app\common\controller;


use app\admin\model\SystemConfig;
use app\common\model\User;
use think\Controller;
use think\Db;


class Common extends Controller
{

    /**
     * 获取公共的数据
     * @access public
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 检查请求来源
     *　@access public
     */
    public function checkOrigin()
    {
        $domain_info = Db::name('sg_system_config')->where('name', 'SYSTEM_DOMAIN')->find();
        print_r($domain_info);
    }

}
