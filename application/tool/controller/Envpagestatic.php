<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\View;


/**
 * 配置文件中的页面静态化
 * 执行首页静态化相关操作
 */
class Envpagestatic extends Common
{
    use FileExistsTraits;

    /**
     * 配置文件配置页面静态化
     * @access public
     */
    public function index()
    {
        //判断模板是否存在

        $env_info = Menu::getEnvMenuInfo();
        foreach ($env_info as $v) {
            list($com_name, $title, $keyword, $description,
                $m_url, $redirect_code, $menu, $before_head,
                $after_head, $chain_type, $next_site,
                $main_site, $partnersite, $commonjscode,
                $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('envmenu', $v['generate_name'], $v['name']);
            $assign_data = compact('com_name', 'title', 'keyword', 'description', 'm_url', 'redirect_code', 'menu', 'before_head', 'after_head', 'chain_type', 'next_site', 'main_site', 'partnersite', 'commonjscode', 'article_list', 'question_list', 'scatteredarticle_list');
            file_put_contents('log/envmenu.txt', $this->separator . date('Y-m-d H:i:s') . 'env中菜单名' . $v['name'] . print_r($assign_data, true) . $this->separator, FILE_APPEND);
            //还需要 存储在数据库中 相关数据
            //页面中还需要填写隐藏的 表单 node_id site_id

            //判断下是不是有 模板文件
            if(!$this->fileExists('template/{$v["generate_name"]}.html')){
                return;
            }
            $content = (new View())->fetch("template/{$v['generate_name']}.html",
                [
                    'd' => $assign_data
                ]
            );
            file_put_contents("{$v['generate_name']}.html", $content);
        }
    }


}
