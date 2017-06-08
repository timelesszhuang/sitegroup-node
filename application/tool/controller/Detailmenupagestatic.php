<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\View;


/**
 *  详情型 页面 static
 */
class Detailmenupagestatic extends Common
{

    /**
     * 首恶静态化
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
            print_r($v);
            list($com_name, $title, $keyword, $description,
                $m_url, $redirect_code, $menu, $before_head,
                $after_head, $chain_type, $next_site,
                $main_site, $partnersite, $commonjscode,
                $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('menu', $v['generate_name'], $v['name'], $v['id']);
            $assign_data = compact('com_name', 'title', 'keyword', 'description', 'm_url', 'redirect_code', 'menu', 'before_head', 'after_head', 'chain_type', 'next_site', 'main_site', 'partnersite', 'commonjscode', 'article_list', 'question_list', 'scatteredarticle_list');
            print_r($assign_data);
            exit;
            file_put_contents('log/detailmenu.txt', $this->separator . date('Y-m-d H:i:s') . 'env中菜单名' . $v['name'] . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //还需要 存储在数据库中 相关数据
            //页面中还需要填写隐藏的 表单 node_id site_id
            $content = (new View())->fetch("template/{$v['generate_name']}.html",
                [
                    'd' => $assign_data
                ]
            );
            file_put_contents("{$v['genarate_name']}.html", $content);
        }
    }


}
