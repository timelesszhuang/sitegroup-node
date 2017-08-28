<?php

namespace app\index\controller;

use app\index\model\ArticleSyncCount;
use app\index\model\Useragent;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use app\common\controller\Common;
use think\View;
use app\tool\controller\FileExistsTraits;

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
    public function index($id)
    {
        $templatepath = 'template/articlelist.html';
        //判断模板是否存在
        if (!$this->fileExists($templatepath)) {
            return;
        }
        $siteinfo = Site::getSiteInfo();

//        $data['node_id'] = $siteinfo['node_id'];
//        $data['site_id'] = $siteinfo['id'];
//        $data['useragent'] = $_SERVER['HTTP_USER_AGENT'];
//        if (preg_match("/Baiduspider/i", $_SERVER['HTTP_USER_AGENT'])) {
//            $data['engine'] = "baidu";
//            Useragent::create($data);
//        } elseif (preg_match("/Sogou web spider/i", $_SERVER['HTTP_USER_AGENT'])) {
//            $data['engine'] = "Sogou";
//            Useragent::create($data);
//        } elseif (preg_match("/HaoSouSpider/i", $_SERVER['HTTP_USER_AGENT'])) {
//            $data['engine'] = "360haosou";
//            Useragent::create($data);
//        } elseif (preg_match("/Googlebot/i", $_SERVER['HTTP_USER_AGENT'])) {
//            $data['engine'] = 'google';
//            Useragent::create($data);
//        }

        //爬虫来源 统计
        $this->spidercomefrom($siteinfo);

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
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id);
        //取出同步的总数
        $articleSyncCount = ArticleSyncCount::where(["site_id" => $siteinfo["id"], "node_id" => $siteinfo["node_id"], "type_name" => "article", 'type_id' => $menu_info['type_id']])->find();
        $article = [];
        if ($articleSyncCount) {
            $where = "id <={$articleSyncCount->count} and node_id={$siteinfo['node_id']} and articletype_id={$menu_info->type_id} and is_sync=20 or  (id <={$articleSyncCount->count} and node_id={$siteinfo['node_id']} and articletype_id={$menu_info->type_id} and site_id = {$siteinfo['id']})";
            //获取当前type_id的文章
            $article = \app\index\model\Article::order('id', "desc")->field("id,title,content,thumbnails,thumbnails_name,summary")->where($where)->paginate(10);
            foreach ($this->foreachArticle($article) as $item) {
                $data = $item();
                $img = "<img src='/templatestatic/default.jpg' alt=" . $data["title"] . ">";
                if (!empty($data["thumbnails_name"])) {
                    //如果有本地图片则 为本地图片
                    $src = "/images/" . $data['thumbnails_name'];
                    $img = "<img src='$src' alt= '{$data['title']}'>";
                } else if (!empty($data["thumbnails"])) {
                    $img = $data["thumbnails"];
                }
                $data["img"] = $img;
            }
        }
        $assign_data['article'] = $article->toArray();
        //file_put_contents('log/questionlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return (new View())->fetch($templatepath,
            [
                'd' => $assign_data
            ]
        );
    }


    /**
     * 遍历文章列表
     * @param $data
     * @return \Generator
     */
    public function foreachArticle($data)
    {
        foreach ($data as $item) {
            yield function () use ($item) {
                return $item;
            };
        }
    }

}
