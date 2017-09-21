<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\index\model\ArticleSyncCount;
use app\index\model\ScatteredTitle;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use think\Cache;
use think\View;
use app\tool\controller\FileExistsTraits;

/**
 * 文章列表零散段落相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class NewsList extends Common
{
    use FileExistsTraits;

    use SpiderComefrom;

    /**
     * 首页列表
     * @access public
     */
    public function index($id, $currentpage = 1)
    {
        $templatepath = 'template/newslist.html';
        //判断模板是否存在
        if (!$this->fileExists($templatepath)) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        $this->spidercomefrom($siteinfo);
        if (empty($siteinfo["menu"])) {
            exit("当前栏目为空");
        }
        if (empty(strstr($siteinfo["menu"], "," . $id . ","))) {
            exit("当前网站无此栏目");
        }
        $siteinfo = Site::getSiteInfo();
        $this->spidercomefrom($siteinfo);
        // 从缓存中获取数据
        $assign_data = Cache::remember("newslist" . "-" . $id . "-" . $currentpage, function () use ($id, $siteinfo, $templatepath, $currentpage) {
            return $this->generateNewsList($id, $siteinfo, $currentpage);
        }, 0);
        //file_put_contents('log/newslist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return (new View())->fetch($templatepath,
            [
                'd' => $assign_data
            ]
        );
    }


    /**
     * NEWS列表静态化
     * @param $id
     * @param $siteinfo
     * @param int $currentpage
     * @return array
     */
    public function generateNewsList($id, $siteinfo, $templatepath, $currentpage = 1)
    {
        if (empty($siteinfo["menu"])) {
            exit("当前站点菜单配置异常");
        }
        if (empty(strstr($siteinfo["menu"], "," . $id . ","))) {
            exit("当前网站无此栏目");
        }

        $siteinfo = Site::getSiteInfo();
        $menu_info = \app\index\model\Menu::get($id);
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id, 'newslist');
        $articleSyncCount = \app\index\model\ArticleSyncCount::where(["site_id" => $siteinfo['id'], "node_id" => $siteinfo['node_id'], "type_name" => "scatteredarticle", 'type_id' => $menu_info['type_id']])->find();
        $where["articletype_id"] = $menu_info->type_id;
        $newslist = [];
        if ($articleSyncCount) {
            $where["id"] = ["elt", $articleSyncCount->count];
            //获取当前type_id的文章
            $newslist = ScatteredTitle::order('id', "desc")->field("id,title,create_time")->where($where)
                ->paginate(10, false, [
                    'path' => url('/newslist', '', '') . "/{$id}/[PAGE].html",
                    'page' => $currentpage
                ]);
        }
        $assign_data['newslist'] = $newslist;
        //获取当前type_id的文章
        return $assign_data;
    }
}
