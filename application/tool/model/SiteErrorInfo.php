<?php
/**
 * 站点错误信息 常见的操作 出现错误之后会推送到 后台中  后台中可以看到错误信息
 * Created by PhpStorm.
 * User: timeless
 * Date: 17-5-15
 * Time: 下午17:25
 */

namespace app\tool\model;

use think\Model;


class SiteErrorInfo extends Model
{

    /**
     * 添加错误数据
     * @access public
     */
    public function addError($info)
    {
        $this->save($info);
    }


}