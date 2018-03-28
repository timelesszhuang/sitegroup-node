<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\common\controller\EntryCommon;
use app\tool\controller\Commontool;
use app\tool\controller\Detailmenupagestatic;
use app\tool\controller\Detailstatic;
use app\tool\controller\Indexstatic;
use app\tool\model\Menu;
use think\Cache;
use think\Db;
use think\View;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class Detailenter extends EntryCommon
{

    /**
     * 首页入口
     */
    public function index()
    {
        $this->entryCommon();
        if ($this->mainsite) {
            // 主站相关
            $filename = $this->detailmenupath . 'index.html';
            exit(file_get_contents($filename));
        }
        //分站相关
        $index = new Indexstatic();
        return $index->indexstaticdata();
    }


    /**
     * @param $id
     * 判断文章页面是否存在
     * @throws \think\Exception
     */
    public function article($id)
    {
        if ($this->mainsite) {
            $id = $this->subNameId($id, 'article');
            $filename = sprintf('article/%s.html', $id);
            if (file_exists($filename)) {
                exit(file_get_contents($filename));
            } else {
                //如果不存在的话  跳转到首页
                exit(file_get_contents('index.html'));
            }
        }
        // 子站相关 可以使用预览部分的相关功能
        list($template, $data) = (new Detailstatic())->article_detailinfo(str_replace('article', '', $id));
        $content = Common::Debug((new View())->fetch($template,
            $data
        ), $data);
        exit($content);
    }

    /**
     * @param $id
     * 判断问答页面是否存在
     * @throws \think\Exception
     */
    public function question($id)
    {
        $this->entryCommon();
        if ($this->mainsite) {
            $id = $this->subNameId($id, 'question');
            $filename = sprintf('question/%s.html', $id);
            if (file_exists($filename)) {
                exit(file_get_contents($filename));
            } else {
                exit(file_get_contents('index.html'));
            }
        }
        // 子站相关 可以使用预览部分的相关功能
        list($template, $data) = (new Detailstatic())->question_detailinfo(str_replace('question', '', $id));
        $content = Common::Debug((new View())->fetch($template,
            $data
        ), $data);
        exit($content);
    }

    /**
     * @param $id
     * 判断产品页面是否存在
     * @throws \think\Exception
     */
    public function product($id)
    {
        $this->entryCommon();
        if ($this->mainsite) {
            // 截取出id来
            $id = $this->subNameId($id, 'product');
            $filename = sprintf('product/%s.html', $id);
            if (file_exists($filename)) {
                exit(file_get_contents($filename));
            } else {
                exit(file_get_contents('index.html'));
            }
        }
        //
        list($template, $data) = (new Detailstatic())->product_detailinfo(str_replace('product', '', $id));
        $content = Common::Debug((new View())->fetch($template,
            $data
        ), $data);
        exit($content);
    }

    /**
     * 相关详情菜单的信息 该部分实现是利用 thinkphp module not exists: 异常捕获处理实现 因为详情菜单名称定义不一致
     * @access public
     */
    public function detailMenu($filename)
    {
        $this->entryCommon();
        if ($this->mainsite) {
            $filepath = $this->detailmenupath . $filename;
            if (file_exists($filepath)) {
                exit(file_get_contents($filepath));
            }
        }
        $filename = substr($filename, 0, strpos($filename, '.'));
        // 需要根据$filename 取出 menu 的信息
        $menu = Cache::remember('detailmenu' . $filename . 'menu' . $this->suffix, function () use ($filename) {
            $menu = (new \app\tool\model\Menu)->Where(['flag' => 1, 'node_id' => $this->node_id, 'generate_name' => $filename])->find();
            return $menu;
        });
        $content = Cache::remember('detailmenu' . $filename . 'content' . $this->suffix, function () use ($menu) {
            return (new Detailmenupagestatic)->getContent($menu);
        });
        exit($content);
    }


    /**
     * 区域信息
     * @access public
     * @throws \think\Exception
     */
    public function district()
    {
        //伪静态实现
        $commontool = new Commontool();
        // 泛站 相关列表页面展现
        $commontool->tag = 'district';
        $assign_data = $commontool->getEssentialElement();
        $data = [
            'd' => $assign_data
        ];
        if (!file_exists($this->districttemplate)) {
            //如果文件不存在的话
            $content = Common::Debug((new View())->fetch($this->defaultdistricttemplate,
                $data
            ), $data);
        } else {
            $content = Common::Debug((new View())->fetch($this->districttemplate,
                $data
            ), $data);
        }
        return $content;
    }


}
