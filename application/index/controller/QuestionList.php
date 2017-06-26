<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\index\model\ArticleSyncCount;
use app\index\model\Question;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use think\View;
use app\tool\controller\FileExistsTraits;
use app\index\model\ArticleSyncCount;
/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class QuestionList extends Common
{
    use FileExistsTraits;

    /**
     * 首页列表
     * @access public
     */
    public function index($id)
    {
        //判断模板是否存在
        if (!$this->fileExists('template/questionlist.html')) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        if (empty($siteinfo["menu"])) {
            exit("当前栏目为空");
        }
        if (empty(strstr($siteinfo["menu"], "," . $id . ","))) {
            exit("当前网站无此栏目");
        }
        $siteinfo = Site::getSiteInfo();
        $menu_info = \app\index\model\Menu::get($id);
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id);
        $articleSyncCount = ArticleSyncCount::where(["site_id" => $siteinfo['id'], "node_id" => $siteinfo['node_id'], "type_name" => "question"])->find();
        $where["type_id"] = $menu_info->type_id;
        if ($articleSyncCount) {
            $where["id"] = ["lt", $articleSyncCount->count];
        }
        //获取当前type_id的文章
        $question = Question::order('id', "desc")->where($where)->paginate();
        $assign_data['question'] = $question;
        file_put_contents('log/questionlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return (new View())->fetch('template/questionlist.html',
            [
                'd' => $assign_data
            ]
        );

    }

}
