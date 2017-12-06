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
            $assign_data = Commontool::getEssentialElement('menu', $v['generate_name'], $v['name'], $v['id']);
            //还需要获取下级栏目的相关信息
            //还需要 存储在数据库中 相关数据
            //页面中还需要填写隐藏的 表单 node_id site_id
            //判断下是不是有 模板文件
            if (!$this->fileExists("template/{$v['generate_name']}.html")) {
                continue;
            }
            $content = (new View())->fetch("template/{$v['generate_name']}.html",
                [
                    'd' => $assign_data,
                    'content' => $v['content'],
                ]
            );
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
