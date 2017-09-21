<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\index\model\ArticleSyncCount;
use app\index\model\Question;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use think\Cache;
use think\View;
use app\tool\controller\FileExistsTraits;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class QuestionList extends Common
{
    use FileExistsTraits;
    use SpiderComefrom;

    /**
     * 首页列表
     * @access public
     */
    public function index($id, $currentpage = 1)
    {
        $templatepath = 'template/questionlist.html';
        //判断模板是否存在
        if (!$this->fileExists($templatepath)) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        $this->spidercomefrom($siteinfo);
        if (empty($siteinfo["menu"])) {
            exit("当前栏目为空");
        }
        if (empty(strstr($siteinfo["menu"], "," . $id . ","))) {
            exit("当前网站无此栏目");
        }
        $siteinfo = Site::getSiteInfo();
        //爬虫来源 统计
        $this->spidercomefrom($siteinfo);
        // 从缓存中获取数据
        $assign_data = Cache::remember("question" . "-" . $id . "-" . $currentpage, function () use ($id, $siteinfo, $templatepath, $currentpage) {
            return $this->generateQuestionList($id, $siteinfo, $templatepath, $currentpage);
        }, 0);
//        file_put_contents('log/questionlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return (new View())->fetch($templatepath,
            [
                'd' => $assign_data
            ]
        );

    }

    /**
     * question列表静态化
     * @param $id
     * @param $siteinfo
     * @param int $currentpage
     * @return array
     */
    public function generateQuestionList($id, $siteinfo, $templatepath, $currentpage = 1)
    {
        if (empty($siteinfo["menu"])) {
            exit("当前站点菜单配置异常");
        }
        if (empty(strstr($siteinfo["menu"], "," . $id . ","))) {
            exit("当前网站无此栏目");
        }
        $siteinfo = Site::getSiteInfo();
        $menu_info = \app\index\model\Menu::get($id);
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id, 'questionlist');
        $articleSyncCount = \app\index\model\ArticleSyncCount::where(["site_id" => $siteinfo['id'], "node_id" => $siteinfo['node_id'], "type_name" => "question", 'type_id' => $menu_info['type_id']])->find();
        $where["type_id"] = $menu_info->type_id;
        $question = [];
        if ($articleSyncCount) {
            $where["id"] = ["elt", $articleSyncCount->count];
            $question = Question::order('id', "desc")->field("id,question,create_time")->where($where)
                ->paginate(10, false, [
                    'path' => url('/questionlist', '', '') . "/{$id}/[PAGE].html",
                    'page' => $currentpage
                ]);
        }
        //获取当前type_id的文章
        $assign_data['question'] = $question;
        return $assign_data;
    }

}
