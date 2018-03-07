<?php
/**
 * 所有需要有总站操作子站的相关代码都需要重新操作
 * User: timeless
 * Date: 18-3-7
 * Time: 下午1:54
 */

namespace app\tool\controller;


use app\common\controller\Common;
use think\Cache;
use think\Db;

class CommonToken extends Common
{
    public function __construct()
    {
        parent::__construct();
        // 检测来源以及是不是有权限操作
        $this->checkOrigin();
        $this->checkToken();
    }

    /**
     * 检查请求来源 如果发送请求 不属于 某域名 则请求不通过
     *　@access public
     */
    private function checkOrigin()
    {
        //数据库中配置的域名 在当前的
        $domain = Db::name('system_config')->where('name', 'SYSTEM_DOMAIN')->field('value')->find();
        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            if (strpos($domain['value'], $_SERVER['HTTP_REFERER'])) {
                return true;
            }
        }
        exit(['status' => 'failed', 'msg' => '请求异常，请求来源异常']);
    }

    /**
     * 验证token 是否合法 添加 token 验证如果验证不通过需要提示有问题
     * @access private
     */
    private function checkToken()
    {
        $token = Request::instance()->get('token');
        $type = Request::instance()->get('type');
        $nowtoken = $this->formatToken($type);
        exit($token . $nowtoken);
        if ($token == $nowtoken) {
            return true;
        }
        exit(['status' => 'failed', 'msg' => '请求异常，token异常']);
    }

    /**
     * 这个地方是双向透明的
     * @access private
     * @param $type node 表示节点请求  site 表示站点请求  调用的时候需要有 site_id node_id 来生成token
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function formatToken($type)
    {
        $data = Db::name('system_config')->where(["name" => 'SYSTEM_CRYPT'])->field('value')->find();
        $crypt = $data['value'];
        // id 为 user 或 site_user 相关
        $user_id = 0;
        if ($type == 'site') {
            $user_id = $this->user_id;
            $saltdata = Cache::remember('site_user_info', function () use ($user_id) {
                return Db::name('site_user')->where(['id' => $user_id])->find();
            });
            $salt = $saltdata['salt'];
            // 读取下 salt数据
            return md5($user_id . $salt . $crypt);
        } else if ($type == 'node') {
            $node_id = $this->node_id;
            // 读取下 salt数据
            $saltdata = Cache::remember('node_user_info', function () use ($node_id) {
                return Db::name('user')->where(['node_id' => $node_id])->find();
            });
            $salt = $saltdata['salt'];
            $user_id = $saltdata['id'];
            return md5($user_id . $salt . $crypt);
        }
    }

}