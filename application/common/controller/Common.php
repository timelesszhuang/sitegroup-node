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
    //子站点的相关url
    public $realsiteurl = '';
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
    //泛站列表 区域展现列表
    public $districttemplate = 'template/district.html';
    //默认不存在跳转到的地方
    public $defaultdistricttemplate = 'defaulttemplate/district.php';


    // 文章静态化的路径
    public $articlepath = 'article/%s.html';
    // 访问静态文件的路径 为了走路由
    public $articleaccesspath = 'article/article%s.html';
    // 上一页 下一页页面代码
    public $prearticlepath = '';
    public $articletemplatepath = 'template/article.html';
    public $articlelisttemplate = 'template/articlelist.html';

    public $articlesearchlist = 'template/articlesearch.html';//查询的列表落地页

    //问答静态化的路径
    public $questionpath = 'question/%s.html';
    //访问静态文件的路径
    public $questionaccesspath = 'question/question%s.html';
    // 上一页 下一页页面
    public $prequestionpath = '';
    public $questiontemplatepath = 'template/question.html';
    public $questionlisttemplate = 'template/questionlist.html';

    public $questionsearchlist = 'template/questionsearch.html';//查询的列表落地页

    //产品相关链接
    public $productpath = 'product/%s.html';
    //访问静态文件的路径
    public $productaccesspath = 'product/product%s.html';
    // 上一页 下一页页面
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

    public $app_debug = 'product';

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
        $this->app_debug = $siteinfo['app_debug'];
        $this->siteinfo = $siteinfo;
        $this->waterImgUrl = Cache::remember('waterImgUrl', function () use ($siteinfo) {
            $SiteWaterImage_info = (new SiteWaterImage())->where(['id' => $siteinfo['site_water_image_id']])->find();
            if ($SiteWaterImage_info) {
                return $SiteWaterImage_info['oss_water_image_path'];
            }
            return '';
        });
        $this->siteInit();
        $this->menu_ids = $siteinfo['menu'];
        //上一页下一页链接
        $this->prearticlepath = '/' . $this->articleaccesspath;
        $this->prequestionpath = '/' . $this->questionaccesspath;
        $this->preproductpath = '/' . $this->productaccesspath;
        $this->realsiteurl = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'];
        $this->currenturl = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
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
    }

    /**
     * 代码测试打印
     * @param $data
     */
    public function print_pre($data, $exit = false)
    {
        echo '<pre>';
        print_r($data);
        if ($exit) {
            exit;
        }
    }


    /**
     * 站点静态化的时候需要检查 更新的相关数据
     * @access private
     */
    private function siteInit()
    {
        //查看站点logo 是不是有修改
        $this->checkSiteLogo();
        $this->checkSiteIco();
        //验证 图片集的静态化相关功能
        $this->checkImgList();
        //用于验证内容中图片加载状态
        $this->checkGetContent();
    }

    /**
     * 用于验证是不是调用内容中的图片是不是已经静态化了
     * @access public
     */
    public function checkGetContent()
    {
        //oss图片可以根据已经有的来更新 暂时不考虑 建议根据content 的相关name 还有 正则匹配到的索引来匹配数据
    }

    /**
     * 判断站点logo是不是有更新 有更新的话直接重新生成
     * @access private
     */
    private function checkSiteLogo()
    {
        $logo_id = $this->siteinfo['sitelogo_id'];
        if (!$logo_id) {
            return;
        }
        $site_logoinfo = Cache::remember('sitelogoinfo', function () use ($logo_id) {
            return Db::name('site_logo')->where('id', $logo_id)->find();
        });
        //如果logo记录被删除的话怎么操作
        if (!$site_logoinfo) {
            return;
        }
        //如果存在logo 名字就叫 ××.jpg
        $oss_logo_path = $site_logoinfo['oss_logo_path'];
        list($file_ext, $filename) = $this->analyseUrlFileType($oss_logo_path);
        //logo 名称 根据站点id 拼成
        $local_img_name = "logo{$this->site_id}.$file_ext";
        $update_time = $site_logoinfo['update_time'];
        $logo_path = "images/$local_img_name";
        if (file_exists($logo_path) && filectime($logo_path) < $update_time) {
            //logo 存在 且 文件创建时间在更新时间之前
            $this->ossGetObject($oss_logo_path, $logo_path);
        } else if (!file_exists($logo_path)) {
            //logo 存在需要更新
            $this->ossGetObject($oss_logo_path, $logo_path);
        }
    }

    /**
     * 判断站点logo是不是有更新 有更新的话直接重新生成
     * @access private
     */
    private function checkSiteIco()
    {
        $ico_id = $this->siteinfo['siteico_id'];
        if (!$ico_id) {
            return;
        }
        $site_icoinfo = Cache::remember('siteicoinfo', function () use ($ico_id) {
            return Db::name('site_ico')->where('id', $ico_id)->find();
        });
        //如果logo记录被删除的话怎么操作
        if (!$site_icoinfo) {
            return;
        }
        //如果存在logo 名字就叫 ××.jpg
        $oss_ico_path = $site_icoinfo['oss_ico_path'];
        //logo 名称 根据站点id 拼成
        $update_time = $site_icoinfo['update_time'];
        $ico_path = "./favicon.ico";
        if (file_exists($ico_path) && filectime($ico_path) < $update_time) {
            //logo 存在 且 文件创建时间在更新时间之前
            $this->ossGetObject($oss_ico_path, $ico_path);
        } else if (!file_exists($ico_path)) {
            //logo 存在需要更新
            $this->ossGetObject($oss_ico_path, $ico_path);
        }
    }


    /**
     * 检测图片集的图片是不是都已经静态化了  某个节点下的所有图片都会替换掉
     * @access private
     */
    private function checkImgList()
    {
        $endpoint = Config::get('oss.endpoint');
        $bucket = Config::get('oss.bucket');
        $endpointurl = sprintf("https://%s.%s/", $bucket, $endpoint);
        $imglist = cache::remember('imglist', function () {
            return Db::name('imglist')->where('node_id', $this->node_id)->where('status', '10')->select();
        });
        foreach ($imglist as $v) {
            $imgser = $v['imgser'];
            if ($imgser) {
                $imglist_arr = unserialize($imgser);
                foreach ($imglist_arr as $perimg) {
                    $imgname = $perimg['imgname'];
                    $osssrc = $perimg['osssrc'];
                    if (strpos($osssrc, $endpointurl) === false) {
                        //如果路径有问题的话
                        continue;
                    }
                    if ($this->get_osswater_img($osssrc, $imgname, $this->waterString)) {
                        //获取网站水印图片
                        continue;
                    }
                }
            }
        }
    }


    /**
     * 截取article111.html 中的111
     * 截取question111.html 中的 111
     * 截取product111.html 中的 111
     * @access public
     */
    public function subNameId($filename, $type)
    {
        $id = str_replace($type, '', $filename);
        return $id;
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
     * @param string $pk
     * @param string $pid parent标记字段
     * @param string $child
     * @param int $root
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
     * 网站正在建设中 打印出相关站点状态 修改到数据库中
     * @access public
     */
    public function Debug($content, $data)
    {
        if ($this->app_debug == 'dev') {
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
