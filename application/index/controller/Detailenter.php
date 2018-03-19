<?php

namespace app\index\controller;

use app\common\controller\EntryCommon;
use app\tool\controller\Indexstatic;

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
        if ($this->mainsite) {
            // 主站相关
            $filename = $this->detailmenupath . 'index.html';
            $this->entryCommon();
            exit(file_get_contents($filename));
        }
        //分站相关
        $index = new Indexstatic($this->mainsite, $this->district_id, $this->district_name, $this->suffix);
        return $index->indexstaticdata();
    }


    /**
     * @param $id
     * 判断文章页面是否存在
     */
    public function article($id)
    {
        $filename = sprintf('article/%s.html', $id);
        $this->entryCommon();
        if (file_exists($filename)) {
            exit(file_get_contents($filename));
        } else {
            //如果不存在的话  跳转到首页
            exit(file_get_contents('index.html'));
        }
    }

    /**
     * @param $id
     * 判断问答页面是否存在
     */
    public function question($id)
    {
        $filename = sprintf('question/%s.html', $id);
        $this->entryCommon();
        if (file_exists($filename)) {
            exit(file_get_contents($filename));
        } else {
            exit(file_get_contents('index.html'));
        }
    }

    /**
     * @param $id
     * 判断产品页面是否存在
     */
    public function product($id)
    {
        $filename = sprintf('product/%s.html', $id);
        $this->entryCommon();
        if (file_exists($filename)) {
            exit(file_get_contents($filename));
        } else {
            exit(file_get_contents('index.html'));
        }
    }

    /**
     * 相关详情菜单的信息 该部分实现是利用 thinkphp module not exists: 异常捕获处理实现 因为详情菜单名称定义不一致
     * @access public
     */
    public function detailMenu($filename)
    {
        if ($this->mainsite) {
            $filepath = $this->detailmenupath . $filename;
            if (file_exists($filepath)) {
                $this->entryCommon();
                exit(file_get_contents($filepath));
            }
        }
        $this->entryCommon();
        // 去请求数据 返回详情型菜单
        print_r($this->suffix);
        print_r($this->district_id);
        print_r($this->district_name);
        echo '非主站';
        exit;
    }

}
