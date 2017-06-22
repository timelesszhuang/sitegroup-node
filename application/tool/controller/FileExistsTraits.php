<?php
/**
 * Created by PhpStorm.
 * User: qiangbi
 * Date: 17-6-8
 * Time: 下午3:18
 */

namespace app\tool\controller;

use app\tool\model\ArticleInsertA;
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

    /**
     * 目录生成失败后,将目录记录下来
     * @param $directory
     */
    public function make_error($directory)
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        (new SiteErrorInfo)->addError([
            'msg' => "{$site_name}站点$directory" . "目录不存在或没有权限",
            'operator' => "模板不存在",
            'site_id' => $site_id,
            'site_name' => $site_name,
            'node_id' => $node_id,
        ]);
    }

    /**
     * 检查字符的ascii码 是否大于127
     * @param $str
     * @return bool
     */
    public function checkAscii($str)
    {
        if (ord($str) > 127) {
            return true;
        }
        return false;
    }

    /**
     * 从内容中获取中文下标
     * @param $content
     * @param int $count
     * @return mixed
     */
    public function getKey($content, $count = 3)
    {
        $arr = [];
        if (!empty($content)) {
            preg_match_all("/./u", $content, $arr);
            $i = 1;
            while ($i < 3) {
                $temp_arr = array_rand($arr[0], $count);
                foreach ($temp_arr as $item) {
                    if (!$this->checkAscii($arr[0][$item]) || $item < 5) {
                        $i = 1;
                        continue;
                    } else {
                        $i++;
                    }
                }
            }
            return $temp_arr;
        }
    }

    /**
     * 组织a链接
     * @param $node_id
     * @param $site_id
     */
    public function insertA($node_id, $site_id, $content)
    {
        $data = ArticleInsertA::where(["node_id" => $node_id, "site_id" => $site_id])->select();
        if (empty($data)) {
            return false;
        }
        if (count($data) < 3) {
            return false;
        }
        $a = [];
        $temp_content = $content;
        //字符串长度
        $count = strlen($temp_content);
        //获取下标中文
        list($first, $second, $third) = $this->getKey($temp_content);
        foreach ($this->foreachLink($node_id, $site_id, $data) as $item) {
            array_push($a, $item);
        }
        //截取前面的内容 和后面的内容 然后和a链接合并到一起组成新内容
        $pre_one = mb_substr($temp_content, 0, $third);
        $next_one = mb_substr($temp_content, $third, $count);
        $lastest_one = $pre_one . $a[0] . $next_one;

        $pre_two = mb_substr($lastest_one, 0, $second);
        $next_two = mb_substr($lastest_one, $second, $count);
        $lastest_one = $pre_two . $a[1] . $next_two;

        $pre_three = mb_substr($lastest_one, 0, $first);
        $next_three = mb_substr($lastest_one, $first, $count);
        $lastest_one = $pre_three . $a[2] . $next_three;
        return $lastest_one;
    }

    /**
     * 循环a链接
     * @param $node_id
     * @param $site_id
     * @return \Generator
     */
    public function foreachLink($node_id, $site_id, $data)
    {
        $temp_data = collection($data)->toArray();
        $for_arr = array_rand($temp_data, 3);
        foreach ($for_arr as $item) {
            yield $this->makeALink($temp_data[$item]);
        }
    }

    /**
     * 创建a链接
     * @param $item
     * @return string
     */
    public function makeALink($item)
    {
        return '<a href="' . $item["href"] . '" title="' . $item['title'] . '" target="_blank">' . $item["content"] . "</a>";
    }
}