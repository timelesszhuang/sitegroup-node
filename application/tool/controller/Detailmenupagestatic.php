<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\View;


/**
 *  详情型 菜单页面静态化 static
 */
class Detailmenupagestatic extends Common
{
    use FileExistsTraits;

    /**
     * 首页静态化
     * @access public
     */
    public function index()
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        $info = Menu::getDetailMenuInfo($siteinfo['menu'], $site_id, $site_name, $node_id);
        foreach ($info as $v) {
            $assign_data = Commontool::getEssentialElement('menu', $v['generate_name'], $v['name'], $v['id']);
//            file_put_contents('log/detailmenu.txt', $this->separator . date('Y-m-d H:i:s') . 'env中菜单名' . $v['name'] . print_r($assign_data, true) . $this->separator, FILE_APPEND);
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
                file_put_contents('log/detailmenu.txt', $this->separator . date('Y-m-d H:i:s') . '详情类型页面静态化写入失败。' . $this->separator, FILE_APPEND);
                continue;
            }
        }
        return true;
    }


}
