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
use think\Cache;
use think\View;

class ProductList
{
    use FileExistsTraits;
    use SpiderComefrom;

    /**
     * 首页列表
     * @access public
     */
    public function index($id, $currentpage = 1)
    {
        $templatelist = 'template/productlist.html';
        //判断模板是否存在
        if (!$this->fileExists($templatelist)) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        $this->spidercomefrom($siteinfo);
        // 从缓存中获取数据
        $assign_data = Cache::remember("productlist" . "-" . $id . "-" . $currentpage, function () use ($id, $siteinfo, $templatelist, $currentpage) {
            return $this->generateProductList($id, $siteinfo, $templatelist, $currentpage);
        }, 0);
        //file_put_contents('log/productlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        return (new View())->fetch($templatelist,
            [
                'd' => $assign_data
            ]
        );
    }

    /**
     * product列表静态化
     * @param $id
     * @param $siteinfo
     * @param int $currentpage
     * @return array
     */
    public function generateProductList($id, $siteinfo, $templatelist, $currentpage = 1)
    {
        if (empty($siteinfo["menu"])) {
            exit("当前站点菜单配置异常");
        }
        if (empty(strstr($siteinfo["menu"], "," . $id . ","))) {
            exit("当前网站无此栏目");
        }
        $siteinfo = Site::getSiteInfo();
        $menu_info = \app\index\model\Menu::get($id);
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id, 'productlist');
        $articleSyncCount = \app\index\model\ArticleSyncCount::where(["site_id" => $siteinfo['id'], "node_id" => $siteinfo['node_id'], "type_name" => "product", 'type_id' => $menu_info['type_id']])->find();
        $where["type_id"] = $menu_info->type_id;
        $productlist = [];
        if ($articleSyncCount) {
            $where["id"] = ["elt", $articleSyncCount->count];
            //获取当前type_id的文章
            $productlist = \app\index\model\Product::order('id', "desc")->field("id,name,image_name,create_time")->where($where)
                ->paginate(10, false, [
                    'path' => url('/productlist', '', '') . "/{$id}/[PAGE].html",
                    'page' => $currentpage
                ]);
            //循环展现产品的相关数据
            foreach ($productlist as $data) {
                //如果有本地图片则 为本地图片
                $src = "/images/" . $data['image_name'];
                $img = "<img src='$src' alt= '{$data['name']}'>";
                $data["img"] = $img;
            }
        }
        $assign_data['productlist'] = $productlist;
        return $assign_data;
    }

}