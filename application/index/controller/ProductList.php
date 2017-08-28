<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-8-28
 * Time: 上午11:35
 */

namespace app\index\controller;


use app\index\model\ArticleSyncCount;
use app\tool\controller\Commontool;
use app\tool\controller\FileExistsTraits;
use app\tool\controller\Site;
use think\View;

class ProductList
{
    use FileExistsTraits;

    /**
     * 首页列表
     * @access public
     */
    public function index($id)
    {
        $templatelist = 'template/productlist.html';
        //判断模板是否存在
        if (!$this->fileExists($templatelist)) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        if (empty($siteinfo["menu"])) {
            exit("当前站点菜单配置异常");
        }
        if (empty(strstr($siteinfo["menu"], "," . $id . ","))) {
            exit("当前网站无此栏目");
        }
        $siteinfo = Site::getSiteInfo();
        $menu_info = \app\index\model\Menu::get($id);
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id);
        $articleSyncCount = ArticleSyncCount::where(["site_id" => $siteinfo['id'], "node_id" => $siteinfo['node_id'], "type_name" => "product", 'type_id' => $menu_info['type_id']])->find();
        $where["type_id"] = $menu_info->type_id;
        $newslist = [];
        if ($articleSyncCount) {
            $where["id"] = ["elt", $articleSyncCount->count];
            //获取当前type_id的文章
            $productlist = \app\index\model\Product::order('id', "desc")->field("id,name,image")->where($where)->paginate(10);
        }
        $assign_data['productlist'] = $productlist->toArray();
        //file_put_contents('log/productlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return (new View())->fetch($templatelist,
            [
                'd' => $assign_data
            ]
        );
    }
}