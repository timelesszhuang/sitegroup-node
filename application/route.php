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

// 文件管理
Route::rule('filemanage/uploadFile', 'tool/Filemanage/uploadFile');

//只有当后缀是 html 的元素的时候才会有
//当请求文章列表 的时候 首先隐藏 index.php
Route::get('newslist/:id', 'index/NewsList/index', ['ext' => 'html']);
//当请求列表 的时候 首先隐藏 index.php
Route::get('questionlist/:id', 'index/QuestionList/index', ['ext' => 'html']);
//当请求列表 的时候 首先隐藏 index.php
Route::get('articlelist/:id', 'index/ArticleList/index', ['ext' => 'html']);

//全部页面静态化
Route::get('allstatic', 'tool/Pagestaticentry/allstatic');
//首页静态化
Route::get('indexstatic', 'tool/Pagestaticentry/indexstatic');
//菜单静态化  包含 详情型 菜单  env类型菜单
Route::get('menustatic', 'tool/Pagestaticentry/menustatic');
//文章页面静态化
Route::get('artilestatic', 'tool/Pagestaticentry/articlestatic');

//清除缓存 默认使用文件缓存
Route::get('clearCache', 'tool/Commontool/clearCache');