<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\index\model\Articletype;
use app\index\model\Producttype;
use app\index\model\QuestionType;
use app\tool\model\Activity;
use think\Cache;
use think\Config;
use think\Db;


/**
 * 公共的相关操作的 工具
 */
class Commontool extends Common
{

    //各个列表的路径规则
    private $articleListPath = '/articlelist/%s.html';
    // 文章列表相关 需要
    public $listarticleaccesspath = '/article/article%s.html';
    private $productListPath = '/productlist/%s.html';
    //产品列表相关
    public $listproductaccesspath = '/product/product%s.html';
    private $questionListPath = '/questionlist/%s.html';
    //问答列表相关
    public $listquestionaccesspath = '/question/question%s.html';
    //文章问答产品的tag 列表样式 需要有分页
    public $taglist = '/tag/%s.html';
    public $articleListField = 'id,flag,title,title_color,articletype_name,articletype_id,thumbnails,thumbnails_name,summary,tags,create_time';
    public $questionListField = 'id,flag,question,type_id,type_name,tags,create_time';
    public $productListField = 'id,flag,name,image_name,sn,payway,type_id,type_name,summary,tags,field1,field2,field3,field4,create_time';
    //  h 头条 c 推荐 b 加粗 a 特荐 f 幻灯
    public $flag = ['h' => '头条', 'c' => '推荐', 'b' => '加粗', 'a' => '特荐', 'f' => '幻灯'];

    public $tag = 'index';

    /**
     * 清除缓存 信息
     * @access public
     */
    public function clearCache()
    {
        if (Cache::clear()) {
            exit(['status' => 'success', 'msg' => '清除缓存成功。']);
        }
        exit(['status' => 'failed', 'msg' => '清除缓存失败。']);
    }

    /**
     * 获取手机站的域名 跟 跳转的js 存储在缓存中
     * @access public
     */
    public function getMobileSiteInfo()
    {
        $siteinfo = $this->siteinfo;
        return Cache::remember('mobileinfo', function () use ($siteinfo) {
            //获取手机相关信息
            $m_site_url = '';
            //手机重定向的站点
            $m_redirect_code = '';
            //如果是pc 站的话 获取下对应的手机站
            if ($siteinfo['is_mobile'] == '10') {
                //是 pc
                $m_site_id = $siteinfo['m_site_id'];
                if ($m_site_id) {
                    //这个地方如果取出来不是手机站的话 需要给出提示错误
                    $m_site_url = Db::name('site')->where(['id' => $m_site_id])->field('url')->find()['url'];
                    $m_redirect_code = $this->getRedirectCode($m_site_url);
                }
            }
            return [$m_site_url, $m_redirect_code];
        });
    }

    /**
     * 获取网站内容相关tag列表
     * @access public
     * @param string $type
     * @return array|mixed
     */
    public function getTags($type = '')
    {
        $node_id = $this->node_id;
        $tags = Cache::remember('tags', function () use ($node_id) {
            return Db::name('tags')->where('node_id', $node_id)->field('id,name,type')->select();
        });
        $type_tags = [];
        $none_type_tags = [];
        foreach ($tags as $k => $v) {
            //需要格式化下tag的相关信息
            $ptype = $v['type'];
            $pertag = [
                'name' => $v['name'],
                'href' => sprintf($this->taglist, $v['id']),
                'type' => $ptype
            ];
            $type_tags[$ptype][$v['id']] = $pertag;
            $none_type_tags[$v['id']] = $pertag;
        }
        if (!$type) {
            //不区分分类的 tags
            return $none_type_tags;
        }
        if (array_key_exists($type, $type_tags)) {
            return $type_tags[$type];
        }
        return [];
    }


    /**
     * 获取其他的数据
     * @access public
     * @param $url
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRedirectCode($url)
    {
        $code = Db::name('system_config')->where(["name" => 'SYSTEM_PCTOM_REDIRECT_CODE'])->field('value')->find()['value'];
        $redirect_code = '';
        if ($code) {
            $redirect_code = str_replace('{{mobile_path}}', $url, $code);
        }
        return $redirect_code;
    }


    /**
     * 获取首页面的 tdk 相关数据 包含子站的tdk
     * @access public
     * @param $keyword_info
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function getIndexPageTDK($keyword_info)
    {
        $page_id = 'index';
        //默认首页的menu_id 为零
        $menu_id = 0;
        $page_name = '首页';
        $page_type = 'index';
        list($pageinfo_id, $akeyword_id, $change_status, $title, $keyword, $description, $childsite_title, $childsite_keyword, $childsite_description) = $this->getDbPageTDK($menu_id, $page_type);
        if (empty($title)) {
            //tdk 是空的 需要 重新 从关键词中获取
            //首页的title ： A类关键词1_A类关键词2_A类关键词3-公司名
            //     keyword  ： A类关键词1,A类关键词2,A类关键词3
            //     description : A类关键词拼接
            list($title, $keyword, $description) = $this->getIndexPageTool($keyword_info, true);
            //子站生成关键词也需要添加
            list($childsite_title, $childsite_keyword, $childsite_description) = $this->getIndexPageTool($keyword_info, false);
            Db::name('SitePageinfo')->insert([
                'menu_id' => 0,
                'site_id' => $this->site_id,
                'site_name' => $this->site_name,
                'page_type' => $page_type,
                'node_id' => $this->node_id,
                'page_id' => $page_id,
                'page_name' => $page_name,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'childsite_title' => $childsite_title,
                'childsite_keyword' => $childsite_keyword,
                'childsite_description' => $childsite_description,
                'akeyword_id' => 0,
                'pre_akeyword_id' => 0,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }

        if (empty($childsite_title) && !empty($title) && !$this->mainsite) {
            //为了向下兼容 添加子站功能之前不支持
            list($childsite_title, $childsite_keyword, $childsite_description) = $this->getIndexPageTool($keyword_info, false);
            Db::name('site_pageinfo')->where(['menu_id' => $menu_id, 'node_id' => $this->node_id, 'site_id' => $this->site_id, 'page_type' => $page_type])
                ->update([
                    'childsite_title' => $childsite_title,
                    'childsite_keyword' => $childsite_keyword,
                    'childsite_description' => $childsite_description,
                ]);
        }

        if ($this->mainsite) {
            return [$title, $keyword, $description];
        } else {
            // 非主站
            return str_replace($this->childsite_tdkplaceholder, $this->district_name, [$childsite_title, $childsite_keyword, $childsite_description]);
        }
    }


    /**
     * 获取首页页面工具
     * @access private
     * @param $keyword_info
     * @param bool $mainsite
     * @return array
     */
    private function getIndexPageTool($keyword_info, $mainsite = true)
    {
        $a_keywordname_arr = array_column($keyword_info, 'name');
        if ($mainsite) {
            $title = implode('_', $a_keywordname_arr) . '-' . $this->com_name;
            $keyword = implode(',', $a_keywordname_arr);
            $description = implode('，', $a_keywordname_arr) . '，' . $this->com_name;
        } else {
            $region_akeyword = [];
            foreach ($a_keywordname_arr as $v) {
                array_push($region_akeyword, $this->childsite_tdkplaceholder . $v);
            }
            $title = implode('_', $region_akeyword) . '-' . $this->com_name;
            $keyword = implode(',', $region_akeyword);
            $description = implode('，', $region_akeyword) . '，' . $this->com_name;
        }
        return [$title, $keyword, $description];
    }


