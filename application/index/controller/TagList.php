<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 18-1-5
 * Time: 上午9:48
 */

namespace app\index\controller;

use app\common\controller\Common;
use app\common\controller\EntryCommon;
use app\index\model\Product;
use app\index\model\Question;
use app\tool\controller\Commontool;
use app\tool\controller\Site;
use think\Cache;
use think\View;

class TagList extends EntryCommon
{


    //公共操作对象
    public $commontool;

    public function __construct()
    {
        parent::__construct();
        $this->commontool = new Commontool();
        $this->commontool->tag = 'tag';
    }


    /**
     * @access public
     */
    public function tag($id)
    {
        //每一个node下的菜单的英文名不能包含重复的值
        //根据_ 来分割 第一个参数表示 菜单的id_t文章分类的typeid_p页码id.html
        list($tag_id, $tag_name, $currentpage, $type) = $this->analyseTagParams($id);
        if (!$type) {
            exit('没有找到相关的标签。');
        }
        //爬虫来源 统计
        $siteinfo = Site::getSiteInfo();
        $this->entryCommon();
        switch ($type) {
            case 'article':
                return $this->articleList($tag_id, $tag_name, $currentpage);
                break;
            case 'product':
                return $this->productList($tag_id, $tag_name, $currentpage);
                break;
            case 'question':
                return $this->questionList($tag_id, $tag_name, $currentpage);
                break;
        }
    }

    /**
     * 获取tag文章列表
     * @access public
     */
    public function articleList($tag_id, $tag_name, $currentpage)
    {
        // 从缓存中获取数据
        $template = $this->getTagTemplate('article');
        if (!$this->fileExists($template)) {
            exit('文章标签模板不存在');
        }
        $data = Cache::remember("articletaglist_{$tag_id}_{$currentpage}", function () use ($tag_id, $tag_name, $currentpage) {
            return $this->generateArticleList($tag_id, $tag_name, $currentpage);
        }, 0);
        return Common::Debug((new View())->fetch($template,
            $data
        ), $data);
    }


    /**
     * 生成tag文章列表
     * @access public
     * @param $tag_id
     * @param $tag_name
     * @param $siteinfo
     * @param $currentpage
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function generateArticleList($tag_id, $tag_name, $currentpage)
    {
        $listsize = 10;
        //当前栏目的分类
        //获取列表页面必须的元素
        $assign_data = $this->commontool->getEssentialElement($tag_name, 'taglist');
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        //查询出来已经静态化到的地方
        $sync_info = $this->commontool->getDbArticleListId();
        $articlemax_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $article = [];
        //需要获取到当前分类下的所有二级目录
        if ($articlemax_id) {
            $typeid_str = implode(',', array_keys($article_typearr));
            $where = "id <=$articlemax_id and node_id={$this->node_id} and tags like '%,$tag_id,%' and articletype_id in ($typeid_str)";
            //获取当前type_id的文章
            if (!$this->mainsite) {
                $where .= ' and stations = "10"';
            }
            $article = (new \app\index\model\Article())->order('id', "desc")->field($this->commontool->articleListField)->where($where)
                ->paginate($listsize, false, [
                    'path' => url('/tag', '', '') . "/{$tag_id}_p[PAGE].html",
                    'page' => $currentpage
                ]);
            $this->commontool->formatArticleList($article, $article_typearr);
        }
        return [
            'd' => $assign_data,
            //子集的数据也需要展现出来
            'list' => $article,
            //tag name
            'tag' => [
                'text' => $tag_name,
                'href' => $this->currenturl
            ]
        ];
    }


    /**
     * 获取tag产品列表
     * @access public
     * @throws \think\Exception
     */
    public function questionList($siteinfo, $tag_id, $tag_name, $currentpage)
    {
        // 从缓存中获取数据
        $template = $this->getTagTemplate('question');
        if (!$this->fileExists($template)) {
            exit('文章标签模板不存在');
        }
        $data = Cache::remember("questiontaglist_{$tag_id}_{$currentpage}{$this->suffix}", function () use ($tag_id, $tag_name, $siteinfo, $currentpage) {
            return $this->generateQuestionList($tag_id, $tag_name, $siteinfo, $currentpage);
        }, 0);
        return Common::Debug((new View())->fetch($template,
            $data
        ), $data);
    }


