<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-8-28
 * Time: 上午11:35
 */

namespace app\index\controller;


use app\index\model\ArticleSyncCount;
use app\tool\controller\Commontool;
use app\tool\controller\FileExistsTraits;
use app\tool\controller\Site;
use think\Cache;
use think\View;

class ProductList
{
    use FileExistsTraits;
    use SpiderComefrom;

    /**
     * 首页列表
     * @access public
     */
    public function index($id, $currentpage=1)
    {
        $templatelist = 'template/productlist.html';
        //判断模板是否存在
        if (!$this->fileExists($templatelist)) {
            return;
        }
        $siteinfo = Site::getSiteInfo();
        $this->spidercomefrom($siteinfo);
        // 从缓存中获取数据
        $assign_data=Cache::remember("productlist".$id,function() use($id,$siteinfo,$currentpage){
            return $this->generateProductList($id,$siteinfo,$currentpage);
        },0);

        //file_put_contents('log/productlist.txt', $this->separator . date('Y-m-d H:i:s') . print_r($assign_data, true) . $this->separator, FILE_APPEND);
        //页面中还需要填写隐藏的 表单 node_id site_id
        return (new View())->fetch($templatelist,
            [
                'd' => $assign_data
            ]
        );
    }


}