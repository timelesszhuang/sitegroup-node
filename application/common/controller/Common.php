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
     * 检查请求来源 如果发送请求 不属于 某域名 则请求不通过
     *　@access public
     */
    public function checkOrigin()
    {
        return true;
        //数据库中配置的域名 在当前的
        $domain = Db::name('sg_system_config')->where('name', 'SYSTEM_DOMAIN')->field('value')->find();
        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            if (strpos($domain['value'], $_SERVER['HTTP_REFERER'])) {
                return true;
            }
        }
        exit('请求异常');
    }


    /**
     * 解压缩文件
     * @access public
     * @param $path 源文件的路径  路径需要绝对路径
     * @param $dest 解压缩到的路径 路径需要绝对路径
     * @return bool
     */
    public function unzipFile($path, $dest)
    {
        if (file_exists($path)) {
            //文件不存在
        }
//        $dest = 'upload/activity/activity/';
        $zip = new \ZipArchive;
        $res = $zip->open($path);
        if ($res === TRUE) {
            //解压缩到test文件夹
            $zip->extractTo($dest);
            $zip->close();
            return true;
        } else {
            return false;
        }
    }


}
