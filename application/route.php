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
//查询
Route::get('search', 'index/Query/index');
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


//全部页面静态化
Route::get('crontabstatic', 'tool/Pagestaticentry/crontabstatic');
//整站生成 每一次只会生成指定数量的文章
Route::get('allstatic', 'tool/Pagestaticentry/allstatic');
//整站重置 网站恢复到第一次的时候 然后生成指定数量的文章
Route::get('resetall', 'tool/Pagestaticentry/resetall');
//从头全部重新生成
Route::get('allsitestatic', 'tool/Pagestaticentry/allsitestatic');
//首页静态化
Route::get('indexstatic', 'tool/Pagestaticentry/indexstatic');
//菜单静态化  包含 详情型 菜单  env类型菜单
Route::get('menustatic', 'tool/Pagestaticentry/menustatic');
//文章页面静态化
Route::get('articlestatic', 'tool/Pagestaticentry/articlestatic');
//
Route::get('sitemap', 'tool/SiteMap/index');

//清除缓存 默认使用文件缓存
Route::get('clearCache', 'tool/Commontool/clearCache');
//页面 pv 操作 每个页面获取下
Route::post('Rejection', 'tool/Site/Rejection');
//自定义表单提交
Route::post('DefinedRejection', 'tool/Site/DefinedRejection');

//重新生成文章 根据id等信息 重新生成其他各类数据
//前端修改某篇文章之后修改
Route::post('generateHtml', 'tool/Pagestaticentry/reGenerateHtml');
// 获取某个已经静态化的文章产品 问答之后的html 路由定义有问题
Route::get('getStaticOne/:type/:name', 'tool/Pagestaticentry/staticOneHtml');
// 修改已经生成的html 文章产品问答 代码 单页 路由定义有问题
Route::post('generateOne/:type/:name', 'tool/Pagestaticentry/generateOne');

//重新生成单个活动页面
Route::get('regenerateactivity', 'tool/Activitystatic/restatic');

