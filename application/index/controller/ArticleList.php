<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\common\controller\EntryCommon;
use app\index\model\Menu;
use app\tool\controller\Commontool;
use think\Cache;
use think\View;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class ArticleList extends EntryCommon
{
    //公共操作对象
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
     * @todo 需要考虑一下  文章列表 中列出来的文章需要从  sync_count 表中获取
     * @param $id
     * @return string|void
     * @throws \think\Exception
     */
    public function index($id)
    {
        //每一个node下的菜单的英文名不能包含重复的值
        //根据_ 来分割 第一个参数表示 菜单的id_t文章分类的typeid_p页码id.html
        list($menu_enname, $type_id, $currentpage) = $this->analyseParams($id);
        //爬虫来源 统计
        $this->entryCommon();
        // 从缓存中获取数据
        $templatepath = $this->articletemplatepath;
        $data = Cache::remember("articlelist_{$menu_enname}_{$type_id}_{$currentpage}{$this->suffix}", function () use ($menu_enname, $type_id, $templatepath, $currentpage) {
            return $this->generateArticleList($menu_enname, $type_id, $currentpage);
        }, 0);
        $assign_data = $data['d'];
        $template = $this->getTemplate('list', $assign_data['menu_id'], 'article');
        unset($data['d']['menu_id']);
        if (!$this->fileExists($template)) {
            return;
        }
        return Common::Debug((new View())->fetch($template,
            $data
        ), $data);
    }


    /**
     * article列表静态化
     * @param $menu_enname
     * @param $type_id
     * @param int $currentpage
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function generateArticleList($menu_enname, $type_id, $currentpage = 1)
    {
        if (empty($this->menu_ids)) {
            exit("当前网站没有选择栏目");
        }
        $menu_info = (new Menu())->where('node_id', $this->node_id)->where('generate_name', $menu_enname)->find();
        if (!isset($menu_info->id)) {
            //没有获取到
            exit('该网站不存在该栏目');
        }
        $menu_id = $menu_info->id;
        //列表页多少条分页
        $listsize = $menu_info->listsize ?: 10;
        //当前栏目的分类
        //获取列表页面必须的元素
        $assign_data = $this->commontool->getEssentialElement($menu_info->generate_name, $menu_info->name, $menu_info->id, $type_id, 'articlelist');
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        $sync_info = $this->commontool->getDbArticleListId();
        $articlemax_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $article = [];
        $currentarticle = [];
        $typelist = [];
        $siblingstypelist = [];
        //需要获取到当前分类下的所有二级目录
        if ($articlemax_id) {
            //该栏目下的所有分类id 包含子menu的分类
            $typeidarr = $this->commontool->getMenuChildrenMenuTypeid($menu_id, array_filter(explode(',', $menu_info->type_id)));
            //取出当前栏目下级的文章分类 根据path 中的menu_id
            $typeid_str = implode(',', $typeidarr);
            $where_template = "id <=$articlemax_id and node_id={$this->node_id} and articletype_id in (%s)";
            if (!$this->mainsite) {
                $where_template .= ' and stations = "10"';
            }
            if ($typeid_str) {
                //获取当前type_id的文章
                $article = (new \app\index\model\Article())->order('id', "desc")->field($this->commontool->articleListField)->where(sprintf($where_template, $typeid_str))
                    ->paginate($listsize, false, [
                        'path' => url('/articlelist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                        'page' => $currentpage
                    ]);
                $this->commontool->formatArticleList($article, $article_typearr);
            }
            //取出当前菜单的列表 不包含子菜单的
            if ($type_id != 0) {
                $typeid_str = "$type_id";
            } else {
                $typeid_str = implode(',', array_filter(explode(',', $menu_info->type_id)));
            }
            if ($typeid_str) {
                $currentarticle = (new \app\index\model\Article())->order('id', "desc")->field($this->commontool->articleListField)->where(sprintf($where_template, $typeid_str))
                    ->paginate($listsize, false, [
                        'path' => url('/articlelist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                        'page' => $currentpage
                    ]);
                $this->commontool->formatArticleList($currentarticle, $article_typearr);
            }
            foreach ($typeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                if (!array_key_exists($ptype_id, $article_typearr)) {
                    // 表示菜单中虽然选择了该菜单    但是分类中没有已经删除掉了
                    continue;
                }
                $type_info = $article_typearr[$ptype_id];
                $list = $this->commontool->getTypeArticleList($ptype_id, $articlemax_id, $article_typearr, 20);
                $typelist[] = [
                    'text' => $type_info['type_name'],
                    'href' => $type_info['href'],
                    //当前为true
                    'current' => $current,
                    'list' => $list
                ];
            }
            //获取当前菜单的同级别菜单
            $flag = 3;
            $sibilingtypeidarr = $this->commontool->getMenuSiblingMenuTypeid($menu_id, $flag);
            foreach ($sibilingtypeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                if (!array_key_exists($ptype_id, $article_typearr)) {
                    // 表示菜单中虽然选择了该菜单    但是分类中没有已经删除掉了
                    continue;
                }
                $type_info = $article_typearr[$ptype_id];
                $list = $this->commontool->getTypeArticleList($ptype_id, $articlemax_id, $article_typearr, 20);
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
            //当前子集的分类
            'childlist' => $typelist,
            //还有同级的菜单
            'siblingslist' => $siblingstypelist,
            //子集的数据也需要展现出来
            'list' => $article,
            'currentlist' => $currentarticle,
        ];
    }

}
