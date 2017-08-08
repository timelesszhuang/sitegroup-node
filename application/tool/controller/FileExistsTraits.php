<?php
/**
 * Created by PhpStorm.
 * User: qiangbi
 * Date: 17-6-8
 * Time: 下午3:18
 */

namespace app\tool\controller;

use app\tool\model\ArticleInsertA;
use app\tool\model\ArticlekeywordSubstitution;
use app\tool\model\SiteErrorInfo;
use think\Cache;
use app\tool\model\SystemConfig;

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
                'operator' => '页面静态化',
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
            'operator' => "页面静态化",
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
        if (ord($str) > 130) {
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
                    if (!$this->checkAscii($arr[0][$item]) || $item < 15) {
                        $i = 1;
                        continue;
                    } else {
                        file_put_contents("code.txt", $arr[0][$item] . "\r\n", FILE_APPEND);
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
    public function contentJonintALink($node_id, $site_id, $content)
    {
//        取数据
        $data = ArticleInsertA::where(["node_id" => $node_id, "site_id" => $site_id])->select();
        if (empty($data)) {
            return false;
        }
        // 最少需要三个关键词才可以启动
        if (count($data) < 3) {
            return false;
        }
        $temp_data = collection($data)->toArray();
        $a = [];
        $temp_content = $content;
        //字符串长度
        $count = strlen($temp_content);
        //获取文章中的三个点  并且是从大到小排好序的
        list($first, $second, $third) = $this->getKey($temp_content);
        // 获取返回的a链接
        foreach ($this->foreachLink($temp_data) as $item) {
            array_push($a, $item);
        }
        //截取前面的内容 和后面的内容 然后和a链接合并到一起组成新内容
        //先从最后一个点截取 然后合并起来内容
        $pre_one = mb_substr($temp_content, 0, $third);
        $next_one = mb_substr($temp_content, $third, $count);
        $lastest_one = $pre_one . $a[0] . $next_one;

        //然后截取前面一个点 然后合并起来内容
        $pre_two = mb_substr($lastest_one, 0, $second);
        $next_two = mb_substr($lastest_one, $second, $count);
        $lastest_one = $pre_two . $a[1] . $next_two;

        //最后截取最前面的一个点 最后合并返回即可
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
    public function foreachLink($data)
    {
        //随机取3个链接
        $for_arr = array_rand($data, 3);
        foreach ($for_arr as $item) {
            yield $this->makeALink($data[$item]);
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

    /**
     * 文章内容关键字一次性替换 不用循环
     * @param $node_id
     * @param $site_id
     * @param $content
     * @return mixed
     */
    public function replaceKeyword($node_id, $site_id, $content)
    {
        $data = ArticlekeywordSubstitution::where(["site_id" => $site_id, "node_id" => $node_id])->select();
        if (!$data) {
            return $content;
        }
        $temp_data = collection($data)->toArray();
        //替换前数据
        $front_substitution = array_column($temp_data, "front_substitution");
        //替换后的数据
        $substitution = array_column($temp_data, "substitution");
        return str_replace($front_substitution, $substitution, $content);
    }

    /**
     * phpmailer工具发送邮件
     * @param $sendUser 发送者账号
     * @param $sendpwd  发送者密码
     * @param $subject  标题
     * @param $toUser   接收用户
     * @param $sendName 发送者显示名称
     * @param $sendBody 发送内容
     * @return array
     */
    public function phpmailerSend($sendUser, $sendpwd, $host, $subject, $toUser, $sendBody, $fromname)
    {
        $mail = new \PHPMailer;
        $mail->IsSmtp(true);                         // 设置使用 SMTP
        $mail->Host = $host;       // 指定的 SMTP 服务器地址
        $mail->SMTPAuth = true;                  // 设置为安全验证方式
        $mail->Username = $sendUser; // SMTP 发邮件人的用户名
        $mail->Password = $sendpwd;            // SMTP 密码
        $mail->From = $sendUser;
        $mail->FromName = $fromname;
        $mail->CharSet = "UTF-8";
        $mail->AddReplyTo("support@qiangbi.net", "强比科技");//回复给谁
        $mail->AddAddress($toUser);
        //发送到谁 写谁$mailaddress
        $mail->WordWrap = 50;                // set word wrap to 50 characters
        $mail->IsHTML(true);                    // 设置邮件格式为 HTML
        $mail->Subject = $subject; //邮件主题// 标题
        $mail->Body = $sendBody;              // 内容
        $mail->Send();
    }

    /**
     * 获取support邮箱帐号
     * @return array|bool
     */
    public function getEmailAccount()
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        //support邮箱
        $email = SystemConfig::where(["name" => "SYSTEM_EMAIL", "need_auth" => 1])->find();
        if (!isset($email->value)) {
            (new SiteErrorInfo)->addError([
                'msg' => "support邮箱不存在!",
                'operator' => 'support邮箱不存在',
                'site_id' => $site_id,
                'site_name' => $site_name,
                'node_id' => $node_id,
            ]);
            return false;
        }
        //support密码
        $password = SystemConfig::where(["name" => "SYSTEM_EMAIL_PASSWORD", "need_auth" => 1])->find();
        if (!isset($password->value)) {
            (new SiteErrorInfo)->addError([
                'msg' => "support邮箱密码不存在!",
                'operator' => 'support邮箱密码不存在',
                'site_id' => $site_id,
                'site_name' => $site_name,
                'node_id' => $node_id,
            ]);
            return false;
        }
        //support host
        $host = SystemConfig::where(["name" => "SYSTEM_EMAIL_SMTPHOST", "need_auth" => 1])->find();
        if (!isset($host->value)) {
            (new SiteErrorInfo)->addError([
                'msg' => "support邮箱host不存在!",
                'operator' => 'support邮箱host不存在',
                'site_id' => $site_id,
                'site_name' => $site_name,
                'node_id' => $node_id,
            ]);
            return false;
        }
        return [
            "email" => $email->value,
            "password" => $password->value,
            "host" => $host->value
        ];
    }


}