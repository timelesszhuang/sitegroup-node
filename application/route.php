<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\Route;


//只有当后缀是 html 的元素的时候才会有
//当请求文章列表 的时候 首先隐藏 index.php
Route::get('newslist/:id', 'index/NewsList/index', ['ext' => 'html']);
//当请求列表 的时候 首先隐藏 index.php
Route::get('questionlist/:id', 'index/QuestionList/index', ['ext' => 'html']);
//当请求列表 的时候 首先隐藏 index.php
Route::get('articlelist/:id', 'index/ArticleList/index', ['ext' => 'html']);


//模板文件 活动文件管理
Route::rule('filemanage/uploadFile', 'tool/Filemanage/uploadFile');

//模板文件列表相关操作
Route::get('templatelist', 'tool/Template/templatelist');
//读取模板文件
Route::get('templateread', 'tool/Template/templateread');
//更新模板文件
Route::post('templateupdate', 'tool/Template/templateupdate');
//添加新模板文件
Route::post('templateadd', 'tool/Template/templateadd');


//全部页面静态化
Route::get('crontabstatic', 'tool/Pagestaticentry/crontabstatic');
//全部页面静态化
Route::get('allstatic', 'tool/Pagestaticentry/allstatic');
//首页静态化
Route::get('indexstatic', 'tool/Pagestaticentry/indexstatic');
//菜单静态化  包含 详情型 菜单  env类型菜单
Route::get('menustatic', 'tool/Pagestaticentry/menustatic');
//文章页面静态化
Route::get('articlestatic', 'tool/Pagestaticentry/articlestatic');
//清除缓存 默认使用文件缓存
Route::get('clearCache', 'tool/Commontool/clearCache');
//页面 pv 操作 每个页面获取下
Route::get('pv', 'tool/Site/pv');
Route::post('Rejection', 'tool/Site/Rejection');
//自定义表单提交
Route::post('DefinedRejection', 'tool/Site/DefinedRejection');
//统计
Route::resource('externalAccess', 'index/ExternalAccess');
Route::resource('Ceshi', 'tool/Ceshi');
Route::post('ceshi', 'tool/Ceshi/ceshi');
Route::get('sitemap', 'tool/SiteMap/index');