    /**
     * 生成tag产品列表
     * @access public
     * @param $tag_id
     * @param $tag_name
     * @param $currentpage
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function generateQuestionList($tag_id, $tag_name, $currentpage)
    {
        $listsize = 10;
        //当前栏目的分类
        //获取列表页面必须的元素
        $assign_data = $this->commontool->getEssentialElement($tag_name, $this->currenturl);
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        //查询出来已经静态化到的地方
        $sync_info = $this->commontool->getDbArticleListId();
        $questionmax_id = array_key_exists('question', $sync_info) ? $sync_info['question'] : 0;
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        $question = [];
        //需要获取到当前分类下的所有二级目录
        if ($questionmax_id) {
            $typeid_str = implode(',', array_keys($question_typearr));
            $where = "id <={$questionmax_id} and node_id={$this->node_id} and type_id in ($typeid_str) and tags like '%,$tag_id,%'";
            if (!$this->mainsite) {
                $where .= ' and stations = "10"';
            }
            $question = Question::order('id', "desc")->field($this->commontool->questionListField)->where($where)
                ->paginate($listsize, false, [
                    'path' => url('/tag', '', '') . "/{$tag_id}_p[PAGE].html",
                    'page' => $currentpage
                ]);
            $this->commontool->formatQuestionList($question, $question_typearr);
        }
        return [
            'd' => $assign_data,
            //子集的数据也需要展现出来
            'list' => $question,
            //tag name
            'tag' => [
                'text' => $tag_name,
                'href' => $this->currenturl
            ]
        ];
    }


    /**
     * 获取tag产品列表
     * @access public
     */
    public function productList($tag_id, $tag_name, $currentpage)
    {
        // 从缓存中获取数据
        $template = $this->getTagTemplate('question');
        if (!$this->fileExists($template)) {
            exit('文章标签模板不存在');
        }
        $data = Cache::remember("producttaglist_{$tag_id}_{$currentpage}", function () use ($tag_id, $tag_name, $currentpage) {
            return $this->generateProductList($tag_id, $tag_name, $currentpage);
        }, 0);
        return Common::Debug((new View())->fetch($template,
            $data
        ), $data);
    }


    /**
     * 生成tag产品列表
     * @access public
     * @param $tag_id
     * @param $tag_name
     * @param $currentpage
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function generateProductList($tag_id, $tag_name, $currentpage)
    {
        $listsize = 10;
        //当前栏目的分类
        //获取列表页面必须的元素
        $assign_data = $this->commontool->getEssentialElement($tag_name, $this->currenturl);
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        //查询出来已经静态化到的地方
        $sync_info = $this->commontool->getDbArticleListId();
        $productmax_id = array_key_exists('product', $sync_info) ? $sync_info['product'] : 0;
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $product = [];
        //需要获取到当前分类下的所有二级目录
        if ($productmax_id) {
            $typeid_str = implode(',', array_keys($product_typearr));
            $where = "id <={$productmax_id} and node_id={$this->node_id} and type_id in ($typeid_str) and tags like '%,$tag_id,%'";
            if (!$this->mainsite) {
                $where .= ' and stations = "10"';
            }
            $product = (new Product())->order('id', "desc")->field($this->commontool->productListField)->where($where)
                ->paginate($listsize, false, [
                    'path' => url('/tag', '', '') . "/{$tag_id}_p[PAGE].html",
                    'page' => $currentpage
                ]);
            $this->commontool->formatProductList($product, $product_typearr);
        }
        return [
            'd' => $assign_data,
            //子集的数据也需要展现出来
            'list' => $product,
            //tag name
            'tag' => [
                'text' => $tag_name,
                'href' => $this->currenturl
            ]
        ];
    }


}