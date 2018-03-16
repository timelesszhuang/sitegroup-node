<?php
/**
 * Created by PhpStorm.
 * oss 相关操作封装
 * User: 赵兴壮
 * Date: 17-6-12
 * Time: 上午9:44
 */

namespace app\tool\traits;


use app\tool\controller\Menu;

trait Template
{
    /**
     * 获取详情页的template
     */
    public function getTemplate($type, $menu_id, $flag = '')
    {
        $menu_info =(new Menu())->getMergedMenu($this->menu_ids, $this->site_id, $this->site_name, $this->node_id);
        switch ($type) {
            case 'detail':
                //详情
                switch ($flag) {
                    case'article':
                        $template = $this->articletemplatepath;
                        break;
                    case 'question':
                        $template = $this->questiontemplatepath;
                        break;
                    case 'product':
                        $template = $this->producttemplatepath;
                        break;
                    case 'news':
                        break;
                }
                if ($menu_id && array_key_exists($menu_id, $menu_info)) {
                    $current_menu_info = $menu_info[$menu_id];
                    $db_template_name = $current_menu_info['detailtemplate'];
                    $db_template_path = "template/{$db_template_name}";
                    if ($db_template_name && file_exists($db_template_path)) {
                        //设置模板且模板存在
                        $template = $db_template_path;
                    } else {

                    }
                }
                return $template;
                break;
            case 'list':
                //列表
                switch ($flag) {
                    case'article':
                        $template = $this->articlelisttemplate;
                        break;
                    case 'question':
                        $template = $this->questionlisttemplate;
                        break;
                    case 'product':
                        $template = $this->productlisttemplate;
                        break;
                    case 'news':
                        break;
                }
                if ($menu_id && array_key_exists($menu_id, $menu_info)) {
                    $current_menu_info = $menu_info[$menu_id];
                    $db_template_name = $current_menu_info['listtemplate'];
                    $db_template_path = "template/{$db_template_name}";
                    if ($db_template_name && file_exists($db_template_path)) {
                        //设置模板且模板存在
                        $template = $db_template_path;
                    }
                }
                return $template;
                break;
            case 'cover':
                $template = '';
                //详情型菜单 怎么处理
                if ($menu_id && array_key_exists($menu_id, $menu_info)) {
                    $current_menu_info = $menu_info[$menu_id];
                    $template = "template/{$current_menu_info['generate_name']}.html";
                    $db_template_name = $current_menu_info['covertemplate'];
                    $db_template_path = "template/{$db_template_name}";
                    if ($db_template_name && file_exists($db_template_path)) {
                        //设置模板且模板存在
                        $template = $db_template_path;
                    }
                }
                return $template;
                break;
        }
    }

    /**
     * 获取文章内容标签的相关tag
     * @access public
     * @todo 后期建议改为自定义设置taglist
     */
    public function getTagTemplate($type)
    {
        switch ($type) {
            case 'article':
                return $this->articletaglisttemplate;
                break;
            case 'question':
                return $this->articletaglisttemplate;
                break;
            case 'product':
                return $this->articletaglisttemplate;
                break;
        }

    }


}