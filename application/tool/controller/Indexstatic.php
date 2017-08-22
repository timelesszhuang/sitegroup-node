<?php

namespace app\tool\controller;

use app\common\controller\Common;
use think\View;

/**
 * 首页静态化
 * 执行首页静态化相关操作
 */
class Indexstatic extends Common
{
    use FileExistsTraits;

    /**
     * 首恶静态化
     * @access public
     */
    public function index()
    {
        // 判断模板是否存在
        if (!$this->fileExists('template/index.html')) {
            return;
        }
        //  获取首页生成需要的资源
        //  关键词
        //  栏目url  展现以下已经在数据库
        //  文章 或者 问答
        $assign_data = Commontool::getEssentialElement('index');
        //file_put_contents('log/index.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //还需要 存储在数据库中 相关数据
        //页面中还需要填写隐藏的 表单 node_id site_id
        $content = (new View())->fetch('template/index.html',
            [
                'd' => $assign_data
            ]
        );
        if (file_put_contents('index.html', $content) === 'false') {
            file_put_contents('log/index.txt', $this->separator . date('Y-m-d H:i:s') . '首页静态化写入失败。' . $this->separator, FILE_APPEND);
            return false;
        }
        return true;
    }


}
