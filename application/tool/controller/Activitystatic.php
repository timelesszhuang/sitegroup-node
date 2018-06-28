<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-11-9
 * Time: 下午2:07
 * @todo 还有更新活动的时候
 */

namespace app\tool\controller;

use app\common\controller\Common;
use think\Config;
use think\View;


class Activitystatic extends Common
{

    //公共操作对象
    public $commontool;

    public function __construct()
    {
        parent::__construct();
        $this->commontool = new Commontool();
        $this->commontool->tag = 'activity';
    }


    /**
     * 获取活动的内容
     * @access public
     */
    public function getacticitycontent($id)
    {
        $ac_data = (new \app\tool\model\Activity)->Where('id', '=', $id)->find();
        if (!$ac_data) {
            $this->go404();
        }
        //当前id的活动信息
        $water = $this->waterString;
        $img_water = $this->waterImgUrl;
        if ($ac_data['url']) {
            //表示是其他网页的链接不需要静态化页面 只需要静态化oss 相关的图片
            if ($ac_data['img_name']) {
                $this->get_osswater_img($ac_data['oss_img_src'], $ac_data['img_name'], $water, $img_water);
            }
            return;
        }
        $title = $ac_data['title'];
        $keyword = $ac_data['keywords'];
        $description = $ac_data['summary'];
        //获取相关活动的 ativity
        $assign_data = $this->commontool->getEssentialElement($title, $keyword, $description);
        $imgser = $ac_data['imgser'];
        //多张图片
        $local_img = [];
        if ($imgser) {
            $imglist = unserialize($imgser);
            //静态化图片
            $local_img = $this->form_imgser_img($imglist, $water, $img_water);
        }
        //单张大图
        if ($ac_data['img_name']) {
            $this->get_osswater_img($ac_data['oss_img_src'], $ac_data['img_name'], $water, $img_water);
        }
        if ($ac_data['small_img_name']) {
            $this->get_osswater_img($ac_data['smalloss_img_src'], $ac_data['small_img_name'], $water, $img_water);
        }
        $ac_data['imglist'] = $local_img;
        //还需要 存储在数据库中 相关数据
        //页面中还需要填写隐藏的 表单 node_id site_id
        $data = [
            'd' => $assign_data,
            'activity' => $ac_data
        ];
        $content = $this->Debug((new View())->fetch($this->activitytemplatepath,
            $data
        ), $data);
        return $content;
    }


    /**
     * 生成产品的多张图片
     * @access private
     */
    private function form_imgser_img($img_arr, $water, $img_water = "")
    {
        $endpoint = Config::get('oss.endpoint');
        $bucket = Config::get('oss.bucket');
        $local_imgarr = [];
        foreach ($img_arr as $k => $v) {
            $endpointurl = sprintf("https://%s.%s/", $bucket, $endpoint);
            //表示链接不存在
            $imgname = $v['imgname'];
            $osssrc = $v['osssrc'];
            if (strpos($osssrc, $endpointurl) === false) {
                array_push($local_imgarr, $osssrc);
                continue;
            }
            if ($this->get_osswater_img($osssrc, $imgname, $water, $img_water)) {
                array_push($local_imgarr, '/images/' . $imgname);
            }
        }
        return $local_imgarr;
    }


}