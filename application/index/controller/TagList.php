<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 18-1-5
 * Time: 上午9:48
 */

namespace app\index\controller;

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
        $key = "articletaglist_{$tag_id}_{$currentpage}";
        $data = Cache::remember($key, function () use ($tag_id, $tag_name, $currentpage) {
            return $this->generateArticleList($tag_id, $tag_name, $currentpage);
        }, 0);
        Cache::tag(self::$clearableCacheTag, [$key]);
        return $this->Debug((new View())->fetch($template,
            $data
        ), $data);
    }


    /**
     * 生成tag文章列表
     * @access public
     * @param $tag_id
     * @param $tag_name
     * @param $currentpage
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @throws \throwable
     */
    public function generateArticleList($tag_id, $tag_name, $currentpage)
    {
        $listsize = 10;
        //当前栏目的分类
        //获取列表页面必须的元素
        $assign_data = $this->commontool->getEssentialElement($tag_name, 'taglist');
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        //查询出来已经静态化到的地方
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        //需要获取到当前分类下的所有二级目录
        $typeid_str = implode(',', array_keys($article_typearr));
        list($where, $articlemax_id) = $this->commontool->getArticleQueryWhere();
        $where .= " and tags like '%,$tag_id,%'";
        $where = sprintf($where, $typeid_str);
        $article = (new \app\index\model\Article())->order(['sort' => 'desc', 'id' => 'desc'])->field($this->commontool->articleListField)->where($where)
            ->paginate($listsize, false, [
                'path' => url('/tag', '', '') . "/{$tag_id}_p[PAGE].html",
                'page' => $currentpage
            ]);
        $this->commontool->formatArticleList($article, $article_typearr);
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
     * @param $siteinfo
     * @param $tag_id
     * @param $tag_name
     * @param $currentpage
     * @return string
     * @throws \Exception
     */
    public function questionList($siteinfo, $tag_id, $tag_name, $currentpage)
    {
        // 从缓存中获取数据
        $template = $this->getTagTemplate('question');
        if (!$this->fileExists($template)) {
            exit('文章标签模板不存在');
        }
        $key = "questiontaglist_{$tag_id}_{$currentpage}{$this->suffix}";
        $data = Cache::remember($key, function () use ($tag_id, $tag_name, $siteinfo, $currentpage) {
            return $this->generateQuestionList($tag_id, $tag_name, $siteinfo, $currentpage);
        }, 0);
        Cache::tag(self::$clearableCacheTag, [$key]);
        return $this->Debug((new View())->fetch($template,
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
     * @throws \throwable
     */
    public function generateQuestionList($tag_id, $tag_name, $currentpage)
    {
        $listsize = 10;
        //当前栏目的分类
        //获取列表页面必须的元素
        $assign_data = $this->commontool->getEssentialElement($tag_name, $this->currenturl);
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        //需要获取到当前分类下的所有二级目录
        $typeid_str = implode(',', array_keys($question_typearr));
        list($where, $questionmax_id) = $this->commontool->getQuestionQueryWhere();
        $where .= " and tags like '%,$tag_id,%'";
        $where = sprintf($where, $typeid_str);
        $question = Question::order(['sort' => 'desc', 'id' => 'desc'])->field($this->commontool->questionListField)->where($where)
            ->paginate($listsize, false, [
                'path' => url('/tag', '', '') . "/{$tag_id}_p[PAGE].html",
                'page' => $currentpage
            ]);
        $this->commontool->formatQuestionList($question, $question_typearr);
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
        $key = "producttaglist_{$tag_id}_{$currentpage}";
        $data = Cache::remember($key, function () use ($tag_id, $tag_name, $currentpage) {
            return $this->generateProductList($tag_id, $tag_name, $currentpage);
        }, 0);
        Cache::tag(self::$clearableCacheTag, [$key]);
        return $this->Debug((new View())->fetch($template,
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
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @throws \throwable
     */
    public function generateProductList($tag_id, $tag_name, $currentpage)
    {
        $listsize = 10;
        //当前栏目的分类
        //获取列表页面必须的元素
        $assign_data = $this->commontool->getEssentialElement($tag_name, $this->currenturl);
        list($type_aliasarr, $typeid_arr) = $this->commontool->getTypeIdInfo();
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        //需要获取到当前分类下的所有二级目录
        $typeid_str = implode(',', array_keys($product_typearr));
        list($where, $productmax_id) = $this->commontool->getProductQueryWhere();
        $where .= " and tags like '%,$tag_id,%'";
        $where = sprintf($where, $typeid_str);
        $product = (new Product())->order(['sort' => 'desc', 'id' => 'desc'])->field($this->commontool->productListField)->where($where)
            ->paginate($listsize, false, [
                'path' => url('/tag', '', '') . "/{$tag_id}_p[PAGE].html",
                'page' => $currentpage
            ]);
        $this->commontool->formatProductList($product, $product_typearr);
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