    /**
     * 获取栏目页面的 tdk 相关数据
     * @param $keyword_info 关键词相关
     * @param $page_id 页面的id  比如 contactme
     * @param $page_name
     * @param $menu_id
     * @param $menu_name 栏目名
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function getMenuPageTDK($keyword_info, $page_id, $page_name, $menu_id, $menu_name)
    {
        $page_type = 'menu';
        //首先从数据库中获取当前站点设置的 tdk 等 相关数据
        /**
         * 表示页面是不是已经生成过
         * 如果没有生成  则随机选择一个A类 关键词 按照规则拼接关键词
         * 如果已经生成过 则需要比对现在的关键词是不是已经更换过 更换过的需要重新生成
         */
        list($pageinfo_id, $akeyword_id, $change_status, $title, $keyword, $description, $childsite_title, $childsite_keyword, $childsite_description) = $this->getDbPageTDK($menu_id, $page_type);
        if (empty($title)) {
            // 栏目页面的 TDK 获取 A类关键词随机选择
            //栏目页的 title：B类关键词多个_A类关键词1-栏目名
            //        keyword：B类关键词多个,A类关键词
            //        description:拼接一段就可以栏目名
            list($a_keyword_id, $title, $keyword, $description) = $this->getMenuPageTool($keyword_info, $menu_name, true);
            list($a_keyword_id, $childsite_title, $childsite_keyword, $childsite_description) = $this->getMenuPageTool($keyword_info, $menu_name, false);
            //选择好了 之后需要添加到数据库中 一定是新增
            Db::name('SitePageinfo')->insert([
                'menu_id' => $menu_id,
                'site_id' => $this->site_id,
                'site_name' => $this->site_name,
                'page_type' => $page_type,
                'node_id' => $this->node_id,
                'page_id' => $page_id,
                'page_name' => $page_name,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'childsite_title' => $childsite_title,
                'childsite_keyword' => $childsite_keyword,
                'childsite_description' => $childsite_description,
                'pre_akeyword_id' => $a_keyword_id,
                'akeyword_id' => $a_keyword_id,
                'create_time' => time(),
                'update_time' => time()
            ]);
        } elseif ($change_status) {
            //表示修改了 栏目对应的主关键词
            $a_child_info = [];
            foreach ($keyword_info as $k => $v) {
                if ($v['id'] == $akeyword_id) {
                    $a_child_info = $keyword_info[$k];
                }
            }
            if (empty($a_child_info)) {
                return ['', '', ''];
            }
            $a_name = $a_child_info['name'];
            $a_keyword_id = $a_child_info['id'];
            if (!array_key_exists('children', $a_child_info)) {
                return ['', '', ''];
            }
            $b_keyword_info = $a_child_info['children'];
            $b_keywordname_arr = array_column($b_keyword_info, 'name');
            $title = implode('_', $b_keywordname_arr) . '_' . $a_name . '-' . $menu_name;
            $keyword = implode(',', $b_keywordname_arr) . ',' . $a_name;
            $description = implode('，', $b_keywordname_arr) . '，' . $a_name . '，' . $menu_name;
            //生成子站相关的tdk
            $region_bkeyword = [];
            foreach ($b_keywordname_arr as $v) {
                array_push($region_bkeyword, $this->childsite_tdkplaceholder . $v);
            }
            $childsite_title = $menu_name . '-' . implode('_', $region_bkeyword) . '_' . $a_name;
            $childsite_keyword = $menu_name . ',' . implode(',', $region_bkeyword) . ',' . $a_name;
            $childsite_description = $menu_name . '，' . implode('，', $region_bkeyword) . '，' . $a_name;
            Db::name('SitePageinfo')->update([
                'id' => $pageinfo_id,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'childsite_title' => $childsite_title,
                'childsite_keyword' => $childsite_keyword,
                'childsite_description' => $childsite_description,
                'pre_akeyword_id' => $a_keyword_id
            ]);
        }
        //子站的标题为空 且 父站的title 不为空 且当前当文的为子站
        if (empty($childsite_title) && !empty($title) && !$this->mainsite) {
            //为了向下兼容 添加子站功能之前不支持
            list($a_keyword_id, $childsite_title, $childsite_keyword, $childsite_description) = $this->getMenuPageTool($keyword_info, $menu_name, false);
            Db::name('site_pageinfo')->where(['menu_id' => $menu_id, 'node_id' => $this->node_id, 'site_id' => $this->site_id, 'page_type' => $page_type])
                ->update([
                    'childsite_title' => $childsite_title,
                    'childsite_keyword' => $childsite_keyword,
                    'childsite_description' => $childsite_description,
                ]);
        }
        if ($this->mainsite) {
            return [$title, $keyword, $description];
        } else {
            return str_replace($this->childsite_tdkplaceholder, $this->district_name, [$childsite_title, $childsite_keyword, $childsite_description]);
        }
    }

    /**
     * 获取首页页面工具
     * @access private
     * @param $keyword_info
     * @param $menu_name
     * @param bool $mainsite
     * @return array
     */
    private function getMenuPageTool($keyword_info, $menu_name, $mainsite = true)
    {
        $a_keyword_key = array_rand($keyword_info, 1);
        $a_child_info = $keyword_info[$a_keyword_key];
        $a_name = $a_child_info['name'];
        $a_keyword_id = $a_child_info['id'];
        if (!array_key_exists('children', $a_child_info)) {
            return [$a_keyword_id, '', '', ''];
        }
        if ($mainsite) {
            $b_keyword_info = $a_child_info['children'];
            $b_keywordname_arr = array_column($b_keyword_info, 'name');
            $title = $menu_name . '-' . implode('_', $b_keywordname_arr) . '_' . $a_name;
            $keyword = $menu_name . ',' . implode(',', $b_keywordname_arr) . ',' . $a_name;
            $description = $menu_name . '，' . implode('，', $b_keywordname_arr) . '，' . $a_name;
        } else {
            $b_keyword_info = $a_child_info['children'];
            $b_keywordname_arr = array_column($b_keyword_info, 'name');
            $region_bkeyword = [];
            foreach ($b_keywordname_arr as $v) {
                array_push($region_bkeyword, $this->childsite_tdkplaceholder . $v);
            }
            $title = $menu_name . '-' . implode('_', $region_bkeyword) . '_' . $a_name;
            $keyword = $menu_name . ',' . implode(',', $region_bkeyword) . ',' . $a_name;
            $description = $menu_name . '，' . implode('，', $region_bkeyword) . '，' . $a_name;
        }
        return [$a_keyword_id, $title, $keyword, $description];
    }


    /**
     * 详情页 的TDK 获取   需要有固定的关键词
     * @param $page_id 文章或者相关页面的id 　　
     * @param $keyword_info 关键词相关
     * @param $articletitle
     * @param $articlecontent
     * @param $keywords
     * @param $a_keyword_id  上级菜单选择的主关键词的id
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @todo 详情页不需要 存储在数据库中 TDK定死就行
     */
    public function getDetailPageTDK($page_id, $type, $keyword_info, $articletitle, $articlecontent, $keywords, $a_keyword_id)
    {
        //需要知道 栏目的关键词等
        //$keyword_info, $site_id, $node_id, $articletitle, $articlecontent
        // 详情页页面的 TDK 获取 A类关键词随机选择
        // 详情页的 title：C类关键词多个_A类关键词1-文章标题
        //        keyword：C类关键词多个,A类关键词
        //        description:拼接一段就可以栏目名
        list($id, $title, $keyword, $description, $childsite_title, $childsite_keyword, $childsite_description) = $this->getDbDetailPageTDK($page_id, $type);
        if (!$id) {
            //表示为空
            list($title, $keyword, $description, $childsite_title, $childsite_keyword, $childsite_description) = $this->getDetailPageTool($keyword_info, $articletitle, $articlecontent, $keywords, $a_keyword_id);
            //添加到数据库中
            Db::name('SiteDetailPageinfo')->insert([
                'page_id' => $page_id,
                'site_id' => $this->site_id,
                'site_name' => $this->site_name,
                'node_id' => $this->node_id,
                'type' => $type,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'childsite_title' => $childsite_title,
                'childsite_keyword' => $childsite_keyword,
                'childsite_description' => $childsite_description,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }
        if ($this->mainsite) {
            return [$title, $keyword, $description];
        }
        return str_replace($this->childsite_tdkplaceholder, $this->district_name, [$childsite_title, $childsite_keyword, $childsite_description]);
    }

    /**
     * 生成详情型的页面ｔｄｋ
     * @param $keyword_info
     * @param $articletitle
     * @param $articlecontent
     * @param $keywords
     * @param $a_keyword_id
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function getDetailPageTool($keyword_info, $articletitle, $articlecontent, $keywords, $a_keyword_id)
    {
        $a_child_info = [];
        foreach ($keyword_info as $k => $v) {
            if ($v['id'] == $a_keyword_id) {
                $a_child_info = $v['children'];
                break;
            }
        }
        // 该A类下 没有关键词 需要更换
        if (empty($a_child_info)) {
            //需要处理下 如果没有的话 怎么处理 也就是说该网站取消选择某个关键词
            $a_keyword_key = array_rand($keyword_info, 1);
            $new_a_keyword_id = $keyword_info[$a_keyword_key]['id'];
            //更新一下数据库中的页面的a类 关键词
            Db::name('SitePageinfo')->where([
                'site_id' => $this->site_id,
                'node_id' => $this->node_id,
                'akeyword_id' => $a_keyword_id,
            ])->update([
                'akeyword_id' => $new_a_keyword_id,
                'update_time' => time()
            ]);
            foreach ($keyword_info as $k => $v) {
                if ($v['id'] == $new_a_keyword_id) {
                    $a_child_info = $v['children'];
                    break;
                }
            }
        }
        //随机取 一个a类下的b类
        $b_child_info = $a_child_info[array_rand($a_child_info)];
        $c_keyword_arr = [];
        if (array_key_exists('children', $b_child_info)) {
            $c_child_info = $b_child_info['children'];
            $length = count($c_child_info);
            $randamcount = $length > 3 ? 3 : $length;
            $c_rand_key = array_rand($c_child_info, $randamcount);
            if (is_array($c_rand_key)) {
                foreach ($c_rand_key as $v) {
                    $c_keyword_arr[] = $c_child_info[$v];
                }
            } else {
                $c_keyword_arr[] = $c_child_info[$c_rand_key];
            }
        }
        $c_keywordname_arr = array_column($c_keyword_arr, 'name');
        $title = $articletitle . '-' . implode('_', $c_keywordname_arr);
        $keyword = $keywords ? $keywords : implode(',', $c_keywordname_arr);
        $region_ckeyword = [];
        foreach ($c_keywordname_arr as $v) {
            array_push($region_ckeyword, $this->childsite_tdkplaceholder . $v);
        }
        $childsite_title = $articletitle . '-' . implode('_', $region_ckeyword);
        $childsite_keyword = $keywords ? $keywords : implode(',', $region_ckeyword);
        $description = $articlecontent;
        return [$title, $keyword, $description, $childsite_title, $childsite_keyword, $description];
    }


    /**
     * 获取tag 相关信息
     * @access public
     * @param $keyword_info
     * @param $tag_name
     * @return array
     */
    public function getTaglistPageTDK($keyword_info, $tag_name)
    {
        $a_keywordname_arr = array_column($keyword_info, 'name');
        //根据站点的关键词来
        $title = $tag_name . '-' . implode('_', $a_keywordname_arr);
        $keyword = $tag_name . ',' . implode(',', $a_keywordname_arr);
        $description = $tag_name . '，' . implode('，', $a_keywordname_arr);
        //这个需要定死
        return [$title, $keyword, $description];
    }


    /**
     * 从数据库中获取页面的tdk相关信息
     * @access public
     * @param $menu_id
     * @param $page_type
     * @return array
     */
    public function getDbPageTDK($menu_id, $page_type)
    {
        $page_info = Cache::remember($menu_id . $page_type . $this->suffix, function () use ($menu_id, $page_type) {
            return Db::name('site_pageinfo')->where(['menu_id' => $menu_id, 'node_id' => $this->node_id, 'site_id' => $this->site_id, 'page_type' => $page_type])
                ->field('id,title,keyword,description,childsite_title,childsite_keyword,childsite_description,pre_akeyword_id,akeyword_id')->find();
        });

        $akeyword_changestatus = false;
        $akeyword_id = 0;
        if ($page_info) {
            if ($page_info['pre_akeyword_id'] != $page_info['akeyword_id']) {
                $akeyword_changestatus = true;
                $akeyword_id = $page_info['akeyword_id'];
            }
            // 需要向下兼容 已经有的 page_info
            return [$page_info['id'], $akeyword_id, $akeyword_changestatus, $page_info['title'], $page_info['keyword'], $page_info['description'], $page_info['childsite_title'], $page_info['childsite_keyword'], $page_info['childsite_description']];
        }
        return [0, $akeyword_id, $akeyword_changestatus, '', '', '', '', '', ''];
    }

    /**
     * 从数据库中获取页面的tdk相关信息 获取页面中的tdk
     * @access public
     * @param $page_id 页面的id
     * @param $type  product question article
     * @return array
     */
    public function getDbDetailPageTDK($page_id, $type)
    {
        $page_info = Cache::remember($page_id . $type . $this->suffix, function () use ($page_id, $type) {
            return Db::name('site_detail_pageinfo')->where(['page_id' => $page_id, 'node_id' => $this->node_id, 'site_id' => $this->site_id, 'type' => $type])
                ->field('id,title,keyword,description,childsite_title,childsite_keyword,childsite_description')->find();
        });
        if ($page_info) {
            return [$page_info['id'], $page_info['title'], $page_info['keyword'], $page_info['description'], $page_info['childsite_title'], $page_info['childsite_keyword'], $page_info['childsite_description']];
        }
        return [0, '', '', '', '', '', ''];
    }


    /**
     * 获取 文章列表 获取十条　文件名如 article1 　article2
     * @access public
     * @param $sync_info 该站点所有文章分类的 静态化状况
     * @param $typeid_arr
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getArticleList($sync_info, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        //随机取值
        //测试
        if (!($max_id && $article_typearr)) {
            return ['list' => [], 'more' => $more];
        }
        $articlelist = $this->getTypesArticleList($max_id, $article_typearr, $limit);
        //随机取值来生成静态页
        $rand_key = array_rand($article_typearr);
        $rand_type = $article_typearr[$rand_key];
        if ($more['href'] == '/') {
            $more = ['title' => $rand_type['menu_name'], 'href' => sprintf($this->articleListPath, $rand_type['menu_enname']), 'text' => '更多', 'menu_name' => $rand_type['menu_name'], 'type_name' => $rand_type['type_name']];
        }
        // 这个地方有问题
        return ['list' => $articlelist, 'more' => $more];
    }

    /**
     * 获取多个分类下的文章列表
     * @access public
     * @param $max_id
     * @param $article_typearr
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTypesArticleList($max_id, $article_typearr, $limit)
    {
        $typeid_str = implode(',', array_keys($article_typearr));
        $where = " `id`<= {$max_id} and articletype_id in ({$typeid_str})";
        if (!$this->mainsite) {
            // 子站显示文章
            $where .= ' and  stations ="10"';
        }
        $article = Db::name('Article')->where($where)->field($this->articleListField)->order('id desc')->limit($limit)->select();
        $this->formatArticleList($article, $article_typearr);
        return $article;
    }


    /**
     * 获取文章分类下的文章列表 比如 行业新闻直接调取分类下的id 调取的时候建议使用 array_key_exists
     * @access public
     * @param $sync_info
     * @param $type_aliasarr
     * @param $typeid_arr
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getArticleTypeList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_type_aliasarr = array_key_exists('article', $type_aliasarr) ? $type_aliasarr['article'] : [];
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $articlealias_list = [];
        foreach ($article_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $articlelist = $this->getTypeArticleList($type_id, $max_id, $article_typearr, $limit);
            $more = ['title' => $v['menu_name'], 'href' => sprintf($this->articleListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
            //组织数据
            $articlealias_list[$type_alias] = [
                'list' => $articlelist,
                'more' => $more,
                'menu_name' => $v['menu_name'],
                'type_name' => $v['type_name']
            ];
        }
        return $articlealias_list;
    }

    /**
     * 获取产品flag相关list
     * 最多取出10条
     * @param $sync_info
     * @param $type_aliasarr
     * @param $typeid_arr
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getArticleFlagList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('article', $sync_info) ? $sync_info['article'] : 0;
        $article_type_aliasarr = array_key_exists('article', $type_aliasarr) ? $type_aliasarr['article'] : [];
        $more = [];
        foreach ($article_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $more[] = ['title' => $v['menu_name'], 'href' => sprintf($this->articleListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
        }
        $article_typearr = array_key_exists('article', $typeid_arr) ? $typeid_arr['article'] : [];
        $article_flaglist = [];
        if (!($max_id && $article_typearr)) {
            // 表示有空的时候
            foreach ($this->flag as $flag => $name) {
                $article = [];
                //组织数据
                $article_flaglist[$flag] = [
                    'list' => $article,
                    'detail' => $name,
                    'more' => $more
                ];
            }
            return $article_flaglist;
        }
        $typeid_str = implode(',', array_keys($article_typearr));
        $where = " `id`<= {$max_id} and articletype_id in ({$typeid_str})";
        foreach ($this->flag as $flag => $name) {
            $whereflag = $where . " and flag like '%,$flag,%'";
            if (!$this->mainsite) {
                $whereflag .= ' and stations = "10"';
            }
            $article = Db::name('Article')->where($whereflag)->field($this->articleListField)->order('id desc')->limit($limit)->select();
            $this->formatArticleList($article, $article_typearr);
            //组织数据
            $article_flaglist[$flag] = [
                'list' => $article,
                // 所有相关 more
                'more' => $more,
                'name' => $name
            ];
        }
        return $article_flaglist;
    }


    /**
     * 获取单个分类下的文章列表
     * @access public
     * @param $type_id
     * @param $max_id
     * @param $article_typearr
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTypeArticleList($type_id, $max_id, $article_typearr, $limit)
    {
        $where = " `id`<= {$max_id} and articletype_id = {$type_id}";
        if (!$this->mainsite) {
            $where .= ' and stations = "10"';
        }
        //后期可以考虑置顶之类操作
        $article = Db::name('Article')->where($where)->field($this->articleListField)->order('id desc')->limit($limit)->select();
        $this->formatArticleList($article, $article_typearr);
        return $article;
    }


    /**
     * 根据取出来的文章list 格式化为指定的格式
     * @access public
     * @param $article
     * @param $article_typearr
     */
    public function formatArticleList(&$article, $article_typearr)
    {
        $type_tags = $this->getTags('article');
        foreach ($article as $k => $v) {
            //防止标题中带着% 好的引起程序问题
            $v['title'] = str_replace('%', '', $v['title']);
            $img_template = "<img src='%s' alt='{$v['title']}' title='{$v['title']}'>";
            //格式化标题的颜色
            $v['color_title'] = $v['title_color'] ? sprintf('<span style="color:%s">%s</span>', $v['title_color'], $v['title']) : $v['title'];
            unset($v['title_color']);
            if (strpos($v['flag'], 'b')) {
                $v['title'] = '<strong>' . $v['title'] . '</strong>';
                $v['color_title'] = '<strong>' . $v['color_title'] . '</strong>';
            }
            //默认缩略图的
            $src = '/templatestatic/default.jpg';
            $img = sprintf($img_template, $src);
            if (!empty($v["thumbnails_name"])) {
                //如果有本地图片则 为本地图片
                $src = "/images/" . $v['thumbnails_name'];
                $img = sprintf($img_template, $src);
            } else if (!empty($v["thumbnails"])) {
                $src = $v['thumbnails'];
                $img = sprintf($img_template, $v['thumbnails']);
            }
            $v['thumbnails_src'] = $src;
            $type = [];
            //列出当前文章分类来
            if (array_key_exists($v['articletype_id'], $article_typearr)) {
                $type = [
                    'name' => $v['articletype_name'],
                    'href' => $article_typearr[$v['articletype_id']]['href']
                ];
            }
            unset($v['articletype_id']);
            unset($v['articletype_name']);
            $v['href'] = sprintf($this->listarticleaccesspath, $v['id']);
            if (is_array($v)) {
                $v['create_time'] = date('Y-m-d', $v['create_time']);
            }
            $v['thumbnails'] = $img;
            $v['type'] = $type;
            $tags = [];
            if ($v['tags']) {
                $tag_arr = explode(',', $v['tags']);
                foreach ($tag_arr as $val) {
                    if (array_key_exists($val, $type_tags)) {
                        $tags[] = $type_tags[$val];
                    }
                }
            }
            $v['tags'] = $tags;
            $article[$k] = $v;
        }
    }


    /**
     * 获取 产品列表 获取十条产品
     * @access public
     * @param $sync_info 该站点所有文章分类的 静态化状况
     * @param $typeid_arr
     * @param int $limit
     * @return array
     */
    public function getProductList($sync_info, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('product', $sync_info) ? $sync_info['product'] : 0;
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        if (!($max_id && $product_typearr)) {
            return ['list' => [], 'more' => $more];
        }
        $productlist = $this->getTypesProductList($max_id, $product_typearr, $limit);
        //随机取值来生成静态页
        $rand_key = array_rand($product_typearr);
        $rand_type = $product_typearr[$rand_key];
        if ($more['href'] == '/') {
            $more = ['title' => $rand_type['menu_name'], 'href' => sprintf($this->productListPath, $rand_type['menu_enname']), 'text' => '更多'];
        }
        return ['list' => $productlist, 'more' => $more];
    }

    /**
     * 获取多个类型types 产品列表
     * @access public
     * @param $max_id
     * @param $product_typearr
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTypesProductList($max_id, $product_typearr, $limit)
    {
        $typeid_str = implode(',', array_keys($product_typearr));
        $where = " `type_id` in ($typeid_str) and `id`<= {$max_id}";
        if (!$this->mainsite) {
            $where .= ' and stations ="10"';
        }
        $product = Db::name('Product')->where($where)->field($this->productListField)->order('id desc')->limit($limit)->select();
        $this->formatProductList($product, $product_typearr);
        return $product;
    }


    /**
     * 获取产品分类下的文章列表 比如 行业新闻直接调取分类下的id 调取的时候建议使用 array_key_exists
     * @access public
     * @param $sync_info
     * @param $type_aliasarr
     * @param $typeid_arr
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getProductTypeList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('product', $sync_info) ? $sync_info['product'] : 0;
        $product_type_aliasarr = array_key_exists('product', $type_aliasarr) ? $type_aliasarr['product'] : [];
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $productalias_list = [];
        foreach ($product_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $productlist = $this->getTypeProductList($type_id, $max_id, $product_typearr, $limit);
            $more = ['title' => $v['menu_name'], 'href' => sprintf($this->productListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
            //组织数据
            $productalias_list[$type_alias] = [
                'list' => $productlist,
                'more' => $more,
                'menu_name' => $v['menu_name'],
                'type_name' => $v['type_name']
            ];
        }
        return $productalias_list;
    }

    /**
     * 获取产品flag 相关list
     * @access public
     * @param $sync_info
     * @param $type_aliasarr
     * @param $typeid_arr
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getProductFlagList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('product', $sync_info) ? $sync_info['product'] : 0;
        $product_type_aliasarr = array_key_exists('product', $type_aliasarr) ? $type_aliasarr['product'] : [];
        $more = [];
        foreach ($product_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $more[] = ['title' => $v['menu_name'], 'href' => sprintf($this->productListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
        }
        $product_typearr = array_key_exists('product', $typeid_arr) ? $typeid_arr['product'] : [];
        $product_flaglist = [];
        if (!($max_id && $product_typearr)) {
            // 表示有空的时候
            foreach ($this->flag as $flag => $name) {
                $product = [];
                //组织数据
                $product_flaglist[$flag] = [
                    'list' => $product,
                    'detail' => $name,
                    'more' => $more
                ];
            }
            return $product_flaglist;
        }
        $typeid_str = implode(',', array_keys($product_typearr));
        $where = " `id`<= {$max_id} and type_id in ({$typeid_str})";
        foreach ($this->flag as $flag => $name) {
            $whereflag = $where . " and flag like '%,$flag,%'";
            if (!$this->mainsite) {
                $whereflag .= ' and stations ="10"';
            }
            $product = Db::name('Product')->where($whereflag)->field($this->productListField)->order('id desc')->limit($limit)->select();
            $this->formatProductList($product, $product_typearr);
            //组织数据
            $product_flaglist[$flag] = [
                'list' => $product,
                'detail' => $name,
                'more' => $more
            ];
        }
        return $product_flaglist;
    }


    /**
     * 获取单个类型type 产品列表
     * @access public
     * @param $type_id
     * @param $max_id
     * @param $product_typearr
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTypeProductList($type_id, $max_id, $product_typearr, $limit)
    {
        $where = " `id`<= {$max_id} and type_id = {$type_id}";
        if (!$this->mainsite) {
            $where .= ' and stations ="10"';
        }
        //后期可以考虑置顶之类操作
        $product = Db::name('Product')->where($where)->field($this->productListField)->order('id desc')->limit($limit)->select();
        $this->formatProductList($product, $product_typearr);
        return $product;
    }


    /**
     * 格式化产品信息数据
     * @access private
     * @param $product
     * @param $product_typearr
     */
    public function formatProductList(&$product, $product_typearr)
    {
        $type_tags = $this->getTags('product');
        foreach ($product as $k => $v) {
            $src = "/images/" . $v['image_name'];
            $v['thumbnails_src'] = $src;
            $img = "<img src='{$src}' alt= '{$v['name']}'>";
            //列出当前文章分类来
            $type = [
                'name' => '',
                'href' => ''
            ];
            if (array_key_exists($v['type_id'], $product_typearr)) {
                $type = [
                    'name' => $v['type_name'],
                    'href' => $product_typearr[$v['type_id']]['href']
                ];
            }
            if (is_array($v)) {
                $v['create_time'] = date('Y-m-d', $v['create_time']);
            }
            if (strpos($v['flag'], 'b')) {
                $v['name'] = '<strong>' . $v['name'] . '</strong>';
            }
            unset($v['type_id']);
            unset($v['type_name']);
            unset($v['image_name']);
            $v['href'] = sprintf($this->listproductaccesspath, $v['id']);
            $v['thumbnails'] = $img;
            $v['type'] = $type;
            $tags = [];
            if ($v['tags']) {
                $tag_arr = explode(',', $v['tags']);
                foreach ($tag_arr as $val) {
                    if (array_key_exists($val, $type_tags)) {
                        $tags[] = $type_tags[$val];
                    }
                }
            }
            $v['tags'] = $tags;
            $product[$k] = $v;
        }
    }


    /**
     * 获取 问题列表 获取十条　文件名如 question1 　question2
     * @access public
     * @param $sync_info
     * @param $typeid_arr
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getQuestionList($sync_info, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('question', $sync_info) ? $sync_info['question'] : 0;
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        if (!($max_id && $question_typearr)) {
            return ['list' => [], 'more' => $more];
        }
        $questionlist = $this->getTypesQuestionList($max_id, $question_typearr, $limit);
        $rand_key = array_rand($question_typearr);
        $rand_type = $question_typearr[$rand_key];
        if ($more['href'] == '/') {
            $more = ['title' => $rand_type['menu_name'], 'href' => sprintf($this->questionListPath, $rand_type['menu_enname']), 'text' => '更多'];
        }
        return ['list' => $questionlist, 'more' => $more];
    }


    /**
     * 获取多个类型types 问答列表
     * @access public
     * @param $max_id
     * @param $question_typearr
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTypesQuestionList($max_id, $question_typearr, $limit)
    {
        $typeid_str = implode(',', array_keys($question_typearr));
        $where = "`type_id` in ($typeid_str)  and `id`<= {$max_id}";
        if (!$this->mainsite) {
            $where .= ' and stations ="10"';
        }
        $question = Db::name('Question')->where($where)->field($this->questionListField)->order('id desc')->limit($limit)->select();
        $this->formatQuestionList($question, $question_typearr);
        return $question;
    }


    /**
     * 获取问答分类的相关列表数据
     * @access public
     * @param $sync_info
     * @param $type_aliasarr
     * @param $typeid_arr
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getQuestionTypeList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('question', $sync_info) ? $sync_info['question'] : 0;
        $question_type_aliasarr = array_key_exists('question', $type_aliasarr) ? $type_aliasarr['question'] : [];
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        $questionalias_list = [];
        foreach ($question_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $questionlist = $this->getTypeQuestionList($type_id, $max_id, $question_typearr, $limit);
            $more = ['title' => $v['menu_name'], 'href' => sprintf($this->questionListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
            //组织数据
            $questionalias_list[$type_alias] = [
                'list' => $questionlist,
                'more' => $more,
                'menu_name' => $v['menu_name'],
                'type_name' => $v['type_name']
            ];
        }
        return $questionalias_list;
    }


    /**
     * 获取产品flag 相关list
     * @access public
     * @param $sync_info
     * @param $type_aliasarr
     * @param $typeid_arr
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getQuestionFlagList($sync_info, $type_aliasarr, $typeid_arr, $limit = 10)
    {
        $max_id = array_key_exists('question', $sync_info) ? $sync_info['question'] : 0;
        $question_type_aliasarr = array_key_exists('question', $type_aliasarr) ? $type_aliasarr['question'] : [];
        $questionalias_list = [];
        $more = [];
        foreach ($question_type_aliasarr as $type_alias => $v) {
            $type_id = $v['type_id'];
            $more[] = ['title' => $v['menu_name'], 'href' => sprintf($this->questionListPath, "{$v['menu_enname']}_t{$type_id}"), 'text' => '更多', 'menu_name' => $v['menu_name'], 'type_name' => $v['type_name']];
        }
        $question_typearr = array_key_exists('question', $typeid_arr) ? $typeid_arr['question'] : [];
        $question_flaglist = [];
        if (!($max_id && $question_typearr)) {
            // 表示有空的时候
            foreach ($this->flag as $flag => $name) {
                $question = [];
                //组织数据
                $question_flaglist[$flag] = [
                    'list' => $question,
                    'detail' => $name,
                    'more' => $more
                ];
            }
            return $question_flaglist;
        }
        $typeid_str = implode(',', array_keys($question_typearr));
        $where = " `id`<= {$max_id} and type_id in ({$typeid_str})";
        foreach ($this->flag as $flag => $name) {
            $whereflag = $where . " and flag like '%,$flag,%'";
            if (!$this->mainsite) {
                $whereflag .= ' and stations ="10"';
            }
            $question = Db::name('Question')->where($whereflag)->field($this->questionListField)->order('id desc')->limit($limit)->select();
            $this->formatQuestionList($question, $question_typearr);
            //组织数据
            $question_flaglist[$flag] = [
                'list' => $question,
                'detail' => $name,
                'more' => $more
            ];
        }
        return $questionalias_list;
    }


    /**
     * 获取单个类型type 问答列表
     * @access public
     * @param $type_id
     * @param $max_id
     * @param $question_typearr
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTypeQuestionList($type_id, $max_id, $question_typearr, $limit)
    {
        $where = " `id`<= {$max_id} and type_id = {$type_id}";
        //后期可以考虑置顶之类操作
        if (!$this->mainsite) {
            $where .= ' and stations ="10"';
        }
        $question = Db::name('Question')->where($where)->field($this->questionListField)->order('id desc')->limit($limit)->select();
        $this->formatQuestionList($question, $question_typearr);
        return $question;
    }

    /**
     * 根据问答分类格式化列表数据
     * @access private
     * @param $question
     * @param $question_typearr
     */
    public function formatQuestionList(&$question, $question_typearr)
    {
        $type_tags = $this->getTags('question');
        foreach ($question as $k => $v) {
            $type = [
                'name' => '',
                'href' => ''
            ];
            if (array_key_exists($v['type_id'], $question_typearr)) {
                $type = [
                    'name' => $v['type_name'],
                    'href' => $question_typearr[$v['type_id']]['href']
                ];
            }
            if (strpos($v['flag'], 'b')) {
                $v['question'] = '<strong>' . $v['question'] . '</strong>';
            }
            $v['href'] = sprintf($this->listquestionaccesspath, $v['id']);
            if (is_array($v)) {
                // model对象调用的时候会自动格式化
                $v['create_time'] = date('Y-m-d', $v['create_time']);
            }
            $v['type'] = $type;
            $tags = [];
            if ($v['tags']) {
                $tag_arr = explode(',', $v['tags']);
                foreach ($tag_arr as $val) {
                    if (array_key_exists($val, $type_tags)) {
                        $tags[] = $type_tags[$val];
                    }
                }
            }
            $v['tags'] = $tags;
            $question[$k] = $v;
        }
    }


    /**
     * 获取公共代码
     * @access public
     * @param $code_ids
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCommonCode($code_ids)
    {
        $code = Db::name('code')->where(['id' => ['in', array_filter(explode(',', $code_ids))]])->field('code,position')->select();
        $pre_head_code_list = [];
        $after_head_code_list = [];
        foreach ($code as $k => $v) {
            if ($v['position'] == 1) {
                $pre_head_code_list[] = $v['code'];
            } else {
                $after_head_code_list[] = $v['code'];
            }
        }
        return [$pre_head_code_list, $after_head_code_list];
    }


    /**
     * 获取分类中已经静态化到的地方 需要的文章列表  内容列表 问答列表
     * @access public
     * @return array
     */
    public function getDbArticleListId()
    {
        $site_id = $this->site_id;
        return Cache::remember('sync_info', function () use ($site_id) {
            //文章同步表中获取文章同步到的位置 需要考虑到 一个站点新建的时候会是空值
            $article_sync_info = Db::name('ArticleSyncCount')->where(['site_id' => $site_id])->field('type_name,count')->select();
            $article_sync_list = [];
            if ($article_sync_info) {
                foreach ($article_sync_info as $v) {
                    if (!array_key_exists($v['type_name'], $article_sync_list)) {
                        $article_sync_list[$v['type_name']] = $v['count'];
                    }
                }
            }
            return $article_sync_list;//文章同步表中获取文章同步到的位置 需要考虑到 一个站点新建的时候会是空值
        });
    }

    /**
     * 获取活动列表
     * @access public
     */
    public function getActivity()
    {
        $where["id"] = ['in', explode(',', $this->siteinfo['sync_id'])];
        $where["status"] = 10;
        $activity = Activity::where($where)->field('id,en_name,title,img_name,small_img_name,url,summary')->select();
        $activity_list = [];
        $activity_small_list = [];
        $activity_en_list = [];
        foreach ($activity as $k => $v) {
            $has_small = false;
            $activity = [];
            $activity['text'] = $v['title'];
            $activity['summary'] = $v['summary'];
            $activity['src'] = "/images/{$v['img_name']}";
            //默认小图不存在赋值大图
            $activity['smallsrc'] = "/images/{$v['img_name']}";
            if ($v['small_img_name']) {
                $has_small = true;
                $activity['smallsrc'] = "/images/{$v['small_img_name']}";
            }
            $activity['href'] = $v['url'] ?: "/activity/activity{$v['id']}.html";
            $activity_list[] = $activity;
            if ($has_small) {
                $activity_small_list[] = $activity;
            }
            $activity_en_list[$v['en_name']] = $activity;
        }
        return [$activity_list, $activity_small_list, $activity_en_list];
    }


    /**
     * 获取版本控制　软件
     * ＠access public
     */
    public function getSiteCopyright()
    {
        //返回copyright
        return '© 2015-' . date('Y') . '  ' . $this->siteinfo['com_name'] . ' All Rights Reserved.';
    }


    /**
     * 获取网站powerby相关 信息
     * @access private
     */
    private function getSitePowerBy()
    {
        return ['text' => '技术支持：北京易至信科技有限公司', 'href' => 'http://www.salesman.cc'];
    }


    /**
     * 执行ping百度操作
     * @return string
     */
    public function getJsPingBaidu()
    {
        return <<<code
<script>
(function(){
    var bp = document.createElement('script');
    var curProtocol = window.location.protocol.split(':')[0];
    if (curProtocol === 'https') {
        bp.src = 'https://zz.bd.com/linksubmit/push.js';
    }
    else {
        bp.src = 'http://push.zhanzhang.baidu.com/push.js';
    }
    var s = document.getElementsByTagName("script")[0];
    s.parentNode.insertBefore(bp, s);
})();
</script>
code;
    }


    /**
     * 获取站点的js 公共代码
     * @access public
     */
    public function getSiteJsCode()
    {
        $siteinfo = $this->siteinfo;
        //获取公共代码
        list($pre_head_jscode, $after_head_jscode) = $this->getCommonCode($siteinfo['public_code']);
        //head前后的代码
        $before_head = $siteinfo['before_header_jscode'];
        $after_head = $siteinfo['other_jscode'];
        if ($before_head) {
            array_push($pre_head_jscode, $before_head);
        }
        if ($after_head) {
            array_push($after_head_jscode, $after_head);
        }
        $pre_head_js = '';
        foreach ($pre_head_jscode as $v) {
            $pre_head_js = $pre_head_js . $v;
        }
        $after_head_js = '';
        foreach ($after_head_jscode as $v) {
            $after_head_js = $after_head_js . $v;
        }
        $after_head_js = $after_head_js . $this->getJsPingBaidu();
        return [$pre_head_js, $after_head_js];
    }

    /**
     * 获取备案信息
     * @access public
     */
    public function getBeianInfo()
    {
        //公司备案
        $beian_link = 'http://www.miitbeian.gov.cn';
        $beian = ['beian_num' => '', 'link' => $beian_link];
        $domain_id = $this->siteinfo['domain_id'];
        if ($domain_id) {
            //这个地方应该也添加缓存
            $domain_info = Cache::remember('domain_info', function () use ($domain_id) {
                return Db::name('domain')->where('id', $domain_id)->find();
            });
            if ($domain_info) {
                $beian_num = $domain_info['filing_num'];
                $beian = ['text' => $beian_num, 'href' => $beian_link];
            }
        }
        return $beian;
    }

    /**
     * 获取站点的logo 相关信息
     * @access private
     * @return string
     */
    private function getSiteLogo()
    {
        $id = $this->siteinfo['sitelogo_id'];
        $site_name = $this->siteinfo['site_name'];
        if (!$id) {
            return $site_name;
        }
        $site_id = $this->siteinfo['id'];
        $site_logoinfo = Cache::remember('sitelogoinfo', function () use ($id) {
            return Db::name('site_logo')->where('id', $id)->find();
        });
        return Cache::remember('sitelogo', function () use ($site_logoinfo, $site_id, $site_name) {
            if ($site_logoinfo) {
                $oss_file_path = $site_logoinfo['oss_logo_path'];
                $pathinfo = pathinfo(parse_url($oss_file_path)['path']);
                $ext = '';
                if (array_key_exists('extension', $pathinfo)) {
                    $ext = '.' . $pathinfo['extension'];
                }
                return "<img src='/images/logo{$site_id}{$ext}' title='$site_name' alt='$site_name'>";
            }
            return $site_name;
        });

    }


    /**
     * 获取联系方式等相关数据
     * @access public
     */
    public function getContactInfo()
    {
        $siteinfo = $this->siteinfo;
        return Cache::remember('contactway', function () use ($siteinfo) {
            //支持的字段
            $contact_field = [
                'address', 'telephone', 'mobile', 'email', 'zipcode', 'four00', 'qq', 'weixin', 'fax'
            ];
            $contact_way_id = $siteinfo['support_hotline'];
            $contact_info = [];
            if ($contact_way_id) {
                //缓存中有 则用缓存中的
                $contact_info = Db::name('contactway')->where('id', $contact_way_id)->field('html as contact,detail as title')->find();
            }
            $commoncontact = [];
            if ($contact_info) {
                $commoncontact = @unserialize($contact_info['contact']);
                if ($contact_info === false) {
                    $commoncontact = [];
                }
            }
            $contact = [];
            foreach ($contact_field as $field) {
                //以站点中设置为主
                $contact[$field] = $siteinfo[$field] ?: array_key_exists($field, $commoncontact) ? $commoncontact[$field] : '';
            }
            return $contact;
        });
    }


    /**
     * 获取外链
     * @access public
     */
    public function getPatternLink()
    {
        $link_id = $this->siteinfo['link_id'];
        //友链信息
        $link_info = Cache::remember('linkinfo', function () use ($link_id) {
            return Db::name('links')->where(['id' => ['in', array_filter(explode(',', $link_id))]])->field('id,name,domain')->select();
        });
        $partnersite = [];
        foreach ($link_info as $k => $v) {
            $partnersite[] = [
                'href' => $v['domain'],
                'text' => $v['name']
            ];
        }
        //链轮的类型
        $chain_type = '';
        //该站点需要链接到的站点
        $next_site = [];
        //主站是哪个
        $main_site = [];
        $is_mainsite = $this->siteinfo['main_site'];
        if ($is_mainsite == '10') {
            //表示不是主站
            //站点类型 用于取出主站 以及链轮类型 来
            list($chain_type, $next_site, $main_site) = (new Site())->getLinkInfo();
        }
        if ($next_site) {
            $partnersite[] = [
                'href' => $next_site['url'],
                'text' => $next_site['site_name']
            ];
        }
        if ($main_site) {
            $partnersite[] = [
                'href' => $main_site['url'],
                'text' => $main_site['site_name']
            ];
        }
        return $partnersite;
    }

    /**
     * 获取站点内容调取列表 比如相关站点
     * @access private
     * @return mixed
     */
    private function getSiteGetContent()
    {
        $node_id = $this->siteinfo['node_id'];
        return Cache::remember('contentlist', function () use ($node_id) {
            $contentlist = Db::name('content_get')->where('node_id', $node_id)->field('en_name,name,content,href')->select();
            $list = [];
            foreach ($contentlist as $v) {
                $list[$v['en_name']] = [
                    'name' => $v['name'],
                    'content' => $v['content'],
                    'href' => $v['href']
                ];
            }
            return $list;
        });
    }


    /**
     * 获取 页面中必须的元素
     *
     * @param string $param 如果是   index  第二第三个参数没用
     *                              menu 第二个参数$param表示   $page_id 也就是菜单的英文名 第三个参数 $param2 表示 菜单名 menu_name   $param3 是 menu_id  $param4 为type_id菜单下的id  $param5 表示菜单类型 articlelist newslist  questionlist  productlist
     *                              detail   第二个参数$param表示  $articletitle 用来获取文章标题 第三个参数 $param2 表示 文章的内容 $param3 表示文章设置的keywords  $param4 是 a_keyword_id  $para5  表示 menu_id  $param6 表示 menu_name $param7 用于生成面包屑的时候 获取 栏目菜单的url
     *                              activity
     *                              query  查询相关tdk获取
     * @param string $param2
     * @param string $param3
     * @param string $param4
     * @param string $param5
     * @param string $param6
     * @param string $param7
     * @param string $suffix
     * @param bool $mainsite
     * @param string $district_name
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function getEssentialElement($param = '', $param2 = '', $param3 = '', $param4 = '', $param5 = '', $param6 = '', $param7 = '', $suffix = '', $mainsite = true, $district_name = '')
    {
        //获取站点的logo
        $logo = $this->getSiteLogo();
        $keyword_info = (new Keyword())->getKeywordInfo();
        //菜单如果是 详情页面 也就是 文章内容页面  详情类型的 需要 /
        //该站点的网址
        $imgset = $this->getImgList();
        //菜单的返回也需要修改
        $menu = $this->getTreeMenuInfo($param2, $param3);
        //用于生成面包屑
        $allmenu = (new Menu())->getMergedMenu();
        //获取网站中每个分类的 $type_aliasarr
        /*
          [
               'article'=>[
                    //支持多个
                   '栏目英文名alias'=>[
                        栏目信息
                    ]
                ],
               'question'=>[],
               'product'=>[],
          ]
        */
        //获取每个菜单的menu_idarr
        /*
         [
               'menu_id'=>[
                    //支持多个
                   '栏目英文名alias'=>[
                        栏目信息
                    ],
                    ''=>[],
                ],
               'menu_id2'=>[],
               'menu_id3'=>[],
          ]
        */
        // 获取每种分类的 typeid_arr
        /*
         [
               'article'=>[
                    //支持多个
                   '栏目id'=>[
                        栏目信息
                    ],
                    ''=>[],
                ],
               'product'=>[],
               'question'=>[],
          ]
        */
        list($type_aliasarr, $typeid_arr) = $this->getTypeIdInfo();
        //活动创意相关操作
        list($activity, $activity_small, $activity_en) = $this->getActivity();
        //获取站点的类型 手机站的域名 手机站点的跳转链接
        list($m_url, $redirect_code) = $this->getMobileSiteInfo();
        //这个不需要存到缓存中
        $sync_info = $this->getDbArticleListId();
        $breadcrumb = [];
        //每个页面特殊的变量存在
        switch ($this->tag) {
            case 'index':
                $page_id = 'index';
                //然后获取 TDK 等数据  首先到数据库
                list($title, $keyword, $description) = $this->getIndexPageTDK($keyword_info);
                //获取首页面包屑
                //Breadcrumb 面包屑
                $breadcrumb = $this->getBreadCrumb($allmenu);
                $menu_name = '首页';
                break;
            case 'menu':
                //菜单 页面的TDK 分为两种 一种是已经存在的 另外一种为详情形式的列表 栏目的英文名
                $page_id = $param;
                //栏目名
                $menu_name = $param2;
                //栏目的id
                $menu_id = $param3;
                //文章分类的id
                $type_id = $param4;
                //type 为 productlist articlelist questionlist
                $type = $param5;
                list($title, $keyword, $description) = $this->getMenuPageTDK($keyword_info, $page_id, $menu_name, $menu_id, $menu_name);
                //获取菜单的 面包屑 导航
                //需要注意下 详情型的菜单 没有type
                $breadcrumb = $this->getBreadCrumb($allmenu, $menu_id);
                break;
            case 'detail':
                //详情页面
                $page_id = $param['id'];
                //文章的标题
                $articletitle = $param['title'];
                //文章的summary
                $articlecontent = $param2;
                //关键词 文章中的关键词
                $keywords = $param3;
                //该文章所属的父类的a类关键词
                $a_keyword_id = $param4;
                //当前栏目的id
                $menu_id = $param5;
                //当前栏目的name
                $menu_name = $param6;
                $type = $param7;
                list($title, $keyword, $description) = $this->getDetailPageTDK($page_id, $type, $keyword_info, $articletitle, $articlecontent, $keywords, $a_keyword_id);
                //获取详情页面的面包屑
                $breadcrumb = $this->getBreadCrumb($allmenu, $menu_id);
                break;
            case 'activity':
                //活动相关获取
                $title = $param;
                $keyword = $param2;
                $description = $param3;
                //面包屑是空的
                $breadcrumb = $this->getBreadCrumb($allmenu);
                $menu_name = '活动创意';
                break;
            case 'query':
                //查询操作
                $title = $param;
                $keyword = $param2;
                $description = $param3;
                $breadcrumb = $this->getBreadCrumb($allmenu);
                $menu_name = '查询结果';
                break;
            case 'tag':
                //文章之类的标签
                $tag_name = $param;
                //文章标题相关
                //随机选择一个a类关键词组织页面的list相关信息
                list($title, $keyword, $description) = $this->getTaglistPageTDK($keyword_info, $tag_name);
                $breadcrumb = $this->getBreadCrumb($allmenu);
                $menu_name = '查询结果';
                break;
        }
        //获取不分类的文章 全部分类的都都获取到
        $article_list = $this->getArticleList($sync_info, $typeid_arr, 20);
        //获取不分类的文章 全部分类都获取到
        $question_list = $this->getQuestionList($sync_info, $typeid_arr, 20);
        //获取零散段落类型  全部分类都获取到
        //list($scatteredarticle_list, $news_more) = $this->getScatteredArticleList($artiletype_sync_info, $typeid_arr);
        //产品类型 列表获取 全部分类都获取到
        $product_list = $this->getProductList($sync_info, $typeid_arr, 20);
        //根据文章分类展现列表以及more
        $article_typelist = $this->getArticleTypeList($sync_info, $type_aliasarr, $typeid_arr, 20);
        //根据文章分类展现列表以及more
        $question_typelist = $this->getQuestionTypeList($sync_info, $type_aliasarr, $typeid_arr, 20);
        //根据文章分类展现列表以及more
        $product_typelist = $this->getProductTypeList($sync_info, $type_aliasarr, $typeid_arr, 20);
        // 根据文章flag 相关
        $article_flaglist = $this->getArticleFlagList($sync_info, $type_aliasarr, $typeid_arr, 20);
        // 根据问答查询flag
        $question_flaglist = $this->getQuestionFlagList($sync_info, $type_aliasarr, $typeid_arr, 20);
        // 产品相关flag
        $product_flaglist = $this->getProductFlagList($sync_info, $type_aliasarr, $typeid_arr, 20);
        //获取友链
        $partnersite = $this->getPatternLink();
        //获取公共代码
        list($pre_head_js, $after_head_js) = $this->getSiteJsCode();
        //获取公司联系方式等 会在右上角或者其他位置添加  这个应该支持小后台能自己修改才对
        $contact = $this->getContactInfo();
        //获取备案信息
        $beian = $this->getBeianInfo();
        $tdk = $this->form_tdk_html($title, $keyword, $description);
        $share = $this->get_share_code();
        //版本　copyright获取
        $copyright = $this->getSiteCopyright();
        //技术支持
        $powerby = $this->getSitePowerby();
        //调取页面中的相关组件
        $getcontent = $this->getSiteGetContent();
        $site_name = $this->site_name;
        $com_name = $this->com_name;
        $url = $this->siteurl;
        list($childsite, $childtreesite, $currentsite) = $this->getSiteList();
        //获取站点list
        //其中tdk是已经嵌套完成的html代码title keyword description为单独的代码。
        return compact('breadcrumb', 'com_name', 'url', 'site_name', 'menu_name', 'logo', 'contact', 'beian', 'copyright', 'powerby', 'getcontent', 'tdk', 'title', 'keyword', 'description', 'share', 'm_url', 'redirect_code', 'menu', 'imgset', 'activity', 'activity_small', 'activity_en', 'partnersite', 'pre_head_js', 'after_head_js', 'article_list', 'question_list', 'product_list', 'article_more', 'article_typelist', 'question_typelist', 'product_typelist', 'article_flaglist', 'question_flaglist', 'product_flaglist', 'childsite', 'childtreesite', 'currentsite');
    }


    /**
     * 获取该站点的子站列表
     * @access public
     */
    public function getSiteList()
    {
        //站点信息
        $field = 'id,name,pinyin,parent_id,suffix';
        $parent_id = $this->siteinfo['stations_area'];
        $parent = Db::name('District')->where(['id' => $parent_id])->field($field)->find();
        $this->district_id;
        $this->district_name;
        $childsite = Db::name('District')->where(['path' => ['like', "%,{$parent_id},%"]])->field($field)->select();
        array_push($childsite, $parent);
        $allsite = [];
        // 当前如果是主站的话 需要有默认值
        $currentsite = [
            'id' => 0,
            'name' => '主站',
            'parent_id' => 0,
            'url' => $this->siteurl
        ];
        foreach ($childsite as $k => $v) {
            $v['url'] = 'http://' . $v['pinyin'] . '.' . $this->domain;
            unset($v['pinyin']);
            $v['name'] .= $v['suffix'];
            unset($v['suffix']);
            $v['current'] = false;
            if ($this->district_id == $v['id']) {
                $currentsite = $v;
                $v['current'] = true;
            }
            array_push($allsite, $v);
        }
        //生成树形结构
        $treesite = $this->list_to_tree($allsite, 'id', 'parent_id', 'childsite', $parent['parent_id']);
        return [$allsite, $treesite, $currentsite];
    }


    /**
     * 获取每个menu下的所有分类id数组
     * @access public
     * @param $menu_id
     * @param $ptypeidarr
     * @return mixed
     */
    public function getMenuChildrenMenuTypeid($menu_id, $ptypeidarr)
    {
        // 因为会选择三级栏目所以需要选出子栏目的type_id 来
        return Cache::remember('menu_children' . $menu_id, function () use ($menu_id, $ptypeidarr) {
            $menulist = \app\tool\model\Menu::Where('path', 'like', "%,$menu_id,%")->select();
            foreach ($menulist as $menu) {
                //子孙栏目选择type_id
                $ptype_idstr = $menu['type_id'];
                $typeidarr = array_filter(explode(',', $ptype_idstr));
                $ptypeidarr = array_merge($ptypeidarr, $typeidarr);
            }
            $ptypeidarr = array_unique($ptypeidarr);
            return $ptypeidarr;
        });
    }


    /**
     * 获取同一级别的菜单
     * @access public
     * @param $menu_id
     * @param $flag
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getMenuSiblingMenuTypeid($menu_id, $flag)
    {
        //当前菜单的父亲菜单
        $pidinfo = Db::name('menu')->where('id', $menu_id)->where('node_id', $this->node_id)->field('p_id')->find();
        $pid = $pidinfo['p_id'];
        $menulist = [];
        if (!$pid) {
            //表示一级菜单 取出当前flag 一致的 一级menu
            $menulist = Db::name('menu')->where('p_id', 0)->where('node_id', $this->node_id)->where('id', 'in', array_filter(explode(',', $this->menu_ids)))->where('flag', $flag)->select();
        } else {
            $menulist = Db::name('menu')->where('p_id', $pid)->where('node_id', $this->node_id)->select();
        }
        $typeidlist = [];
        foreach ($menulist as $menu) {
            //子孙栏目选择type_id
            $ptype_idstr = $menu['type_id'];
            $typeidarr = array_filter(explode(',', $ptype_idstr));
            $typeidlist = array_merge($typeidlist, $typeidarr);
        }
        return $typeidlist;
    }


    /**
     * 获取类型 id
     * @access private
     */
    public function getTypeIdInfo()
    {
        $menu_idstr = $this->menu_ids;
        return Cache::remember('TypeId', function () use ($menu_idstr) {
            //站点所选择的菜单
            $menu_idarr = array_filter(explode(',', $menu_idstr));
            //获取每个菜单下的type_id
            $field = 'id,generate_name,name,flag,type_id';
            $menulist = Db::name('menu')->Where('id', 'in', $menu_idarr)->field($field)->select();
            foreach ($menu_idarr as $v) {
                $pmenulist = Db::name('menu')->Where('path', 'like', "%,$v,%")->order("sort", "desc")->field($field)->select();
                $menulist = array_merge($menulist, $pmenulist);
            }
            //组织两套数据 菜单对应的id 数据
            $type_aliasarr = [];
            $typeid_arr = [];
            $this->getTypeInfo($menulist, $type_aliasarr, $typeid_arr);
            //第一个是菜单对应的 type  第二个是 article question product等类型对应的别名type 第三个是 类型对应的type_id type
            return [$type_aliasarr, $typeid_arr];
        });
    }

    /**
     * 递归获取网站type_aliasarr typeid_arr
     * @param $menulist
     * @param $type_aliasarr
     * @param $typeid_arr
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTypeInfo($menulist, &$type_aliasarr, &$typeid_arr)
    {
        foreach ($menulist as $k => $v) {
            //栏目类型
            $flag = $v['flag'];
            if ($flag == 1) {
                //详情型号的直接跳过
                continue;
            }
            if ($flag == 4) {
                //零散段落暂时跳过 后期需要重写
                continue;
            }
            $menu_id = $v['id'];
            $menu_name = $v['name'];
            $menu_enname = $v['generate_name'];
            $type_idstr = $v['type_id'];
            //网站typeidarr
            $typeidarr = array_filter(explode(',', $type_idstr));
            //获取每个类型信息
            $typelist = [];
            $listpath = '';
            switch ($flag) {
                case '2':
                    $type = 'question';
                    $listpath = $this->questionListPath;
                    //问答形式
                    $typelist = Questiontype::where('id', 'in', $typeidarr)->select();
                    break;
                case '3':
                    //文章形式
                    $type = 'article';
                    $listpath = $this->articleListPath;
                    $typelist = Articletype::where('id', 'in', $typeidarr)->select();
                    break;
                case '4':
                    //段落形式直接跳过就行暂时不考虑
                    break;
                case '5':
                    $type = 'product';
                    $listpath = $this->productListPath;
                    $typelist = Producttype::where('id', 'in', $typeidarr)->select();
                    break;
            }
            foreach ($typelist as $val) {
                $key = $val['alias'] ?: $val['id'];
                $value = [
                    'menu_id' => $menu_id,
                    'menu_name' => $menu_name,
                    'menu_enname' => $menu_enname,
                    'type_id' => $val['id'],
                    'type_name' => $val['name'],
                    'href' => sprintf($listpath, "{$menu_enname}_t{$val['id']}")
                ];
                if (!array_key_exists($type, $type_aliasarr)) {
                    $type_aliasarr[$type] = [];
                }
                //如果存在 alias 英文名 则用alias 不存在的话用type_id
                //要求同类下不能有重名alias 的
                $type_aliasarr[$type][$key] = $value;
                if (!array_key_exists($type, $typeid_arr)) {
                    $typeid_arr[$type] = [];
                }
                $typeid_arr[$type][$val['id']] = $value;
            }
            //查询当前的menu_id
            $this->getTypeInfo(\app\tool\model\Menu::Where('p_id', $menu_id)->field('id,generate_name,name,flag,type_id')->select(), $type_aliasarr, $typeid_arr);
        }
    }


    /**
     * 获取页面的分享代码
     * @access private
     */
    private function get_share_code()
    {
        return <<<code
    <div class="bdsharebuttonbox">
        <a href="#" class="bds_more" data-cmd="more"></a>
        <a href="#" class="bds_qzone" data-cmd="qzone" title="分享到QQ空间"></a>
        <a href="#" class="bds_tsina" data-cmd="tsina" title="分享到新浪微博"></a>
        <a href="#" class="bds_weixin" data-cmd="weixin" title="分享到微信"></a>
        <a href="#" class="bds_ibaidu" data-cmd="ibaidu" title="分享到百度中心"></a>
        <a href="#" class="bds_bdhome" data-cmd="bdhome" title="分享到百度新首页"></a>
        <a href="#" class="bds_tieba" data-cmd="tieba" title="分享到百度贴吧"></a>
    </div>
    <script>
        window._bd_share_config={"common":{"bdSnsKey":{},"bdText":"","bdMini":"2","bdMiniList":false,"bdPic":"","bdStyle":"0","bdSize":"16"},"share":{}};with(document)0[(getElementsByTagName('head')[0]||body).appendChild(createElement('script')).src='http://bdimg.share.baidu.com//api/js/share.js?v=89860593.js?cdnversion='+~(-new Date()/36e5)];
    </script>
code;
    }


    /**
     * 生成tdk 相关html
     * @access private
     * @param $title
     * @param $keyword
     * @param $description
     * @return string
     */
    private function form_tdk_html($title, $keyword, $description)
    {
        $title_template = "<title>%s</title>";
        $keywords_template = "<meta name='keywords' content='%s'>";
        $description_template = "<meta name='description' content='%s'>";
        $encode = '<meta charset="utf-8"/>';
        $author = '<meta name="author" content="北京易至信科技有限公司" />';
        return $encode . sprintf($title_template, $title) . sprintf($keywords_template, $keyword) . sprintf($description_template, $description) . $author;
    }

    /**
     * 获取图片集调用链接
     * @access public
     * @return mixed
     */
    private function getImgList()
    {
        $node_id = $this->node_id;
        return Cache::remember('imglist', function () use ($node_id) {
            $imglist = Db::name('imglist')->where('node_id', $node_id)->where('status', '10')->select();
            $imgset = [];
            foreach ($imglist as $imgs) {
                $en_name = $imgs['en_name'];
                $imgser = $imgs['imgser'];
                $perimgset = [];
                if ($imgser) {
                    foreach (unserialize($imgser) as $val) {
                        $perimgset[] = [
                            'src' => '/images/' . $val['imgname'],
                            'title' => $val['title'],
                            'alt' => $val['title'],
                            //默认没有链接的话跳转到首页
                            'href' => $val['link'] ?: '/',
                        ];
                    }
                }
                $imgset[$en_name] = $perimgset;
            }
            return $imgset;
        });
    }


    /**
     * 获取当前栏目的菜单信息
     * @access private
     * @todo 如果是menu或者是index  需要优化当前栏目比如前段需要表示出来当前页 并且给出有区别的样式
     * @param $generate_name
     * @param $menu_id
     * @return array|false|\PDOStatement|string|\think\Collection
     */
    private function getTreeMenuInfo($generate_name, $menu_id)
    {
        $site_name = $this->site_name;
        $url = $this->siteurl;
        //需要把首页链接追加进来 而且需要在首位
        $menu = (new Menu())->getMergedMenu();
        //循环为树状结构
        $tree = array();
        //创建基于主键的数组引用
        $refer = array();
        foreach ($menu as $key => $data) {
            unset($data['generate_name']);
            unset($data['flag']);
            unset($data['type_id']);
            unset($data['detailtemplate']);
            unset($data['listtemplate']);
            unset($data['covertemplate']);
            $menu[$key] = $data;
            $refer[$data['id']] = &$menu[$key];
        }
        //循环中还需要设置下当前menu相关信息
        foreach ($menu as $key => $data) {
            // 判断是否存在parent
            $is_current = false;
            if ($menu_id == $data['id']) {
                //表示当前选中该菜单
                $is_current = true;
            }
            $parentId = $data['p_id'];
            $menu[$key]['current'] = $is_current;
            if ($parentId == 0) {
                //根节点元素
                $tree[] = &$menu[$key];
            } else {
                //需要把上级的文件也设置为相关选中操作；
                if ($is_current) {
                    $path = $menu[$key]['path'];
                    $menu_idarr = array_filter(explode(',', $path));
                    foreach ($menu_idarr as $menu_id) {
                        $menu[$menu_id]['current'] = $is_current;
                    }
                }
                if (isset($refer[$parentId])) {
                    //当前正在遍历的父亲节点的数据
                    $parent = &$refer[$parentId];
                    //把当前正在遍历的数据赋值给父亲类的  children
                    $parent['child'][] = &$menu[$key];
                }
            }
        }
        $is_current = false;
        if ($this->tag == 'index') {
            //首页默认选中的
            $is_current = true;
        }
        array_unshift($tree, ['id' => 0, 'name' => '首页', 'path' => '', 'p_id' => 0, 'title' => $site_name, 'href' => '/', 'content' => '', 'current' => $is_current]);
        return $tree;
    }


    /**
     * 截取中文字符串  utf-8
     * @param String $str 要截取的中文字符串
     * @param $len
     * @return mixed
     */
    public function utf8chstringsubstr($str, $len)
    {
        for ($i = 0; $i < $len; $i++) {
            $temp_str = substr($str, 0, 1);
            if (ord($temp_str) > 127) {
                $i++;
                if ($i < $len) {
                    $new_str[] = substr($str, 0, 3);
                    $str = substr($str, 3);
                }
            } else {
                $new_str[] = substr($str, 0, 1);
                $str = substr($str, 1);
            }
        }
        //把数组元素组合为string
        return join($new_str);
    }


    /**
     * 获取面包屑 相关信息
     * @access
     * @param $allmenu
     * @param int $menu_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBreadCrumb($allmenu, $menu_id = 0)
    {
        $breadcrumb = [
            ['text' => '首页', 'href' => '/', 'title' => '首页'],
        ];
        switch ($this->tag) {
            case 'index':
                break;
            case 'menu':
            case 'detail':
                //详情 或者 列表页面
                $menu_path = \app\tool\model\Menu::Where('id', $menu_id)->field('path')->find();
                $path = $menu_path['path'];
                $p_idarr = array_filter(explode(',', $path));
                array_push($p_idarr, $menu_id);
                foreach ($p_idarr as $v) {
                    $pmenu = $allmenu[$v];
                    $perbreadcrumb = [
                        'text' => $pmenu['name'],
                        'href' => $pmenu['href'],
                        'title' => $pmenu['title']
                    ];
                    array_push($breadcrumb, $perbreadcrumb);
                }
                break;
            case 'query':
                array_push($breadcrumb, [
                    'text' => '查询结果',
                    'href' => '/',
                    'title' => '查询'
                ]);
                break;
            case 'activity':
                break;
            case 'tag':
                array_push($breadcrumb, [
                    'text' => '站点标签',
                    'href' => '/',
                    'title' => '站点标签'
                ]);
                break;
        }
        return $breadcrumb;
    }

}
