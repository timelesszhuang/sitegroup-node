<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\View;


/**
 * 首页静态化
 * 执行首页静态化相关操作
 */
class Envpagestatic extends Common
{

    /**
     * 首恶静态化
     * @access public
     */
    public function index()
    {
        $env_info = Menu::getEnvMenuInfo();
        foreach ($env_info as $v) {
            list($com_name, $title, $keyword, $description,
                $m_url, $redirect_code, $menu, $before_head,
                $after_head, $chain_type, $next_site,
                $main_site, $partnersite, $commonjscode,
                $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('envmenu', $v['genarate_name'], $v['name']);
            $assign_data = compact('com_name', 'title', 'keyword', 'description', 'm_url', 'redirect_code', 'menu', 'before_head', 'after_head', 'chain_type', 'next_site', 'main_site', 'partnersite', 'commonjscode', 'article_list', 'question_list', 'scatteredarticle_list');

            file_put_contents('log/index.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);

            //还需要 存储在数据库中 相关数据
            //页面中还需要填写隐藏的 表单 node_id site_id
            $content = (new View())->fetch('template/index.html',
                [
                    'd' => $assign_data
                ]
            );
            file_put_contents('index.html', $content);
        }
    }


}
