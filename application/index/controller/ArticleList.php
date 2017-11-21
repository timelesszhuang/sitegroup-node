<?php

namespace app\index\controller;

use app\tool\controller\Commontool;
use app\tool\controller\Site;
use app\common\controller\Common;
use app\tool\traits\FileExistsTraits;
use think\Cache;
use think\View;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class ArticleList extends Common
{
    use FileExistsTraits;
    use SpiderComefrom;

    /**
     * 首页列表
     * @access public
     * @todo 需要考虑一下  文章列表 中列出来的文章需要从  sync_count 表中获取
     */
    public function index($id, $currentpage = 1)
    {
//        print_r($id);
        $templatepath = 'template/articlelist.html';
        //判断模板是否存在
        if (!$this->fileExists($templatepath)) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        //爬虫来源 统计
        $this->spidercomefrom($siteinfo);
        // 从缓存中获取数据
        $assign_data = Cache::remember("articlelist" . "-" . $id . "-" . $currentpage, function () use ($id, $siteinfo, $templatepath, $currentpage) {
            return $this->generateArticleList($id, $siteinfo, $templatepath, $currentpage);
        }, 0);
        return (new View())->fetch($templatepath,
            [
                'd' => $assign_data
            ]
        );
    }


    /**
     * article列表静态化
     * @param $id
     * @param int $currentpage
     * @return array
     */
    public function generateArticleList($id, $siteinfo, $templatepath, $currentpage = 1)
    {
        if (empty($siteinfo["menu"])) {
            exit("当前栏目为空");
        }
        if (empty(strstr($siteinfo["menu"], "," . $id . ","))) {
            exit("当前网站无此栏目");
        }
        $menu_info = \app\index\model\Menu::get($id);
        if (is_null($menu_info)) {
            exit("unkown article");
        }
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id, 'articlelist');
        //取出同步的总数
        $articleSyncCount = \app\index\model\ArticleSyncCount::where(["site_id" => $siteinfo["id"], "node_id" => $siteinfo["node_id"], "type_name" => "article", 'type_id' => $menu_info['type_id']])->find();
        $article = [];
        if ($articleSyncCount) {
            $where = "id <={$articleSyncCount->count} and node_id={$siteinfo['node_id']} and articletype_id={$menu_info->type_id}";
            //获取当前type_id的文章
            $article = \app\index\model\Article::order('id', "desc")->field("id,title,thumbnails,thumbnails_name,summary,create_time")->where($where)
                ->paginate(10, false, [
                    'path' => url('/articlelist', '', '') . "/{$id}/[PAGE].html",
                    'page' => $currentpage
                ]);
            foreach ($article as $v) {
                $v['title'] = str_replace('%', '', $v['title']);
                $img_template = "<img src='%s' alt='{$v['title']}' title='{$v['title']}'>";
                $img = sprintf($img_template, '/templatestatic/default.jpg');
                if (!empty($v["thumbnails_name"])) {
                    //如果有本地图片则 为本地图片
                    $src = "/images/" . $v['thumbnails_name'];
                    $img = sprintf($img_template, $src);
                } else if (!empty($v["thumbnails"])) {
                    //如果没有本地图片则 直接显示 base64的
                    $img = sprintf($img_template, $v['thumbnails']);
                }
                $v["img"] = $img;
            }
        }
        $assign_data['article'] = $article;
        return $assign_data;
    }

}
