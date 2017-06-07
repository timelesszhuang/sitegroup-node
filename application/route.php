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

//清理缓存
Route::rule('clearcache', 'tool/Commontool/clearCache');

//页面静态化第一次 的入口 无需传递数据
Route::rule('pagestaticentry', 'tool/Pagestaticentry/index');

//只有当后缀是 html 的元素的时候才会有
//当请求文章列表 的时候 首先隐藏 index.php
Route::get('newslist/:id', 'index/NewsList/index', ['ext' => 'html']);

//当请求列表 的时候 首先隐藏 index.php
Route::get('questionlist/:id', 'index/QuestionList/index',['ext' => 'html']);

//当请求列表 的时候 首先隐藏 index.php
Route::get('articlelist/:id', 'index/ArticleList/index',['ext' => 'html']);

Route::get('generateStatic','tool/Detailstatic/index');