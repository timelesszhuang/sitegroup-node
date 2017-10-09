<?php
// +----------------------------------------------------------------------
// | Description: 基础类，无需验证权限。
// +----------------------------------------------------------------------
// | Author: timelesszhuang <834916321@qq.com>
// +----------------------------------------------------------------------

namespace app\common\controller;


use think\Controller;
use think\Db;
use app\tool\controller\FileExistsTraits;

class Common extends Controller
{
    use FileExistsTraits;
    public $separator = '||||||||||||||||||||||||';

    /**
     * 获取公共的数据
     * @access public
     */
    public function __construct()
    {
        session_write_close();
//        $this->getSiteId();
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
        exit(['status' => '20', 'msg' => '请求异常']);
    }


    /**
     * 验证下node_id
     * @access public
     */
    public function check_siteid()
    {
        $site_id = Request::instance()->param('site_id');
        if ($site_id != Config::get('site.SITE_ID')) {
            //发送过来的请求站点的id 是不是跟 配置文件中 一致
            exit(['status' => '20', 'msg' => '请求异常，节点id不一致，忽略您的请求']);
        }
        return $site_id;
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
//      $dest = 'upload/activity/activity/';
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


    /**
     * 把返回的数据集转换成Tree  本函数使用引用传递  修改  数组的索引架构
     *  可能比较难理解     函数中   $reffer    $list[]  $parent 等的信息实际上只是内存中地址的引用
     * @access public
     * @param array $list 要转换的数据集
     * @param string $pid parent标记字段
     * @param string $level level标记字段
     * @return array
     */
    function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0)
    {
        // 创建Tree
        $tree = array();
        if (is_array($list)) {
            //创建基于主键的数组引用
            $refer = array();
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] = &$list[$key];
            }
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = $data[$pid];
                if ($root == $parentId) {
                    //根节点元素
                    $tree[] = &$list[$key];
                } else {
                    if (isset($refer[$parentId])) {
                        //当前正在遍历的父亲节点的数据
                        $parent = &$refer[$parentId];
                        //把当前正在遍历的数据赋值给父亲类的  children
                        $parent[$child][] = &$list[$key];
                    }
                }
            }
        }
        return $tree;
    }


    /**
     * 返回对象  默认不填为success 否则是failed
     * @param $array 响应数据
     * @return array
     * @return array
     * @author guozhen
     */
    public function resultArray($msg = 0, $stat = '', $data = 0)
    {
        if (empty($stat)) {
            $status = "success";
        } else {
            $status = "failed";
        }
        return [
            'status' => $status,
            'data' => $data,
            'msg' => $msg
        ];
    }


    /**
     * 获取站点id
     */
    public function getSiteId()
    {
        $directory=dirname(THINK_PATH)."/application/extra/site.php";
        if(file_exists($directory)){
            return;
        }
        $site=$_SERVER['SERVER_NAME'];
        if(empty($site)){
            (new \app\tool\model\SiteErrorInfo)->addError([
                'msg' => "站点获取域名失败!!",
                'operator' => '获取当前站点域名',
                'site_id' => 0,
                'site_name' => '未获取到',
                'node_id' => 0,
            ]);
            exit(0);
        }
        $domain="http://$site";
        $https_domain="https://$site";
        $map['url'] =  array(['=',$domain],['=',$https_domain],'or');
        $resoure=\app\tool\model\Site::where($map)->find();
        if(is_null($resoure)){
            (new \app\tool\model\SiteErrorInfo)->addError([
                'msg' => "站点获取site_id失败!!",
                'operator' => '获取当前站点site_id',
                'site_id' => 0,
                'site_name' => '未获取到',
                'node_id' => 0,
            ]);
            exit(0);
        }
        $html=<<<ENF
<?php


return [
    //节点的id 信息部署的时候需要修改该id 的值
    'SITE_ID' => {$resoure->id},
    //缓存十分钟
    'CACHE_TIME' => 600,
    //缓存的文件的列表
    'CACHE_LIST' => [
        //
        'SITEINFO' => 'siteinfo',
        //菜单
        'MENU' => 'menu',
        //关键词
        'KEYWORD' => 'keyword',
        //该配置主要用于 获取菜单选择的 文章或者 零散文章 问答文章的分类
        'MENUTYPEID' => 'menutypeid',
        //手机站点 网址  还有跳转链接
        'MOBILE_SITE_INFO' => 'mobile_site_info',
        //
    ],
];
ENF;

        file_put_contents($directory,$html);
    }

}
