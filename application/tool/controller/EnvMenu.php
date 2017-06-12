<?php

namespace app\tool\controller;

use app\common\controller\Common;


/**
 * 首页静态化
 * 执行首页静态化相关操作
 */
class EnvMenu extends Common
{

    /**
     * 获取 .env 中的配置信息
     * @access public
     */
    public static function getEnv()
    {
        $template_env = ROOT_PATH . 'public/template/.env';
        $menu_info = self::read($template_env);
        return $menu_info;
    }


    /**
     * 读取配置文件相关数据
     * @access public
     * @todo 前边两行 省略 主要作用是 相关解释
     */
    public static function read($file_name)
    {
        //首先判断下是不是文件存在
        if (!file_exists($file_name)) {
            return [];
        }
        $fp = fopen($file_name, 'r');
        $i = 0;
        $menu_info = [];
        while (!feof($fp)) {
            if ($i == 0 || $i == 1) {
                fgets($fp, 4096);
            } else {
                $buffer = trim(fgets($fp, 4096));
                if ($buffer) {
                    //菜单相关设置
                    list($menu_name, $name, $title) = array_filter(explode('|', $buffer));
                    $menu_info[] = [
                        'generate_name' => $menu_name,
                        'title' => $title,
                        'name' => $name,
                    ];
                }
            }
            $i++;
        }
        fclose($fp);
        return $menu_info;
    }

}
