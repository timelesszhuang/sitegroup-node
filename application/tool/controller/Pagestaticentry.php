<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\Cache;
use think\Db;
use think\Request;

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
        //如果logo记录被删除的话怎么操作
        if (!$site_logoinfo) {
            return;
        }
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
        //需要执行下ping百度的操作
        //ping 搜索引擎
        $this->pingEngine();
        Cache::clear();
        exit(['status' => 'success', 'msg' => '静态化生成完成。']);
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
        $this->pingEngine();
        Cache::clear();
        exit(['status' => 'success', 'msg' => '静态化生成完成。']);
    }

    /**
     * 重新从第一条开始重新生成
     * 全部的页面静态化操作
     * @access public
     */
    public function resetall()
    {
        //全部的页面的静态化
        //重置下站点的已经同步到的地方
        Db::name('ArticleSyncCount')->where(['site_id' => $this->site_id, 'node_id' => $this->node_id])->update(['count' => 0]);
        (new Activitystatic())->index();
        (new Detailstatic())->index();
        // 详情类性的页面的静态化
        (new Detailmenupagestatic())->index();
        (new Indexstatic())->index();
        (new SiteMap)->index();
        $this->pingEngine();
        Cache::clear();
        exit(['status' => 'success', 'msg' => '静态化生成完成。']);
    }

    /**
     * 整站重新生成
     * @access public
     */
    public function allsitestatic()
    {
        Db::name('ArticleSyncCount')->where(['site_id' => $this->site_id, 'node_id' => $this->node_id])->update(['count' => 0]);
        //每次活动都会重新生成 整站全部重新生成
        (new Activitystatic())->index();
        (new Detailstatic())->index('allsitestatic');
        (new Detailmenupagestatic())->index();
        (new Indexstatic())->index();
        (new SiteMap)->index();
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
        $this->pingEngine();
        Cache::clear();
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
        Cache::clear();
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
        Cache::clear();
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
        // 检查文件夹
        if (!is_dir($type)) {
            return json_encode([
                "msg" => "文件未生成",
                "status" => "failed",
            ]);
        }
//        $resource = opendir($type);
//        $content = '';
        $filename = ROOT_PATH . "public/" . $type . "/" . $name . ".html";
        if (file_exists($filename)) {
            $content = base64_encode(file_get_contents($filename));
            return json_encode([
                "msg" => "",
                "status" => "success",
                "data" => $content
            ]);
        }
        return json_encode([
            "msg" => "文件未生成",
            "status" => "failed",
        ]);
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
        // 检查文件夹
        if (!is_dir($type)) {
            return $this->resultArray("文件夹不存在");
        }
        $filename = ROOT_PATH . "public/" . $type . "/" . $name . ".html";
        if (file_exists($filename)) {
            $content = file_put_contents($filename, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content);
            return json_encode([
                "msg" => "修改成功",
                "status" => "success",
                "data" => ""
            ]);
        }
        return json_encode([
            "msg" => "文件未生成",
            "status" => "failed",
            "data" => ''
        ]);
    }


}
