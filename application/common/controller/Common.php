<?php
// +----------------------------------------------------------------------
// | Description: 基础类，无需验证权限。
// +----------------------------------------------------------------------
// | Author: timelesszhuang <834916321@qq.com>
// +----------------------------------------------------------------------

namespace app\common\controller;


use app\tool\controller\Site;
use app\tool\traits\FileExistsTraits;
use app\tool\traits\Osstrait;
use app\tool\traits\Params;
use app\tool\traits\Pingbaidu;
use app\tool\traits\Template;
use think\Config;
use think\Controller;
use think\Cache;
use app\tool\model\SiteWaterImage;
use think\Db;

class Common extends Controller
{
    use FileExistsTraits;
    use Osstrait;
    use Pingbaidu;
    use Params;
    use Template;

    //表示是不是主站
    public $mainsite = true;
    // 默认当前是主站
    // 默认当前的区域为0
    //区域id相关信息
    public $district_id = 0;
    //区域name
    public $district_name = '';
    //二级域名的后缀
    public $suffix = '';
    public $urlskey = 'pingUrls';
    public $separator = '||||||||||||||||||||||||';

    public $childsite_tdkplaceholder = '{{site}}';

    //该网站的url
    public $siteurl = '';
    public $site_id = '';
    public $node_id = '';
    public $site_name = '';
    public $waterString = '';
    public $waterImgUrl = '';
    public $menu_ids = '';
    public $domain = '';
    public $user_id = 0;
    public $com_name = '';
    public $siteinfo = [];

    public $detailmenupath = 'indexmenu/';
    // 首页模板位置
    public $indextemplate = 'template/index.html';

    //文章相关链接
    public $articlepath = 'article/article%s.html';
    public $prearticlepath = '';
    public $articletemplatepath = 'template/article.html';
    public $articlelisttemplate = 'template/articlelist.html';

    public $articlesearchlist = 'template/articlesearch.html';//查询的列表落地页

    //问答相关链接
    public $questionpath = 'question/question%s.html';
    public $prequestionpath = '';
    public $questiontemplatepath = 'template/question.html';
    public $questionlisttemplate = 'template/questionlist.html';

    public $questionsearchlist = 'template/questionsearch.html';//查询的列表落地页

    //产品相关链接
    public $productpath = 'product/product%s.html';
    public $preproductpath = '';
    public $producttemplatepath = 'template/product.html';
    public $productlisttemplate = 'template/productlist.html';

    public $productsearchlist = 'template/productsearch.html';//查询的列表落地页

    //活动相关链接
    public $activitypath = 'activity/activity%s.html';
    public $preactivitypath = '';
    public $activitytemplatepath = 'template/activity.html';

    // 文章内容设置的标签列表页面
    public $articletaglisttemplate = 'template/articletaglist.html';
    public $questiontaglisttemplate = 'template/questiontaglist.html';
    public $producttaglisttemplate = 'template/producttaglist.html';

    public $currenturl = '';

    /**
     * 获取公共的数据
     * @access public
     */
    public function __construct()
    {
        parent::__construct();
        session_write_close();
        set_time_limit(0);
        $this->getSiteId();
        //绝对网址
        $siteinfo = Site::getSiteInfo();
        $this->siteurl = $siteinfo['url'];
        $this->site_id = $siteinfo['id'];
        $this->site_name = $siteinfo['site_name'];
        $this->node_id = $siteinfo['node_id'];
        $this->waterString = $siteinfo['walterString'];
        $this->user_id = $siteinfo['user_id'];
        $this->com_name = $siteinfo['com_name'];
        //主域名相关
        $this->domain = $siteinfo['domain'];
        $this->siteinfo = $siteinfo;
        $this->waterImgUrl = Cache::remember('waterImgUrl', function () use ($siteinfo) {
            $SiteWaterImage_info = (new SiteWaterImage())->where(['id' => $siteinfo['site_water_image_id']])->find();
            if ($SiteWaterImage_info) {
                return $SiteWaterImage_info['oss_water_image_path'];
            }
            return '';
        });
        $this->menu_ids = $siteinfo['menu'];
        //上一页下一页链接
        $this->prearticlepath = '/' . $this->articlepath;
        $this->prequestionpath = '/' . $this->questionpath;
        $this->preproductpath = '/' . $this->productpath;
        $this->currenturl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        ///////////////////////////////////////////////
        //截取下相关域名是 主站 还是子站 以及 获取到的区域的id 跟 name
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
        ///////////////////////////////////////////////
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
     * 网站正在建设中
     * @access public
     */
    public static function Debug($content, $data)
    {
        $debug = Config::get('app_debug');
        if ($debug) {
            $str = json_encode($data);
            $script = "<script>console.log($str)</script>";
            $content .= $script;
        }
        return $content;
    }


    /**
     * 返回对象  默认不填为success 否则是failed
     * @param int $msg
     * @param string $stat
     * @param int $data
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
        $directory = dirname(THINK_PATH) . "/application/extra/site.php";
        if (file_exists($directory)) {
            return;
        }
        $site = $_SERVER['SERVER_NAME'];
        if (empty($site)) {
            (new \app\tool\model\SiteErrorInfo)->addError([
                'msg' => "站点获取域名失败!!",
                'operator' => '获取当前站点域名',
                'site_id' => 0,
                'site_name' => '未获取到',
                'node_id' => 0,
            ]);
            exit(0);
        }
        $domain = "http://$site";
        $https_domain = "https://$site";
        $map['url'] = array(['=', $domain], ['=', $https_domain], 'or');
        $resoure = \app\tool\model\Site::where($map)->find();
        if (is_null($resoure)) {
            (new \app\tool\model\SiteErrorInfo)->addError([
                'msg' => "站点获取site_id失败!!",
                'operator' => '获取当前站点site_id',
                'site_id' => 0,
                'site_name' => '未获取到',
                'node_id' => 0,
            ]);
            exit(0);
        }
        $html = <<<ENF
<?php

return [
    //节点的id 信息部署的时候需要修改该id 的值
    'SITE_ID' => {$resoure->id},
];
ENF;

        file_put_contents($directory, $html);
    }

}
