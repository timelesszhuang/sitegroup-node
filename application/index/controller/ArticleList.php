<?php

namespace app\index\controller;

use app\index\model\ArticleSyncCount;
use app\index\model\Useragent;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use app\common\controller\Common;
use think\View;
use app\tool\controller\FileExistsTraits;

/**
 * 文章列表相关操作 列表伪静态
 * 栏目下的文章 相关操作
 */
class ArticleList extends Common
{
    use FileExistsTraits;

    use SpiderComefrom;

    /**
     * 首页列表
     * @access public
     * @todo 需要考虑一下  文章列表 中列出来的文章需要从  sync_count 表中获取
     */
    public function index($id, $currentpage = 1)
    {
        $templatepath = 'template/articlelist.html';
        //判断模板是否存在
        if (!$this->fileExists($templatepath)) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        //爬虫来源 统计
        $this->spidercomefrom($siteinfo);
        // 从缓存中获取数据
        $assign_data=Cache::remember("articlelist".$id,function() use($id,$siteinfo,$currentpage){
            return $this->generateArticleList($id,$siteinfo,$currentpage);
        },0);
        //file_put_contents('log/questionlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return (new View())->fetch($templatepath,
            [
                'd' => $assign_data
            ]
        );
    }

}
