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
    //公共操作对象
    public $commontool;

    public function __construct()
    {
        parent::__construct();
        $this->commontool = new Commontool();
        $this->commontool->tag = 'menu';
    }

    /**
     * 首页列表
     * @access public
     */
    public function index($id)
    {
        list($menu_enname, $type_id, $currentpage) = $this->analyseParams($id);
        $this->entryCommon();
        // 从缓存中获取数据
        $templatepath = $this->questionlisttemplate;
        $data = Cache::remember("questionlist_{$menu_enname}_{$type_id}_{$currentpage}", function () use ($menu_enname, $type_id, $templatepath, $currentpage) {
            return $this->generateQuestionList($menu_enname, $type_id, $currentpage);
        }, 0);
        $assign_data = $data['d'];
        $template = $this->getTemplate('list', $assign_data['menu_id'], 'question');
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
     * @param $menu_enname
     * @param $type_id
     * @param $siteinfo
     * @param int $currentpage
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function generateQuestionList($menu_enname, $type_id, $currentpage = 1)
    {
        if (empty($this->menu_ids)) {
            exit("当前站点菜单配置异常");
        }
        $menu_info = (new \app\index\model\Menu)->where('node_id', $this->node_id)->where('generate_name', $menu_enname)->find();
        if (!isset($menu_info->id)) {
            //没有获取到
            exit('该网站不存在该栏目');
        }
        $menu_id = $menu_info->id;
        //列表页多少条分页
        $listsize = $menu_info->listsize ?: 10;
        $assign_data = $this->commontool->getEssentialElement($menu_info->generate_name, $menu_info->name, $menu_info->id, 'questionlist');
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        $sync_info = $this->commontool->getDbArticleListId();
        $questionmax_id = array_key_exists('question', $sync_info) ? $sync_info['question'] : 0;
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        $question = [];
        $currentquestion = [];
        $typelist = [];
        $siblingstypelist = [];
        if ($questionmax_id) {
            //获取当前栏目下的二级栏目的typeid 列表
            $typeidarr = $this->commontool->getMenuChildrenMenuTypeid($menu_id, array_filter(explode(',', $menu_info->type_id)));
            //取出当前栏目下级的文章分类 根据path 中的menu_id
            $typeid_str = implode(',', $typeidarr);
            $where = "id <={$questionmax_id} and node_id={$this->node_id} and type_id in (%s)";
            if ($typeid_str) {
                $question = (new \app\index\model\Question)->order('id', "desc")->field($this->commontool->questionListField)->where(sprintf($where, $typeid_str))
                    ->paginate($listsize, false, [
                        'path' => url('/questionlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                        'page' => $currentpage
                    ]);
                $this->commontool->formatQuestionList($question, $question_typearr);
            }
            //取出当前菜单的列表 不包含子菜单的
            if ($type_id != 0) {
                $typeid_str = "$type_id";
            } else {
                $typeid_str = implode(',', array_filter(explode(',', $menu_info->type_id)));
            }
            if ($typeid_str) {
                $currentquestion = (new \app\index\model\Question)->order('id', "desc")->field($this->commontool->questionListField)->where(sprintf($where, $typeid_str))
                    ->paginate($listsize, false, [
                        'path' => url('/questionlist', '', '') . "/{$menu_enname}_t{$type_id}_p[PAGE].html",
                        'page' => $currentpage
                    ]);
                $this->commontool->formatQuestionList($currentquestion, $question_typearr);
            }
            //获取当前栏目的以及下级菜单的分类列表
            foreach ($typeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                if (!array_key_exists($ptype_id, $question_typearr)) {
                    // 表示菜单中虽然选择了该菜单    但是分类中没有已经删除掉了
                    continue;
                }
                $type_info = $question_typearr[$ptype_id];
                $list = $this->commontool->getTypeQuestionList($ptype_id, $questionmax_id, $question_typearr, 20);
                $typelist[] = [
                    'text' => $type_info['type_name'],
                    'href' => $type_info['href'],
                    //当前为true
                    'current' => $current,
                    'list' => $list
                ];
            }
            //获取当前菜单的同级别菜单下的type
            $flag = 2;
            $sibilingtypeidarr = $this->commontool->getMenuSiblingMenuTypeid($menu_id, $flag);
            foreach ($sibilingtypeidarr as $ptype_id) {
                $current = false;
                if ($type_id == $ptype_id) {
                    $current = true;
                }
                if (!array_key_exists($ptype_id, $question_typearr)) {
                    // 表示菜单中虽然选择了该菜单    但是分类中没有已经删除掉了
                    continue;
                }
                $type_info = $question_typearr[$ptype_id];
                $list = $this->commontool->getTypeQuestionList($ptype_id, $questionmax_id, $question_typearr, 20);
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
        return [
            'd' => $assign_data,
            'childlist' => $typelist,
            'siblingslist' => $siblingstypelist,
            'list' => $question,
            'currentlist' => $currentquestion
        ];
    }

}
