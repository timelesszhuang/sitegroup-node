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
//请求产品列表的时候
Route::get('productlist/:id', 'index/ProductList/index', ['ext' => 'html']);

//新支持的页面预览功能
Route::get('preview/:type/:id', 'index/Preview/preview', ['ext' => 'html']);
//相关的标签列表
Route::get('tag/:id', 'index/TagList/tag', ['ext' => 'html']);
//栏目首页相关
Route::get('index', 'index/Detailenter/index', ['ext' => 'html']);
//文章页面入口
Route::get('article/:id', 'index/Detailenter/article', ['ext' => 'html']);
//问答页面入口
Route::get('question/:id', 'index/Detailenter/question', ['ext' => 'html']);
//产品页面入口
Route::get('product/:id', 'index/Detailenter/product', ['ext' => 'html']);
//活动相关整理完善
Route::get('activity/:id', 'index/Detailenter/activity', ['ext' => 'html']);
//查询
Route::get('search', 'index/Query/index');
//区域id
Route::get('district', 'index/Detailenter/district', ['ext' => 'html']);
//站点地图
Route::get('sitemap', 'tool/SiteMap/index',['ext' => 'xml']);

//模板的其他操作
//模板文件 活动文件管理
Route::get('filemanage/uploadFile/:id', 'tool/Filemanage/uploadFile');

//安全漏洞 需要执行验证是不是有权限之类
//模板文件列表相关操作
Route::get('templatelist', 'tool/Template/templatelist');
//读取模板文件
Route::get('templateFileRead', 'tool/Template/templateread');
//文件重命名
Route::get('templateFileRename', 'tool/Template/templatefilerename');
//模板文件相关处理
Route::get('manageTemplateFile', 'tool/Template/manageTemplateFile');
//下载相关链接
Route::get('downloadtemplatefile', 'tool/DownloadTemplate/downloadtemplatefile');

//整站生成 每一次只会生成指定数量的文章
Route::get('allstatic', 'tool/Pagestaticentry/allstatic');
//整站重置 网站恢复到第一次的时候 然后生成指定数量的文章
Route::get('resetall', 'tool/Pagestaticentry/resetall');

//清除缓存 默认使用文件缓存
Route::get('clearCache', 'tool/Commontool/clearCache');
//页面 pv 操作 每个页面获取下
Route::post('Rejection', 'tool/Site/Rejection');
//自定义表单提交
Route::post('DefinedRejection', 'tool/Site/DefinedRejection');
