<?php
/**
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-11-9
 * Time: 下午2:07
 * @todo 还有更新活动的时候
 */

namespace app\tool\controller;


use app\tool\model\Activity;
use app\tool\traits\FileExistsTraits;
use app\tool\traits\Osstrait;
use think\Config;
use think\View;


class Activitystatic
{
    use FileExistsTraits;
    use Osstrait;

    private $activity_path = 'activity/%s.html';

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
        if (!$this->fileExists('template/activity.html')) {
            //只需要静态化下图片就可以
            $activity_status = false;
        }
        $ids = array_filter(explode(',', $activity_ids));
        //修改之后怎么处理
        foreach ($ids as $activity_id) {
            if (!$activity_status) {
                $this->staticImg($siteinfo, $activity_id);
                continue;
            }
            //判断下是不是有相关文件静态文件
            if (file_exists(sprintf($this->activity_path, $activity_id))) {
                //已经静态化的直接跳过
                return;
            }
            $this->staticOne($siteinfo, $activity_id);
        }
    }

    /**
     * 修改单个活动之后重新生成操作
     * @access public
     */
    public function restatic($id)
    {
        $siteinfo = Site::getSiteInfo();
        // 判断模板是否存在  如果模板不存在的话怎么处理
        if (!$this->fileExists('template/activity.html')) {
            //如果没有模板的情况
            $this->staticImg($siteinfo, $id);
        }
        $this->staticOne($siteinfo, $id);
    }

    /**
     * 只需要静态化图片
     * @param $id id数据
     * @access public
     */
    public function staticImg($siteinfo, $id)
    {
        $data = Activity::Where('id', '=', $id)->field('oss_img_src,img_name')->find();
        if ($data) {
            $data = $data->toArray();
            $img_name = $data['img_name'];
            $oss_img_src = $data['oss_img_src'];
            $this->get_osswater_img($oss_img_src, $img_name, $siteinfo['walterString']);
        }
    }


    /**
     * 静态化一个文件
     * @access public
     */
    public function staticOne($siteinfo, $id)
    {
        //获取活动 ativity
        $assign_data = Commontool::getActivityEssentialElement($siteinfo, $id);
        //当前id的活动信息
        $a_data = $assign_data['data'];
        unset($assign_data['data']);
        $imgser = $a_data['imgser'];
        $water = $siteinfo['walterString'];
        //多张图片
        $local_img = [];
        if ($imgser) {
            $imglist = unserialize($imgser);
            //静态化图片
            $local_img = $this->form_imgser_img($imglist, $water);
        }
        //单张大图
        if ($a_data['img_name']) {
            $this->get_osswater_img($a_data['oss_img_src'], $a_data['img_name'], $water);
        }
        $a＿data['imglist'] = $local_img;
        //还需要 存储在数据库中 相关数据
        //页面中还需要填写隐藏的 表单 node_id site_id
        $content = (new View())->fetch('template/activity.html', ['d' => $assign_data, 'activity' => $a_data]);
        //判断目录是否存在
        file_put_contents('activity/activity' . $id . '.html', chr(0xEF) . chr(0xBB) . chr(0xBF) . $content);
    }


    /**
     * 生成产品的多张图片
     * @access private
     */
    private function form_imgser_img($img_arr, $water)
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
            if ($this->get_osswater_img($osssrc, $imgname, $water)) {
                array_push($local_imgarr, '/images/' . $imgname);
            }
        }
        return $local_imgarr;
    }


}