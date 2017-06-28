<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-5-22
 * Time: 下午2:29
 */

return [
    //节点的id 信息部署的时候需要修改该id 的值
    'SITE_ID' => 5,
    //缓存十分钟
    'CACHE_TIME' => 600,
    //缓存的文件的列表
    'CACHE_LIST' => [
        //
        'SITEINFO' => 'siteinfo',
        //菜单
        'MENU' => 'menu',
        //关键词
        'KEYWORD' => 'keyword',
        //该配置主要用于 获取菜单选择的 文章或者 零散文章 问答文章的分类
        'MENUTYPEID' => 'menutypeid',
        //手机站点 网址  还有跳转链接
        'MOBILE_SITE_INFO' => 'mobile_site_info',
        //
    ],
];