<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-8-28
 * Time: 上午11:35
 */

namespace app\index\controller;


use app\common\controller\Common;
use app\index\model\Menu;
use app\index\model\Product;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use think\Cache;
use think\View;

class ProductList extends Common
{

    use SpiderComefrom;

    /**
     * 首页列表
     * @access public
     */
    public function index($id)
    {
        //判断模板是否存在
        if (!$this->fileExists($this->productlisttemplate)) {
            return;
        }
        list($menu_enname, $type_id, $currentpage) = $this->analyseParams($id);
        $siteinfo = Site::getSiteInfo();
        $this->spidercomefrom($siteinfo);
        // 从缓存中获取数据
        $templatepath = $this->productlisttemplate;
        $assign_data = Cache::remember("productlist_{$menu_enname}_{$type_id}_{$currentpage}", function () use ($menu_enname, $type_id, $siteinfo, $templatepath, $currentpage) {
            return $this->generateProductList($menu_enname, $type_id, $siteinfo, $currentpage);
        }, 0);
        return (new View())->fetch($this->productlisttemplate,
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
    public function generateProductList($menu_enname, $type_id, $siteinfo, $currentpage = 1)
    {
        if (empty($siteinfo["menu"])) {
            exit("当前站点菜单配置异常");
        }
        $node_id = $siteinfo['node_id'];
        $menu_info = Menu::where('node_id', $node_id)->where('generate_name', $menu_enname)->find();
        if (!isset($menu_info->id)) {
            //没有获取到
            exit('该网站不存在该栏目');
        }
        $menu_id = $menu_info->id;
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id, 'productlist');
        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($siteinfo['menu']);
        $sync_info = Commontool::getDbArticleListId($siteinfo['id']);
        $productmax_id = array_key_exists('product', $sync_info) ? $sync_info['product'] : 0;
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $productlist = [];
        if ($productmax_id) {
            //该栏目下的所有分类id 包含子menu的分类
            $typeidarr = Commontool::getMenuChildrenMenuTypeid($menu_id, array_filter(explode(',', $menu_info->type_id)));
            //取出当前栏目下级的文章分类 根据path 中的menu_id
            $typelist = [];
            //如果 type_id=0 表示去除该菜单下的全部
            //    type_id=* 表示只需要取出该type_id 下的值
            foreach ($typeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                $type_info = $product_typearr[$ptype_id];
                $list = Commontool::getTypeProductList($ptype_id, $productmax_id, $product_typearr, 10);
                $typelist[] = [
                    'text' => $type_info['type_name'],
                    'href' => $type_info['href'],
                    //当前为true
                    'current' => $current,
                    'list' => $list
                ];
            }
            $typeid_str = implode(',', $typeidarr);
            $where = "id <={$productmax_id} and node_id={$siteinfo['node_id']} and type_id in ({$typeid_str})";
            //获取当前type_id的文章
            $productlist = Product::order('id', "desc")->field(Commontool::$productListField)->where($where)
                ->paginate(10, false, [
                    'path' => url('/productlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                    'page' => $currentpage
                ]);
            foreach ($productlist as $k => $v) {
                $src = "/images/" . $v['image_name'];
                $img = "<img src='{$src}' alt= '{$v['name']}'>";
                //列出当前文章分类来
                $type = [
                    'name' => '',
                    'href' => ''
                ];
                if (array_key_exists($v['type_id'], $product_typearr)) {
                    $type = [
                        'name' => $v['type_name'],
                        'href' => $product_typearr[$v['type_id']]['href']
                    ];
                }
                $v['a_href'] = sprintf(Commontool::$productPath, $v['id']);
                $v['thumbnails'] = $img;
                $v['type'] = $type;
            }
        }
        $assign_data['type_list'] = $typelist;
        $assign_data['list'] = $productlist;
        return $assign_data;
    }

}