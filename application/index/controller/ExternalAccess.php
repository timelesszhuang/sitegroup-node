<?php

namespace app\index\controller;

use app\index\model\AccessKeyword;
use app\index\model\BrowseRecord;
use app\tool\controller\Site;
use think\Controller;
use think\Request;

class ExternalAccess extends Controller
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        //
    }

    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create()
    {
        //
    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $obj = [];
        $referer = $request->post('referrer');
        $origin_web = $request->post('origin_web');
        if (stripos($referer, 'www.sogou.com')) {
            $arr = explode('&', $referer);
            foreach ($arr as $k => $v) {
                $refererdata = explode('=', $v);
                $obj = [
                    $refererdata[0] => $refererdata[1]
                ];
            }
            $keyword = urldecode($obj['query']);
            $engine = "sogou";
        } else if (stripos($referer, 'www.so.com')) {
            $arr = explode('&', $referer);
            foreach ($arr as $k => $v) {
                $refererdata = explode('=', $v);
                $obj = [
                    $refererdata[0] => $refererdata[1]
                ];
            }
            $keyword = urldecode($obj['query']);
            $engine = "haosou";
        } else if (stripos($referer, 'www.baidu.com')) {
            $keyword = "百度关键词";
            $engine = "baidu";
        }
        $siteinfo = Site::getSiteInfo();
        $data = [
            'keyword' => $keyword,
            'referrer' => $referer,
            'engine' => $engine,
            'origin_web'=>$origin_web,
            'node_id' =>$siteinfo['node_id'],
            'site_id' => $siteinfo['id'],
        ];
        $browse = new BrowseRecord($data);
        $browse->allowField(true)->save();


        if (!empty($keyword)) {
            $where = [
                "keyword" => $keyword,
                "site_id" => $data['site_id'],
                "node_id" => $data['node_id']
            ];
            $access = AccessKeyword::where($where)->find();
            if ($access) {
                $access->count = ++$access->count;
                $access->save();
                return;
            }
            $access_model = new AccessKeyword($data);
            $access_model->allowField(true)->save();
        }
    }

    /**
     * 显示指定的资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function read($id)
    {
        //
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param  int $id
     * @return \think\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request $request
     * @param  int $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * 删除指定资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //
    }
}
