<?php

namespace app\tool\controller;

use app\common\controller\Common;
use app\tool\model\SiteErrorInfo;
use think\Cache;
use think\Config;
use think\Db;


/**
 * 关键词 相关操作 关键词相关设置
 */
class Keyword extends Common
{

    /**
     * 获取 关键词
     * @access public
     * @param string $aKeyword 关键词id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getKeywordInfo($aKeyword_ids, $site_id, $site_name, $node_id)
    {
        //首先从缓存中获取数据 缓存中没有的话 再到数据库中获取
        if ($keyword = Cache::get(Config::get('site.CACHE_LIST')['KEYWORD'])) {
            return $keyword;
        }
        $field = 'id,name,parent_id,path,tag';
        $keyword = [];
        //获取全部的a类 客户
        $aKeyword_ids_arr = array_filter(explode(',', $aKeyword_ids));
        $aKeyword = Db::name('keyword')->where(['id' => ['in', $aKeyword_ids_arr]])->field($field)->select();
        //获取全部的 其他的 a类 b类 c类 关键词
        $keywordModel = Db::name('keyword');
        foreach ($aKeyword_ids_arr as $k => $v) {
            if ($k == 0) {
                $keywordModel->where('path', 'like', "%,$v,%");
            } else {
                $keywordModel->whereOr('path', 'like', "%,$v,%");
            }
        }
        //获取 a类 下的bc 类关键词
        $bcKeyword = $keywordModel->order('tag asc')->field($field)->select();
        if (empty($bcKeyword)) {
            //如果 bc 类关键词没有的话 应该提示 bc 类关键词不足等
            $site_info = new SiteErrorInfo();
            $site_info->addError([
                'msg' => "{$site_name}站点所选择的关键词没有设置BC类关键词。",
                'operator' => '页面静态化',
                'site_id' => $site_id,
                'site_name' => $site_name,
                'node_id' => $node_id,
            ]);
            //错误信息
            exit;
        }
        //处理下 关键词 分出 B 类 C 类来
        $keyword = (new Common())->list_to_tree(array_merge($aKeyword, $bcKeyword), 'id', 'parent_id', 'children', $parent_id = 0);
        //利用文件缓存缓存下文件
        Cache::set(Config::get('site.CACHE_LIST')['KEYWORD'], $keyword, Config::get('site.CACHE_TIME'));
        return $keyword;
    }




}
