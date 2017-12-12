<?php

namespace app\index\controller;

use app\common\controller\EntryCommon;
use app\index\model\Menu;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use think\Cache;
use think\View;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class ArticleList extends EntryCommon
{


    /**
     * 首页列表
     * @access public
     * @todo 需要考虑一下  文章列表 中列出来的文章需要从  sync_count 表中获取
     */
    public function index($id)
    {
        //每一个node下的菜单的英文名不能包含重复的值
        //根据_ 来分割 第一个参数表示 菜单的id_t文章分类的typeid_p页码id.html
        list($menu_enname, $type_id, $currentpage) = $this->analyseParams($id);
        //爬虫来源 统计
        $siteinfo = Site::getSiteInfo();
        $this->entryCommon();
        // 从缓存中获取数据
        $templatepath = $this->articletemplatepath;
        $assign_data = Cache::remember("articlelist_{$menu_enname}_{$type_id}_{$currentpage}", function () use ($menu_enname, $type_id, $siteinfo, $templatepath, $currentpage) {
            return $this->generateArticleList($menu_enname, $type_id, $siteinfo, $currentpage);
        }, 0);
        $template = $this->getTemplate('list', $assign_data['menu_id'], 'article');
        unset($assign_data['menu_id']);
        if (!$this->fileExists($template)) {
            return;
        }
        return (new View())->fetch($template,
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
    public function generateArticleList($menu_enname, $type_id, $siteinfo, $currentpage = 1)
    {
        if (empty($siteinfo["menu"])) {
            exit("当前网站没有选择栏目");
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
        //当前栏目的分类
        //获取列表页面必须的元素
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id, $type_id, 'articlelist');

        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($siteinfo['menu']);
        $sync_info = Commontool::getDbArticleListId($siteinfo['id']);
        $articlemax_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $article = [];
        //需要获取到当前分类下的所有二级目录
        if ($articlemax_id) {
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
                $type_info = $article_typearr[$ptype_id];
                $list = Commontool::getTypeArticleList($ptype_id, $articlemax_id, $article_typearr, 20);
                $typelist[] = [
                    'text' => $type_info['type_name'],
                    'href' => $type_info['href'],
                    //当前为true
                    'current' => $current,
                    'list' => $list
                ];
            }
            $typeid_str = implode(',', $typeidarr);
            $where = "id <=$articlemax_id and node_id={$siteinfo['node_id']} and articletype_id in ({$typeid_str})";
            //获取当前type_id的文章
            $article = \app\index\model\Article::order('id', "desc")->field(Commontool::$articleListField)->where($where)
                ->paginate($listsize, false, [
                    'path' => url('/articlelist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
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
                //列出当前文章分类来
                if (array_key_exists($v['articletype_id'], $article_typearr)) {
                    $type = [
                        'name' => $v['articletype_name'],
                        'href' => $article_typearr[$v['articletype_id']]['href']
                    ];
                }
                $v['a_href'] = sprintf(Commontool::$articlePath, $v['id']);
                $v['type'] = $type;
                $v["thumbnails"] = $img;
            }
        }
        $assign_data['type_list'] = $typelist;
        $assign_data['list'] = $article;
        $assign_data['menu_id'] = $menu_id;
        return $assign_data;
    }

}
