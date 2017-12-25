<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\View;


/**
 *  详情型 菜单页面静态化 static
 */
class Detailmenupagestatic extends Common
{

    /**
     * 首页静态化
     * @access public
     */
    public function index()
    {
        $siteinfo = Site::getSiteInfo();
        $info = Menu::getDetailMenuInfo($siteinfo['menu'], $this->site_id, $this->site_name, $this->node_id);
        $pingUrls = [];
        foreach ($info as $v) {
            $menu_id = $v['id'];
            $p_id = $v['p_id'];
            $template = $this->getTemplate('cover', $menu_id);
            //模板不存在的情况
            if (!$this->fileExists($template)) {
                continue;
            }
            $assign_data = Commontool::getEssentialElement('menu', $v['generate_name'], $v['name'], $v['id']);
            //获取该详情形式下级的菜单相关内容
            $childmenu = \app\tool\model\Menu::Where('p_id', $menu_id)->Where('flag', '1')->field('id,name,generate_name,title,content,covertemplate')->order('sort', 'desc')->select();
            //同级的菜单列表
            $sibilingmenulist = [];
            $childmenulist = [];
            foreach ($childmenu as $v) {
                $childmenulist = [
                    'text' => $v['name'],
                    'href' => '/' . $v['generate_name'] . '.html',
                    //下级的没有当前选中
                    'current' => false,
                    'content' => $v['content']
                ];
            }
            //有可能上级就是空的所以同级的只需要取出
            $siblingmenu = \app\tool\model\Menu::Where('p_id', $p_id)->Where('flag', '1')->field('id,name,generate_name,title,content,covertemplate')->order('sort', 'desc')->select();
            //获取同级的菜单
            foreach ($siblingmenu as $v) {
                $current = $v['id'] == $menu_id ? true : false;
                $sibilingmenulist = [
                    'text' => $v['name'],
                    'href' => '/' . $v['generate_name'] . '.html',
                    //下级的没有当前选中
                    'current' => $current,
                    'list' => $v['content']
                ];
            }
            //还需要获取下级栏目的相关信息
            //还需要 存储在数据库中 相关数据
            //页面中还需要填写隐藏的 表单 node_id site_id
            //判断下是不是有 模板文件
            //需要调出当前分类下的子菜单
            $data = [
                'd' => $assign_data,
                'content' => $v['content'],
                //同级列表
                'siblingslist' => $sibilingmenulist,
                //下级列表
                'childlist' => $childmenulist,
            ];
            $content = Common::Debug((new View())->fetch($template,
                $data
            ), $data);
            if (file_put_contents("{$v['generate_name']}.html", $content) === 'false') {
                continue;
            } else {
                array_push($pingUrls, $this->siteurl . "/{$v['generate_name']}.html");
            }
        }
        //推送搜索引擎更新数据
        $this->urlsCache($pingUrls);
        return true;
    }


}
