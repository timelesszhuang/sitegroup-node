<?php

namespace app\index\controller;

use app\common\controller\Common;
use app\common\controller\EntryCommon;
use app\tool\controller\Activitystatic;
use app\tool\controller\Commontool;
use app\tool\controller\Detailmenupagestatic;
use app\tool\controller\Detailstatic;
use app\tool\controller\Indexstatic;
use think\Cache;
use think\View;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 * @todo 需要添加修改的功能
 * 1、crontab 静态化请求问题  1、更新数据库中已经静态化到的地方。  2、请求清除缓存。
 * 2、手动请求静态化问题。 比如请求一次  静态化过程需要  1、更新数据库中已经静态化到的地方。  2、请求清除缓存。
 */
class Detailenter extends EntryCommon
{

    /**
     * 首页入口
     */
    public function index()
    {
        $this->entryCommon();
        //分站相关
        $index = new Indexstatic();
        return $index->indexstaticdata();
    }


    /**
     * @param $id
     * 判断文章页面是否存在
     * @return string
     */
    public function article($id)
    {
        $type = 'article';
        $id = $this->subNameId($id, $type);
        // 子站相关 可以使用预览部分的相关功能
        return Cache::remember($this->suffix . $type . $id, function () use ($id) {
            list($template, $data) = (new Detailstatic())->article_detailinfo($id);
            $content = Common::Debug((new View())->fetch($template,
                $data
            ), $data);
            return $content;
        });
    }

    /**
     * @param $id
     * 判断问答页面是否存在
     * @return string
     * @throws \Exception
     */
    public function question($id)
    {
        $type = 'question';
        $this->entryCommon();
        $id = $this->subNameId($id, $type);
        return Cache::remember($this->suffix . $type . $id, function () use ($id) {
            // 子站相关 可以使用预览部分的相关功能
            list($template, $data) = (new Detailstatic())->question_detailinfo($id);
            return Common::Debug((new View())->fetch($template,
                $data
            ), $data);
        });
    }

    /**
     * @param $id
     * 判断产品页面是否存在
     * @return string
     * @throws \Exception
     */
    public function product($id)
    {
        $type = 'product';
        $this->entryCommon();
        $id = $this->subNameId($id, $type);
        return Cache::remember($this->suffix . $type . $id, function () use ($id) {
            list($template, $data) = (new Detailstatic())->product_detailinfo($id);
            return Common::Debug((new View())->fetch($template,
                $data
            ), $data);
        });
    }

    /**
     * 获取伪静态访问地址
     * @access public
     * @param $id
     * @return string
     * @throws \Exception
     */
    public function activity($id)
    {
        $type = 'activity';
        $this->entryCommon();
        $id = $this->subNameId($id, $type);
        return Cache::remember($this->suffix . $type . $id, function () use ($id) {
            list($template, $data) = (new Activitystatic())->getacticitycontent($id);
            return Common::Debug((new View())->fetch($template,
                $data
            ), $data);
        });
    }


    /**
     * 相关详情菜单的信息 该部分实现是利用 thinkphp module not exists: 异常捕获处理实现 因为详情菜单名称定义不一致
     * @access public
     * @param $filename
     * @return mixed
     */
    public function detailMenu($filename)
    {
        $this->entryCommon();
        $filename = substr($filename, 0, strpos($filename, '.'));
        // 需要根据$filename 取出 menu 的信息
        $menu = Cache::remember('detailmenu' . $filename . 'menu' . $this->suffix, function () use ($filename) {
            $menu = (new \app\tool\model\Menu)->Where(['flag' => 1, 'node_id' => $this->node_id, 'generate_name' => $filename])->find();
            return $menu;
        });
        $content = Cache::remember('detailmenu' . $filename . 'content' . $this->suffix, function () use ($menu) {
            return (new Detailmenupagestatic)->getContent($menu);
        });
        return $content;
    }


    /**
     * 区域信息
     * @access public
     * @return string
     * @throws \Exception
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
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
