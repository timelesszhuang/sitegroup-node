<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\common\controller\EntryCommon;
use app\index\model\Menu;
use app\index\model\Question;
use app\index\traits\SpiderComefrom;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use think\Cache;
use think\View;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class QuestionList extends EntryCommon
{
    use SpiderComefrom;

    /**
     * 首页列表
     * @access public
     */
    public function index($id)
    {
        list($menu_enname, $type_id, $currentpage) = $this->analyseParams($id);
        $siteinfo = Site::getSiteInfo();
        $this->entryCommon();
        // 从缓存中获取数据
        $templatepath = $this->questionlisttemplate;
        $data = Cache::remember("questionlist_{$menu_enname}_{$type_id}_{$currentpage}", function () use ($menu_enname, $type_id, $siteinfo, $templatepath, $currentpage) {
            return $this->generateQuestionList($menu_enname, $type_id, $siteinfo, $currentpage);
        }, 0);
        $assign_data = $data['d'];
        $template = $this->getTemplate('list', $assign_data['menu_id'], 'product');
        unset($data['d']['menu_id']);
        //判断模板是否存在
        if (!$this->fileExists($template)) {
            return;
        }
        //页面中还需要填写隐藏的 表单 node_id site_id
        return Common::Debug((new View())->fetch($template,
            $data
        ), $data);
    }

    /**
     * question列表静态化
     * @param $id
     * @param $siteinfo
     * @param int $currentpage
     * @return array
     */
    public function generateQuestionList($menu_enname, $type_id, $siteinfo, $currentpage = 1)
    {
        if (empty($siteinfo["menu"])) {
            exit("当前站点菜单配置异常");
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
        $assign_data = Commontool::getEssentialElement('menu', $menu_info->generate_name, $menu_info->name, $menu_info->id, 'questionlist');
        list($type_aliasarr, $typeid_arr) = Commontool::getTypeIdInfo($siteinfo['menu']);
        $sync_info = Commontool::getDbArticleListId($siteinfo['id']);
        $questionmax_id = array_key_exists('question', $sync_info) ? $sync_info['question'] : 0;
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        $question = [];
        $currentquestion = [];
        $typelist = [];
        $siblingstypelist = [];
        if ($questionmax_id) {
            //获取当前栏目下的二级栏目的typeid 列表
            $typeidarr = Commontool::getMenuChildrenMenuTypeid($menu_id, array_filter(explode(',', $menu_info->type_id)));
            //取出当前栏目下级的文章分类 根据path 中的menu_id
            $typeid_str = implode(',', $typeidarr);
            if ($typeid_str) {
                $wheretemplate = "id <={$questionmax_id} and node_id={$siteinfo['node_id']} and type_id in (%s)";
                $question = Question::order('id', "desc")->field(Commontool::$questionListField)->where(sprintf($wheretemplate, $typeid_str))
                    ->paginate($listsize, false, [
                        'path' => url('/questionlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                        'page' => $currentpage
                    ]);
                Commontool::formatQuestionList($question, $question_typearr);
            }
            //取出当前菜单的列表 不包含子菜单的
            $typeid_str = implode(',', array_filter(explode(',', $menu_info->type_id)));
            if ($typeid_str) {
                $currentquestion = Question::order('id', "desc")->field(Commontool::$questionListField)->where(sprintf($wheretemplate, $typeid_str))
                    ->paginate($listsize, false, [
                        'path' => url('/questionlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                        'page' => $currentpage
                    ]);
                Commontool::formatQuestionList($currentquestion, $question_typearr);
            }
            //获取当前栏目的以及下级菜单的分类列表
            foreach ($typeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                $type_info = $question_typearr[$ptype_id];
                $list = Commontool::getTypeQuestionList($ptype_id, $questionmax_id, $question_typearr, 20);
                $typelist[] = [
                    'text' => $type_info['type_name'],
                    'href' => $type_info['href'],
                    //当前为true
                    'current' => $current,
                    'list' => $list
                ];
            }
            //获取当前菜单的同级别菜单下的type
            $sibilingtypeidarr = Commontool::getMenuSiblingMenuTypeid($menu_id);
            foreach ($sibilingtypeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                $type_info = $question_typearr[$ptype_id];
                $list = Commontool::getTypeQuestionList($ptype_id, $questionmax_id, $question_typearr, 20);
                $siblingstypelist[] = [
                    'text' => $type_info['type_name'],
                    'href' => $type_info['href'],
                    //当前为true
                    'current' => $current,
                    'list' => $list
                ];
            }
        }
        //获取当前type_id的文章
        $assign_data['menu_id'] = $menu_id;
//        $question['detail'] = '当前以及子菜单的所有问答列表，包含分页。';
//        $typelist['detail'] = '当前菜单以及子菜单的所有问答列表。';
//        $siblingstypelist['detail'] = '同级别菜单的所有问答列表。';
//        $currentquestion['detail'] = '当前菜单的问答列表，包含分页';
        return [
            'd' => $assign_data,
            'childlist' => $typelist,
            'siblingslist' => $siblingstypelist,
            'list' => $question,
            'currentlist' => $currentquestion
        ];
    }

}
