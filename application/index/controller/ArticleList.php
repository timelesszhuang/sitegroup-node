<?php

namespace app\index\controller;
use app\tool\controller\Article;
use app\tool\controller\Commontool;
use app\tool\controller\Keyword;
use app\tool\controller\Menu;
use app\tool\controller\Site;
use app\common\controller\Common;
use think\View;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class ArticleList extends Common
{
    /**
     * 首页列表
     * @access public
     */
    public function index($id)
    {
        $menu_info=\app\index\model\Menu::get($id);
        list($com_name, $title, $keyword, $description,
            $m_url, $redirect_code, $menu, $before_head,
            $after_head, $chain_type, $next_site,
            $main_site, $partnersite, $commonjscode,
            $article_list, $question_list, $scatteredarticle_list) = Commontool::getEssentialElement('menu',$menu_info->generate_name,$menu_info->name,$menu_info->id);

        //获取当前type_id的文章
        $article=\app\index\model\Article::order('id',"desc")->where(["articletype_id"=>$menu_info->type_id])->paginate();
        $assign_data = compact('article','com_name', 'title', 'keyword', 'description', 'm_url', 'redirect_code', 'before_head', 'after_head', 'chain_type', 'next_site','main_site','common_site','partnersite','commonjscode','article_list','question_list','scatteredarticle_list');
//        file_put_contents('log/questionlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return  (new View())->fetch('template/article.html',
            [
                'd' => $assign_data
            ]
        );
    }

}
