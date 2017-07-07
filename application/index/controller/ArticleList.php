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

    /**
     * 首页列表
     * @access public
     * @todo 需要考虑一下  文章列表 中列出来的文章需要从  sync_count 表中获取
     */
    public function index($id)
    {
        //判断模板是否存在
        if (!$this->fileExists('template/articlelist.html')) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        $data['node_id'] = $siteinfo['node_id'];
        $data['site_id'] = $siteinfo['id'];
        $data['useragent'] = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match("/Baiduspider/i", $_SERVER['HTTP_USER_AGENT'])) {
            $data['engine'] = "baidu";
            Useragent::create($data);
        } elseif (preg_match("/Sogou web spider/i", $_SERVER['HTTP_USER_AGENT'])) {
            $data['engine'] = "Sogou";
            Useragent::create($data);
        } elseif (preg_match("/HaoSouSpider/i", $_SERVER['HTTP_USER_AGENT'])) {
            $data['engine'] = "360haosou";
            Useragent::create($data);
        }
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
        $articleSyncCount = ArticleSyncCount::where(["site_id" => $data["site_id"], "node_id" => $data["node_id"], "type_name" => "article", 'type_id' => $menu_info['type_id']])->find();
        $article=[];
        if ($articleSyncCount) {
            $where="id <={$articleSyncCount->count} and node_id={$siteinfo['node_id']} and articletype_id={$menu_info->type_id} and is_sync=20 or  (id <={$articleSyncCount->count} and node_id={$siteinfo['node_id']} and articletype_id={$menu_info->type_id} and site_id = {$siteinfo['id']})";
            //获取当前type_id的文章
            $article = \app\index\model\Article::order('id', "desc")->where($where)->paginate(10);
        }
        $assign_data['article'] = $article;
        file_put_contents('log/questionlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return (new View())->fetch('template/articlelist.html',
            [
                'd' => $assign_data
            ]
        );
    }

}
