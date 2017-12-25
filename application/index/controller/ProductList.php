<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-8-28
 * Time: 上午11:35
 */

namespace app\index\controller;

use app\common\controller\Common;
use app\common\controller\EntryCommon;
use app\index\model\Menu;
use app\index\model\Product;
use app\index\traits\SpiderComefrom;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use think\Cache;
use think\View;

class ProductList extends EntryCommon
{

    /**
     * 首页列表
     * @access public
     */
    public function index($id)
    {
        list($menu_enname, $type_id, $currentpage) = $this->analyseParams($id);
        $siteinfo = Site::getSiteInfo();
        $this->entryCommon();
        // 从缓存中获取数据
        $templatepath = $this->productlisttemplate;
        $data = Cache::remember("productlist_{$menu_enname}_{$type_id}_{$currentpage}", function () use ($menu_enname, $type_id, $siteinfo, $templatepath, $currentpage) {
            return $this->generateProductList($menu_enname, $type_id, $siteinfo, $currentpage);
        }, 0);
        $assign_data = $data['d'];
        $template = $this->getTemplate('list', $assign_data['menu_id'], 'product');
        unset($data['d']['menu_id']);
        //判断模板是否存在
        if (!$this->fileExists($template)) {
            return;
        }
        return Common::Debug((new View())->fetch($template,
            $data
        ), $data);
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
        //列表页多少条分页
        $listsize = $menu_info->listsize ?: 10;
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id, 'productlist');
        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($siteinfo['menu']);
        $sync_info = Commontool::getDbArticleListId($siteinfo['id']);
        $productmax_id = array_key_exists('product', $sync_info) ? $sync_info['product'] : 0;
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $typelist = [];
        $siblingstypelist = [];
        $productlist = [];
        $currentproductlist = [];
        if ($productmax_id) {
            //该栏目下的所有分类id 包含子menu的分类
            $typeidarr = Commontool::getMenuChildrenMenuTypeid($menu_id, array_filter(explode(',', $menu_info->type_id)));
            //取出当前栏目下级的文章分类 根据path 中的menu_id
            $typeid_str = implode(',', $typeidarr);
            if ($typeid_str) {
                $wheretemplate = "id <={$productmax_id} and node_id={$siteinfo['node_id']} and type_id in (%s)";
                //获取当前type_id的文章
                $productlist = Product::order('id', "desc")->field(Commontool::$productListField)->where(sprintf($wheretemplate, $typeid_str))
                    ->paginate($listsize, false, [
                        'path' => url('/productlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                        'page' => $currentpage
                    ]);
                Commontool::formatProductList($productlist, $product_typearr);
            }
            //取出当前菜单的列表 不包含子菜单的
            $typeid_str = implode(',', array_filter(explode(',', $menu_info->type_id)));
            if ($typeid_str) {
                $currentproductlist = Product::order('id', "desc")->field(Commontool::$productListField)->where(sprintf($wheretemplate, $typeid_str))
                    ->paginate($listsize, false, [
                        'path' => url('/productlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                        'page' => $currentpage
                    ]);
                Commontool::formatProductList($currentproductlist, $product_typearr);
            }
            //如果 type_id=0 表示去除该菜单下的全部
            //    type_id=* 表示只需要取出该type_id 下的值
            foreach ($typeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                $type_info = $product_typearr[$ptype_id];
                $list = Commontool::getTypeProductList($ptype_id, $productmax_id, $product_typearr, 20);
                $typelist[] = [
                    'text' => $type_info['type_name'],
                    'href' => $type_info['href'],
                    //当前为true
                    'current' => $current,
                    'list' => $list
                ];
            }
            $sibilingtypeidarr = Commontool::getMenuSiblingMenuTypeid($menu_id);
            foreach ($sibilingtypeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                $type_info = $product_typearr[$ptype_id];
                $list = Commontool::getTypeProductList($ptype_id, $productmax_id, $product_typearr, 20);
                $siblingstypelist[] = [
                    'text' => $type_info['type_name'],
                    'href' => $type_info['href'],
                    //当前为true
                    'current' => $current,
                    'list' => $list
                ];
            }
        }
        $assign_data['menu_id'] = $menu_id;
        return [
            'd' => $assign_data,
            'childlist' => $typelist,
            'siblingslist' => $siblingstypelist,
            //当前以及下级的所有list
            'list' => $productlist,
            //当前的list
            'currentlist' => $currentproductlist
        ];
    }

}