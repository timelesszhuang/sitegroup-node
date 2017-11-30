<?php
/**
 * Created by PhpStorm.
 * oss 相关操作封装
 * User: 赵兴壮
 * Date: 17-6-12
 * Time: 上午9:44
 */

namespace app\tool\traits;

use think\Config;

trait Params
{

    /**
     * 分析 列表参数
     */
    public function analyseParams($param)
    {
        //每一个node下的菜单的英文名不能包含重复的值
        //根据_ 来分割 第一个参数表示 菜单的id_t文章分类的typeid_p页码id.html
        $params_info = explode('_', $param);
        if (count($params_info) == 1) {
            $menu_enname = $params_info[0];
            //没有传递文章分类的情况 默认为0 取出全部的文章分类
            $type_id = 0;
            //如果只有菜单id的话
            $currentpage = 1;
        } elseif (count($params_info) == 2) {
            $menu_enname = $params_info[0];
            if (strpos($params_info[1], 't') === false) {
                //表示第二个参数是 page
                $currentpage = substr($params_info[1], 1, strlen($params_info[1]));
                $type_id = 0;
            } else {
                $type_id = substr($params_info[1], 1, strlen($params_info[1]));
                $currentpage = 1;
            }
        } elseif (count($params_info) == 3) {
            $menu_enname = $params_info[0];
            $type_id = substr($params_info[1], 1, strlen($params_info[1]));
            $currentpage = substr($params_info[2], 1, strlen($params_info[1]));
        }
        return [$menu_enname, $type_id, $currentpage];
    }

}