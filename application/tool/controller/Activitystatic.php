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
use app\tool\model\Activity;
use think\Config;
use think\View;


class Activitystatic extends Common
{


    /**
     * 活动页面静态化
     * @access public
     */
    public function index()
    {
        // 首先得获取到没有静态化的活动
        $siteinfo = Site::getSiteInfo();
        $activity_ids = $siteinfo['sync_id'];
        if (!$activity_ids) {
            return;
        }
        $activity_status = true;
        // 如果活动模板不存在则找些优化的地方  如果模板不存在的话怎么处理
        if (!file_exists($this->activitytemplatepath)) {
            //只需要静态化下图片就可以
            $activity_status = false;
        }
        $ids = array_filter(explode(',', $activity_ids));
        //修改之后怎么处理
        foreach ($ids as $activity_id) {
            if (!$activity_status) {
                $this->staticImg($activity_id);
                continue;
            }
            $this->staticOne($activity_id);
        }
    }

    /**
     * 修改单个活动之后重新生成操作
     * @access public
     */
    public function restatic($id)
    {
        // 判断模板是否存在  如果模板不存在的话怎么处理
        if (!file_exists($this->activitytemplatepath)) {
            //如果没有模板的情况
            $this->staticImg($id);
            return;
        }
        $this->staticOne($id);
    }

    /**
     * 只需要静态化图片
     * @param $id id数据
     * @access public
     */
    public function staticImg($id)
    {
        //取出启用的活动
        $data = Activity::Where('id', '=', $id)->field('oss_img_src,img_name,smalloss_img_src,small_img_name')->find();
        if ($data) {
            $data = $data->toArray();
            $img_name = $data['img_name'];
            $oss_img_src = $data['oss_img_src'];
            $this->get_osswater_img($oss_img_src, $img_name, $this->waterString ,$this->waterImgUrl);
            $smallimg_name = $data['small_img_name'];
            $smalloss_img_src = $data['smalloss_img_src'];
            if ($smallimg_name) {
                $this->get_osswater_img($smalloss_img_src, $smallimg_name, $this->waterString,$this->waterImgUrl);
            }
        }
    }


    /**
     * 静态化一个文件
     * @access public
     */
    public function staticOne($id)
    {
        $ac_data = Activity::Where('id', '=', $id)->find();
        if (!$ac_data) {
            return;
        }
        //当前id的活动信息
        $water = $this->waterString;
        $img_water = $this->waterString;
        if ($ac_data['url']) {
            //表示是其他网页的链接不需要静态化页面 只需要静态化oss 相关的图片
            if ($ac_data['img_name']) {
                $this->get_osswater_img($ac_data['oss_img_src'], $ac_data['img_name'], $water ,$img_water);
            }
            return;
        }
        $title = $ac_data['title'];
        $keyword = $ac_data['keywords'];
        $description = $ac_data['summary'];
        //获取相关活动的 ativity
        $assign_data = Commontool::getEssentialElement('activity', $title, $keyword, $description);
        $imgser = $ac_data['imgser'];
        //多张图片
        $local_img = [];
        if ($imgser) {
            $imglist = unserialize($imgser);
            //静态化图片
            $local_img = $this->form_imgser_img($imglist, $water,$img_water);
        }
        //单张大图
        if ($ac_data['img_name']) {
            $this->get_osswater_img($ac_data['oss_img_src'], $ac_data['img_name'], $water ,$img_water);
        }
        $ac_data['imglist'] = $local_img;
        //还需要 存储在数据库中 相关数据
        //页面中还需要填写隐藏的 表单 node_id site_id
        $data = [
            'd' => $assign_data,
            'activity' => $ac_data
        ];
        $content = Common::Debug((new View())->fetch($this->activitytemplatepath,
            $data
        ), $data);
        //判断目录是否存在
        $ac_path = sprintf($this->activitypath, $id);
        if (file_put_contents($ac_path, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content)) {
            //添加到缓存中
            $this->urlsCache([$this->siteurl . '/' . $ac_path]);
        }
    }


    /**
     * 生成产品的多张图片
     * @access private
     */
    private function form_imgser_img($img_arr, $water,$img_water="")
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
            if ($this->get_osswater_img($osssrc, $imgname, $water,$img_water)) {
                array_push($local_imgarr, '/images/' . $imgname);
            }
        }
        return $local_imgarr;
    }


}