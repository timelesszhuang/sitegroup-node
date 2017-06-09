<?php
/**
 * Created by PhpStorm.
 * User: qiangbi
 * Date: 17-6-8
 * Time: 下午3:18
 */

namespace app\tool\controller;

use app\tool\model\SiteErrorInfo;

trait FileExistsTraits
{

    /**
     * 检测文件是否存在 并写入数据库
     * @param $filename
     * @return bool
     */
    public function fileExists($filename)
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        if (!file_exists($filename)) {
            (new SiteErrorInfo)->addError([
                'msg' => "{$site_name}站点" . $filename . "模板不存在!",
                'operator' => '模板不存在',
                'site_id' => $site_id,
                'site_name' => $site_name,
                'node_id' => $node_id,
            ]);
            return false;
        }
        return true;
    }

}