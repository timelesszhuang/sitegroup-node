<?php

namespace app\tool\controller;

use app\common\controller\Common;

use app\tool\traits\FileExistsTraits;
use app\tool\traits\Osstrait;
use OSS\OssClient;
use think\Cache;
use think\Db;
use think\Request;

/**
 * 页面静态化 入口文件
 * 该文件接收请求重新生成页面
 */
class Pagestaticentry extends Common
{

    use FileExistsTraits;
    use Osstrait;

    /**
     * 本地测试开启下 允许跨域ajax 获取数据
     */
    public function __construct()
    {
        parent::__construct();
        Cache::clear();
        $this->siteInit();
    }

    /**
     * 站点静态化的时候需要检查 更新的相关数据
     * @access private
     */
    private function siteInit()
    {
        $siteinfo = Site::getSiteInfo();
        $this->checkSiteLogo($siteinfo);
    }

    /**
     * 判断站点logo是不是有更新 有更新的话直接重新生成
     * @access private
     */
    private function checkSiteLogo($siteinfo)
    {
        $logo_id = $siteinfo['sitelogo_id'];
        if (!$logo_id) {
            return;
        }
        $site_logoinfo = Cache::remember('sitelogoinfo', function () use ($logo_id) {
            return Db::name('site_logo')->where('id', $logo_id)->find();
        });
        //如果存在logo 名字就叫 ××.jpg
        $oss_logo_path = $site_logoinfo['oss_logo_path'];
        $file_ext = $this->analyseUrlFileType($oss_logo_path);
        //logo 名称 根据站点id 拼成
        $local_img_name = "logo{$siteinfo['id']}.$file_ext";
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
     * crontabstatic
     * crontab 定期请求
     */
    public function crontabstatic()
    {
        // 详情页面生成
        (new Activitystatic())->index();
        (new Detailstatic())->index('crontab');
        // 详情类性的页面的静态化
        (new Detailmenupagestatic())->index();
        (new Indexstatic())->index();
        (new SiteMap)->index();
        exit(['status' => 'success', 'msg' => '首页静态化生成完成。']);
    }


    /**
     * 全部的页面静态化操作
     * @access public
     */
    public function allstatic()
    {
        //全部的页面的静态化
        // 详情页面生成
        (new Activitystatic())->index();
        (new Detailstatic())->index();
        // 详情类性的页面的静态化
        (new Detailmenupagestatic())->index();
        (new Indexstatic())->index();
        (new SiteMap)->index();
        exit(['status' => 'success', 'msg' => '首页静态化生成完成。']);
    }


    /**
     * 首页生成相关操作
     * @access public
     */
    public function indexstatic()
    {
        // 首先首页更新
        if ((new Indexstatic())->index()) {
            exit(['status' => 'success', 'msg' => '首页静态化生成完成。']);
        }
        exit(['status' => 'failed', 'msg' => '首页静态化生成失败。']);
    }


    /**
     * 文章静态化
     * @access public
     */
    public function articlestatic()
    {
        //文章页面的静态化
        (new Detailstatic())->index();
        exit(['status' => 'success', 'msg' => '文章页面生成完成。']);
    }


    /**
     * 菜单 静态化入口
     * @access public
     */
    public function menustatic()
    {
        //菜单详情页面 静态化 配置页面静态化
        if ((new Detailmenupagestatic())->index()) {
            exit(['status' => 'success', 'msg' => '栏目页静态化生成完成。']);
        }
        exit(['status' => 'failed', 'msg' => '栏目页静态化生成完成。']);
    }

    /**
     * 根据id和类型 重新生成静态化
     * 比如 修改文章相关信息之后重新生成
     * @param Request $request
     */
    public function reGenerateHtml(Request $request)
    {
        $id = $request->post("id");
        $searchType = $request->post("searchType");
        $type = $request->post("type");
        if ($id && $searchType && $type) {
            //重新生成
            (new Detailrestatic())->exec_refilestatic($id, $searchType, $type);
        }
    }


    /**
     * 获取单条数据内容 获取制定模板的id
     * @param $type
     * @param $name
     * @return array|string
     */
    public function staticOneHtml($type, $name)
    {
        return $this->staticOne($type, $name);
    }

    /**
     * 修改指定静态文件的内容 比如模板之类
     * @param $type
     * @param $name
     * @return array
     */
    public function generateOne($type, $name)
    {
        $content = $this->request->post("content");
        if (empty($content)) {
            return $this->resultArray("数据为空");
        }
        $this->generateStaticOne($type, $name, $content);
    }


    /**
     * url 安全的base64 编码
     * @access private
     */
    private function urlsafe_b64encode($string)
    {
        $data = base64_encode($string);
        $data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
        return $data;
    }


}
