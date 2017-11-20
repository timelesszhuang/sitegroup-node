<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\tool\model\Activity;
use think\Cache;
use think\Config;
use think\Db;


/**
 * 公共的相关操作的 工具
 */
class Commontool extends Common
{

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
    public static function getMobileSiteInfo()
    {
        $mobileinfo = Cache::remember('mobileinfo', function () {
            //获取手机相关信息
            $siteinfo = Site::getSiteInfo();
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
                    $m_redirect_code = self::getRedirectCode($m_site_url);
                }
            }
            $mobileinfo = [$m_site_url, $m_redirect_code];
            return $mobileinfo;
        });
        return $mobileinfo;
    }


    /**
     * 获取其他的数据
     * @access public
     */
    public static function getRedirectCode($url)
    {
        $code = Db::name('system_config')->where(["name" => 'SYSTEM_PCTOM_REDIRECT_CODE'])->field('value')->find()['value'];
        $redirect_code = '';
        if ($code) {
            $redirect_code = str_replace('{{mobile_path}}', $url, $code);
        }
        return $redirect_code;
    }


    /**
     * 获取首页面的 tdk 相关数据
     * @access public
     * @param $keyword  关键词数组
     * @param $page_id 页面的id  index
     * @param $site_id 站点的id
     * @param $node_id 节点的id
     * @param $tag 标志tag  index 表示首页  栏目 column  详情页 detail
     * @return array
     */
    public static function getIndexPageTDK($keyword_info, $site_id, $site_name, $node_id, $com_name)
    {
        $title = '';
        $keyword = '';
        $description = '';
        $page_id = 'index';
        //默认首页的menu_id 为零
        $menu_id = 0;
        $page_name = '首页';
        $page_type = 'index';
        list($pageinfo_id, $akeyword_id, $change_status, $title, $keyword, $description) = self::getDbPageTDK($menu_id, $node_id, $site_id, $page_type);
        if (empty($title)) {
            //tdk 是空的 需要 重新 从关键词中获取
            //首页的title ： A类关键词1_A类关键词2_A类关键词3-公司名
            //     keyword  ： A类关键词1,A类关键词2,A类关键词3
            //     description : A类关键词拼接
            $a_keywordname_arr = array_column($keyword_info, 'name');
            $title = implode('_', $a_keywordname_arr) . '-' . $com_name;
            $keyword = implode(',', $a_keywordname_arr);
            $description = implode('，', $a_keywordname_arr) . '，' . $com_name;
            Db::name('SitePageinfo')->insert([
                'menu_id' => 0,
                'site_id' => $site_id,
                'site_name' => $site_name,
                'page_type' => $page_type,
                'node_id' => $node_id,
                'page_id' => $page_id,
                'page_name' => $page_name,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'akeyword_id' => 0,
                'pre_akeyword_id' => 0,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }
        return [$title, $keyword, $description];
    }


    /**
     * 获取栏目页面的 tdk 相关数据
     * @param $keyword_info 关键词相关
     * @param $page_id 页面的id  比如 contactme
     * @param $site_id 站点的id
     * @param $node_id 节点的id
     * @param $menu_name 栏目名
     * @return array
     */
    public static function getMenuPageTDK($keyword_info, $page_id, $page_name, $site_id, $site_name, $node_id, $menu_id, $menu_name)
    {
        $page_type = 'menu';
        //首先从数据库中获取当前站点设置的 tdk 等 相关数据
        /**
         * 表示页面是不是已经生成过
         * 如果没有生成  则随机选择一个A类 关键词 按照规则拼接关键词
         * 如果已经生成过 则需要比对现在的关键词是不是已经更换过 更换过的需要重新生成
         */
        list($pageinfo_id, $akeyword_id, $change_status, $title, $keyword, $description) = self::getDbPageTDK($menu_id, $node_id, $site_id, $page_type);
        if (empty($title)) {
            // 栏目页面的 TDK 获取 A类关键词随机选择
            //栏目页的 title：B类关键词多个_A类关键词1-栏目名
            //        keyword：B类关键词多个,A类关键词
            //        description:拼接一段就可以栏目名
            $a_keyword_key = array_rand($keyword_info, 1);
            $a_child_info = $keyword_info[$a_keyword_key];
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
            //选择好了 之后需要添加到数据库中 一定是新增
            Db::name('SitePageinfo')->insert([
                'menu_id' => $menu_id,
                'site_id' => $site_id,
                'site_name' => $site_name,
                'page_type' => $page_type,
                'node_id' => $node_id,
                'page_id' => $page_id,
                'page_name' => $page_name,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'pre_akeyword_id' => $a_keyword_id,
                'akeyword_id' => $a_keyword_id,
                'create_time' => time(),
                'update_time' => time()
            ]);
        } elseif ($change_status) {
            //需要验证下
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
            Db::name('SitePageinfo')->update([
                'id' => $pageinfo_id,
                'title' => $title,
                'keyword' => $keyword,
                'description' => $description,
                'pre_akeyword_id' => $a_keyword_id
            ]);
        }
        return [$title, $keyword, $description];
    }


    /**
     * 详情页 的TDK 获取   需要有固定的关键词
     * @param $keyword_info 关键词相关
     * @param $site_id 站点的id
     * @param $node_id 节点的id
     * @param $articletitle
     * @param $articlecontent
     * @param $a_keyword_key
     * @return array
     * @todo 详情页不需要 存储在数据库中 TDK定死就行
     */
    public static function getDetailPageTDK($keyword_info, $site_id, $node_id, $articletitle, $articlecontent, $keywords, $a_keyword_id)
    {
        //需要知道 栏目的关键词等
        //$keyword_info, $site_id, $node_id, $articletitle, $articlecontent
        // 栏目页面的 TDK 获取 A类关键词随机选择
        //栏目页的 title：C类关键词多个_A类关键词1-文章标题
        //        keyword：C类关键词多个,A类关键词
        //        description:拼接一段就可以栏目名
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
                'site_id' => $site_id,
                'node_id' => $node_id,
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
        $description = $articlecontent;
        return [$title, $keyword, $description];
    }


    /**
     * 从数据库中获取页面的相关信息
     * @access public
     */
    public static function getDbPageTDK($menu_id, $node_id, $site_id, $page_type)
    {
        $page_info = Db::name('site_pageinfo')->where(['menu_id' => $menu_id, 'node_id' => $node_id, 'site_id' => $site_id, 'page_type' => $page_type])->field('id,title,keyword,description,pre_akeyword_id,akeyword_id')->find();
        $akeyword_changestatus = false;
        $akeyword_id = 0;
        if ($page_info) {
            if ($page_info['pre_akeyword_id'] != $page_info['akeyword_id']) {
                $akeyword_changestatus = true;
                $akeyword_id = $page_info['akeyword_id'];
            }
            return [$page_info['id'], $akeyword_id, $akeyword_changestatus, $page_info['title'], $page_info['keyword'], $page_info['description']];
        }
        return [0, $akeyword_id, $akeyword_changestatus, '', '', ''];
    }


    /**
     * 获取 文章列表 获取十条　文件名如 article1 　article2
     * @access public
     * @param $sync_info 该站点所有文章分类的 静态化状况
     * @param $site_id
     *              如果是 detail 的话 应该给
     * @param int $limit
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getArticleList($sync_info, $site_id, $limit = 10)
    {
        $article_sync_info = array_key_exists('article', $sync_info) ? $sync_info['article'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        if ($article_sync_info) {
            $where = '';
            //还需要　只获取　允许同步的文章
            foreach ($article_sync_info as $k => $v) {
                if ($k == 0) {
                    $where .= "(`articletype_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
                } else {
                    $where .= ' or' . " (`articletype_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
                }
                //比如一般的列表中都会有更多操作 随机从文章列表中选出一个菜单的id来组织数据
                if ($more['href'] == '/') {
                    $more = ['title' => $v['menu_name'], 'href' => "/articlelist/{$v['menu_id']}.html", 'text' => '更多'];
                }
            }
            $where = "({$where}) and ((`is_sync`= '20') or (`is_sync`='10' and `site_id`='{$site_id}'))";
            $article = Db::name('Article')->where($where)->field('id,title,thumbnails,thumbnails_name,summary,create_time')->order('id desc')->limit($limit)->select();
            $articlelist = [];
            foreach ($article as $k => $v) {
                $art = [];
                $art['title'] = $v['title'];
                $art['a_href'] = '/article/article' . $v['id'] . '.html';
                $art['summary'] = $v['summary'];
                $v['title'] = str_replace('%', '', $v['title']);
                $img_template = "<img src='%s' alt='{$v['title']}' title='{$v['title']}'>";
                $img = sprintf($img_template, '/templatestatic/default.jpg');
                if (!empty($v["thumbnails_name"])) {
                    //如果有本地图片则 为本地图片
                    $src = "/images/" . $v['thumbnails_name'];
                    $img = sprintf($img_template, $src);
                } else if (!empty($v["thumbnails"])) {
                    $img = sprintf($img_template, $v['thumbnails']);
                }
                $art['thumbnails'] = $img;
                $art['create_time'] = date('Y-m-d', $v['create_time']);
                $articlelist[] = $art;
            }
            return [$articlelist, $more];
        }
        return [[], $more];
    }


    /**
     * 获取 产品列表 获取十条产品
     * @access public
     * @param $sync_info 该站点所有文章分类的 静态化状况
     * @param $site_id 如果是 detail 的话 应该给
     * @param int $limit
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getProductList($sync_info, $site_id, $limit = 10)
    {
        $product_sync_info = array_key_exists('product', $sync_info) ? $sync_info['product'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        if ($product_sync_info) {
            $where = '';
            foreach ($product_sync_info as $k => $v) {
                if ($k == 0) {
                    $where .= "(`type_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
                } else {
                    $where .= ' or' . " (`type_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
                }
                if ($more['href'] == '/') {
                    $more = ['title' => $v['menu_name'], 'href' => "/productlist/{$v['menu_id']}.html", 'text' => '更多'];
                }
            }
            $product = Db::name('Product')->where($where)->field('id,name,image_name,sn,payway,type_name,summary,create_time')->order('id desc')->limit($limit)->select();
            $productlist = [];
            foreach ($product as $k => $v) {
                $art = [];
                $art['name'] = $v['name'];
                $art['a_href'] = '/product/product' . $v['id'] . '.html';
                $art['summary'] = $v['summary'];
                $src = "/images/" . $v['image_name'];
                $img = "<img src='{$src}' alt= '{$v['name']}'>";
                $art['thumbnails'] = $img;
                $art['create_time'] = date('Y-m-d', $v['create_time']);
                $productlist[] = $art;
            }
            return [$productlist, $more];
        }
        return [[], $more];
    }


    /**
     * 获取 问题列表 获取十条　文件名如 question1 　question2
     * @access public
     * @param $sync_info
     * @param $site_id
     * @param int $limit
     * @return array
     */
    public static function getQuestionList($sync_info, $site_id, $limit = 10)
    {
        $question_sync_info = array_key_exists('question', $sync_info) ? $sync_info['question'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        if ($question_sync_info) {
            $where = '';
            foreach ($question_sync_info as $k => $v) {
                if ($k == 0) {
                    $where .= "(`type_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
                } else {
                    $where .= ' or' . " (`type_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
                }
                if ($more['href'] == '/') {
                    $more = ['title' => $v['menu_name'], "/questionlist/{$v['menu_id']}.html", 'text' => '更多'];
                }
            }
            $question = Db::name('Question')->where($where)->field('id,question,create_time')->order('id desc')->limit($limit)->select();
            $questionlist = [];
            foreach ($question as $k => $v) {
                $questionlist[] = [
                    'question' => $v['question'],
                    'a_href' => '/question/question' . $v['id'] . '.html',
                    'create_time' => date('Y-m-d', $v['create_time']),
                ];
            }
            return [$questionlist, $more];
        }
        return [[], $more];
    }


    /**
     * 获取 零散段落 分类  文件名如 article1 　article2
     * @access public
     * @param $sync_info
     * @param $site_id
     * @param int $limit
     * @return array
     */
    public static function getScatteredArticleList($sync_info, $site_id, $limit = 10)
    {
        $scattered_sync_info = array_key_exists('scatteredarticle', $sync_info) ? $sync_info['scatteredarticle'] : [];
        $more = ['title' => '', 'href' => '/', 'text' => '更多'];
        if ($scattered_sync_info) {
            $where = '';
            foreach ($scattered_sync_info as $k => $v) {
                if ($k == 0) {
                    $where .= "(`articletype_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
                } else {
                    $where .= ' or' . " (`articletype_id` = {$v['type_id']} and `id`<= {$v['max_id']})";
                }
                if ($more['href'] == '/') {
                    $more = ['title' => $v['menu_name'], 'href' => "/news/{$v['menu_id']}.html", 'text' => '更多'];
                }
            }
            $scattered_article = Db::name('Scattered_title')->where($where)->field('id,title,create_time')->order('id desc')->limit($limit)->select();
            $articlelist = [];
            foreach ($scattered_article as $k => $v) {
                $articlelist[] = [
                    'a_href' => '/news/news' . $v['id'] . '.html',
                    'title' => $v['title'],
                    'create_time' => date('Y-m-d', $v['create_time'])
                ];
            }
            return [$articlelist, $more];
        }
        return [[], $more];
    }


    /**
     * 获取公共代码
     * @access public
     */
    public static function getCommonCode($code_ids)
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
     * 获取页面中 需要的文章列表  内容列表 问答列表
     * 获取页面中文章列表 需要的  分类的id列表 跟 已经静态化的最大的id值
     * 1、比如 文章 获取该站点所有的 选择的分类的id  跟 文章最大的id
     *    取列表的时候 sql 用 type_id in (id,id) and id < 已经静态化的最大的id值
     * @access public
     * @param $menu_ids 站点选择的菜单的id
     * @param $site_id 站点的id 信息
     * @return array
     */
    public static function getDbArticleListId($menu_ids, $site_id)
    {
        //获取页面中  会用到的 文章列表 问题列表 零散段落列表
        //配置的菜单信息
        //类型：  id 为文章分类  name 为文章分类名
        // question:{
        //           ['id'=>,'name'=>,'menu_id'=>,'menu_name'=>''],
        //           ['id'=>,'name'=>'','menu_id'=>,'menu_name'=>],
        //          }
        // article:{
        //          ['id'=>,'name'=>,'menu_id'=>,'menu_name'=>],
        //         }
        $type_id_arr = Menu::getTypeIdInfo($menu_ids);
        //文章同步表中获取文章同步到的位置 需要考虑到 一个站点新建的时候会是空值
        $article_sync_info = Db::name('ArticleSyncCount')->where(['site_id' => $site_id])->field('type_id,type_name,count')->select();
        $article_sync_list = [];
        if ($article_sync_info) {
            foreach ($article_sync_info as $v) {
                if (!array_key_exists($v['type_name'], $article_sync_list)) {
                    $article_sync_list[$v['type_name']] = [];
                }
                $article_sync_list[$v['type_name']][$v['type_id']] = $v;
            }
        }
        $sync_article_data = [];
        foreach ($type_id_arr as $type => $v) {
            foreach ($v as $menu) {
                $max_id = 0;
                if (array_key_exists($type, $article_sync_list)) {
                    $max_id = array_key_exists($menu['id'], $article_sync_list[$type]) ? $article_sync_list[$type][$menu['id']]['count'] : 0;
                }
                if (!array_key_exists($type, $sync_article_data)) {
                    $sync_article_data[$type] = [
                    ];
                }
                array_push($sync_article_data[$type], ['type_id' => $menu['id'], 'menu_id' => $menu['menu_id'], 'menu_name' => $menu['menu_name'], 'max_id' => $max_id]);
            }
        }
        return $sync_article_data;
    }

    /**
     * 获取活动列表
     * @access public
     */
    public static function getActivity($sync_id)
    {
        $where["id"] = ['in', explode(',', $sync_id)];
        $where["status"] = 10;
        $activity = Activity::where($where)->field('id,title,img_name,url,summary')->select();
        $activity_list = [];
        foreach ($activity as $k => $v) {
            $activity = [];
            $activity['name'] = $v['title'];
            $activity['summary'] = $v['summary'];
            $activity['imgsrc'] = "/images/{$v['img_name']}";
            $activity['a_href'] = $v['url'] ?: "/activity/activity{$v['id']}.html";
            $activity_list[] = $activity;
        }
        return $activity_list;
    }

    /**
     * 获取搜索引擎的 referer 不支持百度 谷歌 现仅支持 搜狗 好搜
     * @access public
     * @todo 这个地方有bug 会有安全隐患 太low
     */
    public static function getRefereerDemo()
    {
        return <<<CODE
                <script>
                    var referrer = document.referrer;
                    var sendInfo = {};
                    sendInfo.referrer = referrer;
                    sendInfo.origin_web = window.location.href
                    $(function () {
                        var url = "/index.php/externalAccess";
                        $.ajax({
                                type: "post",
                                url: url,
                                data: sendInfo,
                                success: function () {
                                }
                            }
                        )
                    })
                </script>
CODE;
    }


    /**
     * 获取版本控制　软件
     * ＠access public
     */
    public static function getSiteCopyright($com_name)
    {
        //返回copyright
        return '© 2015-' . date('Y') . '  ' . $com_name . ' All Rights Reserved.';
    }

    /**
     * 获取站点的js 公共代码
     * @access public
     */
    public static function getSiteJsCode($siteinfo)
    {
        //获取公共代码
        list($pre_head_jscode, $after_head_jscode) = self::getCommonCode($siteinfo['public_code']);
        //获取页面pv 操作页面
        $after_head_jscode[] = "<script src='/index.php/pv'></script>";
        //head前后的代码
        $before_head = $siteinfo['before_header_jscode'];
        $after_head = $siteinfo['other_jscode'];
        if ($before_head) {
            array_push($pre_head_jscode, $before_head);
        }
        if ($after_head) {
            array_push($after_head_jscode, $after_head);
        }
        $refere_code = self::getRefereerDemo();
        if ($refere_code) {
            array_push($after_head_jscode, $refere_code);
        }
        $pre_head_js = '';
        foreach ($pre_head_jscode as $v) {
            $pre_head_js = $pre_head_js . $v;
        }
        $after_head_js = '';
        foreach ($after_head_jscode as $v) {
            $after_head_js = $after_head_js . $v;
        }
        return [$pre_head_jscode, $after_head_jscode, $pre_head_js, $after_head_js];
    }

    /**
     * 获取备案信息
     * @access public
     */
    public static function getBeianInfo($siteinfo)
    {
        //公司备案
        $beian_link = 'www.miitbeian.gov.cn';
        $beian = ['beian_num' => '', 'link' => $beian_link];
        $domain_id = $siteinfo['domain_id'];
        if ($domain_id) {
            //这个地方应该也添加缓存
            $domain_info = Cache::remember('domain_info', function () use ($domain_id) {
                return Db::name('domain')->where('id', $domain_id)->find();
            });
            if ($domain_info) {
                $beian_num = $domain_info['filing_num'];
                $beian = ['beian_num' => $beian_num, 'link' => $beian_link];
            }
        }
        return $beian;
    }

    /**
     * 获取站点的logo 相关信息
     * @access private
     * @param $site_info 站点相关数据
     * @return string
     */
    private static function getSiteLogo($site_info)
    {
        $id = $site_info['sitelogo_id'];
        $site_name = $site_info['site_name'];
        if (!$id) {
            return $site_name;
        }
        $site_id = $site_info['id'];
        $site_logoinfo = Cache::remember('sitelogoinfo', function () use ($id) {
            return Db::name('site_logo')->where('id', $id)->find();
        });
        if ($site_logoinfo) {
            $oss_file_path = $site_logoinfo['oss_logo_path'];
            $ext = pathinfo(parse_url($oss_file_path)['path'])['extension'];
            return "<img src='/images/logo{$site_id}.{$ext}' title='$site_name'>";
        }
        return $site_name;
    }


    /**
     * 获取联系人信息
     * @access public
     */
    public static function getContactInfo($siteinfo)
    {
        if(!empty($siteinfo["site_contact"])){
            return $siteinfo["site_contact"];
        }
        $contact_way_id = $siteinfo['support_hotline'];
        $contact_info = [];
        if ($contact_way_id) {
            //缓存中有 则用缓存中的
            $contact_info = Cache::remember('contactway', function () use ($contact_way_id) {
                return Db::name('contactway')->where('id', $contact_way_id)->field('html as contact,detail as title')->find();
            });
        }
        return $contact_info;
    }


    /**
     * 获取外链
     * @access public
     */
    public static function getPatternLink($siteinfo)
    {
        //友链信息
        $link_info = Cache::remember('linkinfo', function () use ($siteinfo) {
            return Db::name('links')->where(['id' => ['in', array_filter(explode(',', $siteinfo['link_id']))]])->field('id,name,domain')->select();
        });
        $partnersite = [];
        foreach ($link_info as $k => $v) {
            $partnersite[$v['domain']] = $v['name'];
        }
        //链轮的类型
        $chain_type = '';
        //该站点需要链接到的站点
        $next_site = [];
        //主站是哪个
        $main_site = [];
        $is_mainsite = $siteinfo['main_site'];
        if ($is_mainsite == '10') {
            //表示不是主站
            //站点类型 用于取出主站 以及链轮类型 来
            $site_type_id = $siteinfo['site_type'];
            list($chain_type, $next_site, $main_site) = Site::getLinkInfo($site_type_id, $siteinfo['id'], $siteinfo['site_name'], $siteinfo['node_id']);
        }
        if ($next_site) {
            $partnersite[$next_site['url']] = $next_site['site_name'];
        }
        if ($main_site) {
            $partnersite[$main_site['url']] = $main_site['site_name'];
        }
        return $partnersite;
    }


    /**
     * 获取 页面中必须的元素
     *
     * @param string $tag index 或者 menu detail
     * @param string $param 如果是  index  第二第三个参数没用
     *                              menu 第二个参数$param表示   $page_id 也就是菜单的英文名 第三个参数 $param2 表示 菜单名 menu_name   $param3 是 menu_id   $param4 表示菜单类型 articlelist newslist  questionlist  productlist
     *                              envmenu 第二个参数$param表示   $page_id 也就是菜单的英文名 第三个参数 $param2 表示 菜单名 menu_name
     *                              detail   第二个参数$param表示  $articletitle 用来获取文章标题 第三个参数 $param2 表示 文章的内容 $param3 表示文章设置的keywords  $param4 是 a_keyword_id  $para5  表示 menu_id  $param6 表示 menu_name $param7 用于生成面包屑的时候 获取 栏目菜单的url
     * @param string $param2
     * @return array
     */
    public static function getEssentialElement($tag = 'index', $param = '', $param2 = '', $param3 = '', $param4 = '', $param5 = '', $param6 = '', $param7 = '')
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        //获取站点的logo
        $logo = self::getSiteLogo($siteinfo);
        $keyword_info = Keyword::getKeywordInfo($siteinfo['keyword_ids'], $site_id, $site_name, $node_id);
        //菜单如果是 详情页面 也就是 文章内容页面  详情类型的 需要 /
        //该站点的网址
        $url = $siteinfo['url'];
        $menu = self::getMenuInfo($siteinfo['menu'], $site_id, $site_name, $node_id, $url, $tag, $param2, $param3);
        //活动创意相关操作
        $activity = self::getActivity($siteinfo['sync_id']);
        //获取站点的类型 手机站的域名 手机站点的跳转链接
        list($m_url, $redirect_code) = self::getMobileSiteInfo();
        $breadcrumb = [];
        switch ($tag) {
            case 'index':
                /**
                 * 关于关键词 改变之后 需要手动到搜索引擎优化部分主动修改关键词
                 */
                $page_id = 'index';
                //然后获取 TDK 等数据  首先到数据库
                list($title, $keyword, $description) = self::getIndexPageTDK($keyword_info, $site_id, $site_name, $node_id, $siteinfo['com_name']);
                //获取首页面包屑
                //Breadcrumb 面包屑
                $breadcrumb = self::getBreadCrumb($tag, $siteinfo['url']);
                break;
            case 'menu':
                //菜单 页面的TDK
                $page_id = $param;
                $menu_name = $param2;
                $menu_id = $param3;
                $type = $param4;
                list($title, $keyword, $description) = self::getMenuPageTDK($keyword_info, $page_id, $menu_name, $site_id, $site_name, $node_id, $menu_id, $menu_name);
                //获取菜单的 面包屑 导航
                //需要注意下 详情型的菜单 没有type
                $breadcrumb = self::getBreadCrumb($tag, $siteinfo['url'], $page_id, $menu_name, $menu_id, $type);
                break;
            case 'detail':
                //详情页面
                $page_id = '';
                //文章的标题
                $articletitle = $param;
                //文章的summary
                $articlecontent = $param2;
                //关键词 文章中的关键词
                $keywords = $param3;
                //该文章所属的父类的a类关键词
                $a_keyword_id = $param4;
                $menu_id = $param5;
                $menu_name = $param6;
                $type = $param7;
                list($title, $keyword, $description) = self::getDetailPageTDK($keyword_info, $site_id, $node_id, $articletitle, $articlecontent, $keywords, $a_keyword_id);
                //获取详情页面的面包屑
                $breadcrumb = self::getBreadCrumb($tag, $siteinfo['url'], $page_id, $menu_name, $menu_id, $type);
                break;
        }
        //获取页面中  会用到的 文章列表 问题列表 零散段落列表
        //配置的菜单信息  用于获取 文章的列表
        //首页获取文章列表改为二十篇
        $artiletype_sync_info = self::getDbArticleListId($siteinfo['menu'], $site_id);
        $limit = $tag == 'index' ? 15 : 10;
        //这个地方其实可以返回更多的信息
        //正常的文章类型
        list($article_list, $article_more) = self::getArticleList($artiletype_sync_info, $site_id, $limit);
        //问答类型
        list($question_list, $question_more) = self::getQuestionList($artiletype_sync_info, $site_id);
        //零散段落类型
        list($scatteredarticle_list, $news_more) = self::getScatteredArticleList($artiletype_sync_info, $site_id);
        //产品类型 列表获取
        list($product_list, $product_more) = self::getProductList($artiletype_sync_info, $site_id);
        //获取友链
        $partnersite = self::getPatternLink($siteinfo);
        //获取公共代码
        list($pre_head_jscode, $after_head_jscode, $pre_head_js, $after_head_js) = self::getSiteJsCode($siteinfo);
        //获取公司联系方式等 会在右上角或者其他位置添加  这个应该支持小后台能自己修改才对
        $contact_info = self::getContactInfo($siteinfo);
        //获取备案信息
        $beian = self::getBeianInfo($siteinfo);
        $tdk = self::form_tdk_html($title, $keyword, $description);
        $share = self::get_share_code();
        //公司名称
        $com_name = $siteinfo['com_name'];
        //版本　copyright
        $copyright = self::getSiteCopyright($com_name);
        $site_name = $siteinfo['site_name'];
        //其中tdk是已经嵌套完成的html代码title keyword description为单独的代码。
        return compact('breadcrumb', 'com_name', 'url', 'site_name', 'logo', 'contact_info', 'beian', 'copyright', 'tdk', 'title', 'keyword', 'description', 'share', 'm_url', 'redirect_code', 'menu', 'activity', 'partnersite', 'pre_head_jscode', 'after_head_jscode', 'pre_head_js', 'after_head_js', 'article_list', 'question_list', 'scatteredarticle_list', 'product_list', 'article_more', 'question_more', 'news_more', 'product_more');
    }


    /**
     * 获取活动页面需要的信息
     * @access public
     */
    public static function getActivityEssentialElement($siteinfo, $id)
    {
        $logo = self::getSiteLogo($siteinfo);
        //title keywords description
        $data = Activity::Where('id', '=', $id)->find();
        if (!$data) {
            return;
        }
        //没有找到的情况
        if ($data['status'] != '10') {
            return false;
        }
        if (!$data) {
            return false;
        }
        if ($data['url']) {
            return ['data' => $data];
        }
        $data = $data->toArray();
        $title = $data['title'];
        $keyword = $data['keywords'];
        $description = $data['summary'];
        //获取单独的tdk数据
        $tdk = self::form_tdk_html($title, $keyword, $description);
        //获取公司联系方式等 会在右上角或者其他位置添加  这个应该支持小后台能自己修改才对
        $contact_info = self::getContactInfo($siteinfo);
        //公司名称
        $com_name = $siteinfo['com_name'];
        //版本　copyright
        $copyright = self::getSiteCopyright($com_name);
        $site_name = $siteinfo['site_name'];
        //获取公共代码
        list($pre_head_jscode, $after_head_jscode, $pre_head_js, $after_head_js) = self::getSiteJsCode($siteinfo);
        //分享链接
        $share = self::get_share_code();
        //公司备案
        $beian = self::getBeianInfo($siteinfo);
        return compact('data', 'com_name', 'site_name', 'contact_info', 'beian', 'copyright', 'logo', 'tdk', 'title', 'keyword', 'description', 'share', 'menu', 'partnersite', 'pre_head_jscode', 'after_head_jscode', 'pre_head_js', 'after_head_js');
    }


    /**
     * 获取页面的分享代码
     * @access private
     */
    private static function get_share_code()
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
        window._bd_share_config={"common":{"bdSnsKey":{},"bdText":"","bdMini":"2","bdMiniList":false,"bdPic":"","bdStyle":"0","bdSize":"16"},"share":{}};with(document)0[(getElementsByTagName('head')[0]||body).appendChild(createElement('script')).src='http://bdimg.share.baidu.com/static/api/js/share.js?v=89860593.js?cdnversion='+~(-new Date()/36e5)];
    </script>
code;
    }


    /**
     * 生成tdk 相关html
     * @access private
     */
    private static function form_tdk_html($title, $keyword, $description)
    {
        $title_template = "<title>%s</title>";
        $keywords_template = "<meta name='keywords' content='%s'>";
        $description_template = "<meta name='description' content='%s'>";
        return sprintf($title_template, $title) . sprintf($keywords_template, $keyword) . sprintf($description_template, $description);
    }


    /**
     * 获取当前栏目的菜单信息
     * @access private
     * @todo 如果是menu或者是index  需要优化当前栏目比如前段需要表示出来当前页 并且给出有区别的样式
     * @param $menu_ids 栏目的ids
     * $param $site_id 站点的id
     * $param $site_name 站点的name
     * $param $node_id 节点的id
     * $param $url 网站的根目录
     * $param $tag 标志  index、menu、 envmenu、detail
     * $param $generate_name 生成菜单的英文名
     * $param $menu_id 菜单的id
     * @return array|false|\PDOStatement|string|\think\Collection
     */
    private static function getMenuInfo($menu_ids, $site_id, $site_name, $node_id, $url, $tag, $generate_name, $menu_id)
    {
        //需要把首页链接追加进来 而且需要在首位
        $menu = Menu::getMergedMenu($menu_ids, $site_id, $site_name, $node_id);
        array_unshift($menu, ['id' => 0, 'name' => '首页', 'title' => $site_name, 'generate_name' => $url]);
        //这个地方还需要当前menu 给出提示
        switch ($tag) {
            case'index':
                foreach ($menu as $k => $v) {
                    if ($v['name'] == '首页') {
                        $v['actived'] = true;
                    } else {
                        $v['actived'] = false;
                    }
                    $menu[$k] = $v;
                }
                break;
            case'menu':
                foreach ($menu as $k => $v) {
                    if ($v['id'] == $menu_id) {
                        $v['actived'] = true;
                    } else {
                        $v['actived'] = false;
                    }
                    $menu[$k] = $v;
                }
                break;
        }
        return $menu;
    }

    /**
     * 截取中文字符串  utf-8
     * @param String $str 要截取的中文字符串
     * @param $len
     * @return mixed
     */
    public static function utf8chstringsubstr($str, $len)
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
     */
    public static function getBreadCrumb($tag, $url, $page_id = '', $menu_name = '', $menu_id = 0, $type = '')
    {
        $breadcrumb = [
            ['text' => '首页', 'href' => $url],
        ];
        switch ($tag) {
            case 'index':
                break;
            case 'menu':
                //菜单 页面的TDK
                if ($type) {
                    array_push($breadcrumb, ['text' => $menu_name, 'href' => $url . '/' . $type . '/' . $menu_id . '.html']);
                } else {
                    array_push($breadcrumb, ['text' => $menu_name, 'href' => $url . '/' . $page_id . '.html']);
                }
                break;
            case 'envmenu':
                //.env 文件中的配置菜单信息
                array_push($breadcrumb, ['text' => $menu_name, 'href' => $url . '/' . $page_id . '.html']);
                break;
            case 'detail':
                //详情页面
                array_push($breadcrumb, ['text' => $menu_name, 'href' => $url . '/' . $type . '/' . $menu_id . '.html']);
                break;
        }
        return $breadcrumb;
    }


}
