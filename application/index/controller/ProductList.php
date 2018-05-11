<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-8-28
 * Time: 上午11:35
 */

namespace app\index\controller;

use app\common\controller\EntryCommon;
use app\index\model\Product;
use app\tool\controller\Commontool;
use think\Cache;
use think\View;

class ProductList extends EntryCommon
{
    public $commontool;

    public function __construct()
    {
        parent::__construct();
        $this->commontool = new Commontool();
        $this->commontool->tag = 'menu';
    }

    /**
     * 首页列表
     * @access public
     */
    public function index($id)
    {
        list($menu_enname, $type_id, $currentpage) = $this->analyseParams($id);
        $this->entryCommon();
        // 从缓存中获取数据
        $templatepath = $this->productlisttemplate;
        $data = Cache::remember("productlist_{$menu_enname}_{$type_id}_{$currentpage}{$this->suffix}", function () use ($menu_enname, $type_id, $templatepath, $currentpage) {
            return $this->generateProductList($menu_enname, $type_id, $currentpage);
        }, 0);
        $assign_data = $data['d'];
        $template = $this->getTemplate('list', $assign_data['menu_id'], 'product');
        unset($data['d']['menu_id']);
        //判断模板是否存在
        if (!$this->fileExists($template)) {
            return;
        }
        return $this->Debug((new View())->fetch($template,
            $data
        ), $data);
    }

    /**
     * product列表静态化
     * @param $menu_enname
     * @param $type_id
     * @param int $currentpage
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @throws \throwable
     * @return array
     */
    public function generateProductList($menu_enname, $type_id, $currentpage = 1)
    {
        if (empty($this->menu_ids)) {
            exit("当前站点菜单配置异常");
        }
        $menu_info = (new \app\index\model\Menu)->where('node_id', $this->node_id)->where('generate_name', $menu_enname)->find();
        if (!isset($menu_info->id)) {
            //没有获取到
            exit('该网站不存在该栏目');
        }
        $menu_id = $menu_info->id;
        //列表页多少条分页
        $listsize = $menu_info->listsize ?: 10;
        $assign_data = $this->commontool->getEssentialElement($menu_info->generate_name, ['menu_name' => $menu_info->name, 'menu_enname' => $menu_info->generate_name], $menu_info->id, 'productlist');
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $typelist = [];
        $siblingstypelist = [];
        $productlist = [];
        $currentproductlist = [];
        //该栏目下的所有分类id 包含子menu的分类
        $typeidarr = $this->commontool->getMenuChildrenMenuTypeid($menu_id, array_filter(explode(',', $menu_info->type_id)));
        //取出当前栏目下级的文章分类 根据path 中的menu_id
        $typeid_str = implode(',', $typeidarr);
        list($where,$productmax_id)=$this->commontool->getProductQueryWhere();
        if ($typeid_str && $productmax_id) {
            //获取当前type_id的文章
            $productlist = (new Product())->order(['sort' => 'desc', 'id' => 'desc'])->field($this->commontool->productListField)->where(sprintf($where, $typeid_str))
                ->paginate($listsize, false, [
                    'path' => url('/productlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                    'page' => $currentpage
                ]);
            $this->commontool->formatProductList($productlist, $product_typearr);
        }
        //取出当前菜单的列表 不包含子菜单的
        if ($type_id != 0) {
            $typeid_str = "$type_id";
        } else {
            $typeid_str = implode(',', array_filter(explode(',', $menu_info->type_id)));
        }
        if ($typeid_str) {
            $currentproductlist = (new Product())->order(['sort' => 'desc', 'id' => 'desc'])->field($this->commontool->productListField)->where(sprintf($where, $typeid_str))
                ->paginate($listsize, false, [
                    'path' => url('/productlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                    'page' => $currentpage
                ]);
            $this->commontool->formatProductList($currentproductlist, $product_typearr);
        }
        //如果 type_id=0 表示去除该菜单下的全部
        //    type_id=* 表示只需要取出该type_id 下的值
        foreach ($typeidarr as $ptype_id) {
            $current = false;
            if ($type_id == $ptype_id) {
                $current = true;
            }
            if (!array_key_exists($ptype_id, $product_typearr)) {
                // 表示菜单中虽然选择了该菜单    但是分类中没有已经删除掉了
                continue;
            }
            $type_info = $product_typearr[$ptype_id];
            $list = [];
            if ($productmax_id) {
                $list = $this->commontool->getTypeProductList($ptype_id, $product_typearr, 20);
            }
            $typelist[] = [
                'text' => $type_info['type_name'],
                'href' => $type_info['href'],
                //当前为true
                'current' => $current,
                'list' => $list
            ];
        }
        $flag = 5;
        $sibilingtypeidarr = $this->commontool->getMenuSiblingMenuTypeid($menu_id, $flag);
        foreach ($sibilingtypeidarr as $ptype_id) {
            $current = false;
            if ($type_id == $ptype_id) {
                $current = true;
            }
            if (!array_key_exists($ptype_id, $product_typearr)) {
                // 表示菜单中虽然选择了该菜单    但是分类中没有已经删除掉了
                continue;
            }
            $type_info = $product_typearr[$ptype_id];
            $list = $this->commontool->getTypeProductList($ptype_id, $product_typearr, 20);
            $siblingstypelist[] = [
                'text' => $type_info['type_name'],
                'href' => $type_info['href'],
                //当前为true
                'current' => $current,
                'list' => $list
            ];
        }
        $assign_data['menu_id'] = $menu_id;
        //说明每个列表作用
        return [
            'd' => $assign_data,
            //
            'childlist' => $typelist,
            //
            'siblingslist' => $siblingstypelist,
            //当前以及下级的所有list
            'list' => $productlist,
            //当前的list
            'currentlist' => $currentproductlist
        ];
    }

}