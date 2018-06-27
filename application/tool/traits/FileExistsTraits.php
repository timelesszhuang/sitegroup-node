<?php
/**
 * Created by PhpStorm.
 * User: qiangbi
 * Date: 17-6-8
 * Time: 下午3:18
 */

namespace app\tool\traits;

use app\tool\model\ArticlekeywordSubstitution;
use app\tool\model\ArticleReplaceKeyword;
use app\tool\model\SiteErrorInfo;
use app\tool\model\SystemConfig;

trait FileExistsTraits
{

    /**
     * 检测文件是否存在 并写入数据库
     * @param $filename
     * @return bool
     */
    public function fileExists($filename, $operator = '页面静态化')
    {
        $siteinfo = $this->siteinfo;
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        if (!file_exists($filename)) {
            (new SiteErrorInfo)->addError([
                'msg' => "{$site_name}站点" . $filename . "模板不存在!",
                'operator' => $operator,
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
        $siteinfo = $this->siteinfo;
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        (new SiteErrorInfo)->addError([
            'msg' => "{$site_name}站点$directory" . "目录或文件不存在或没有权限",
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
     * 文章内容关键字一次性替换 不用循环
     * @param $node_id
     * @param $site_id
     * @param $content
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
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
     * @param $host
     * @param $subject  标题
     * @param $toUser   接收用户
     * @param $sendBody 发送内容
     * @param $fromname
     * @return void
     * @throws \phpmailerException
     */
    public function phpmailerSend($sendUser, $sendpwd, $host, $subject, $toUser, $sendBody, $fromname)
    {
        $mail = new \PHPMailer();
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
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getEmailAccount()
    {
        //support邮箱
        $email = SystemConfig::where(["name" => "SYSTEM_EMAIL", "need_auth" => 1])->find();
        if (!isset($email->value)) {
            (new SiteErrorInfo)->addError([
                'msg' => "support邮箱不存在!",
                'operator' => 'support邮箱不存在',
                'site_id' => $this->site_id,
                'site_name' => $this->site_name,
                'node_id' => $this->node_id,
            ]);
            return false;
        }
        //support密码
        $password = SystemConfig::where(["name" => "SYSTEM_EMAIL_PASSWORD", "need_auth" => 1])->find();
        if (!isset($password->value)) {
            (new SiteErrorInfo)->addError([
                'msg' => "support邮箱密码不存在!",
                'operator' => 'support邮箱密码不存在',
                'site_id' => $this->site_id,
                'site_name' => $this->site_name,
                'node_id' => $this->node_id,
            ]);
            return false;
        }
        //support host
        $host = SystemConfig::where(["name" => "SYSTEM_EMAIL_SMTPHOST", "need_auth" => 1])->find();
        if (!isset($host->value)) {
            (new SiteErrorInfo)->addError([
                'msg' => "support邮箱host不存在!",
                'operator' => 'support邮箱host不存在',
                'site_id' => $this->site_id,
                'site_name' => $this->site_name,
                'node_id' => $this->node_id,
            ]);
            return false;
        }
        return [
            "email" => $email->value,
            "password" => $password->value,
            "host" => $host->value
        ];
    }


    /**
     * 文章内容中的关键词替换
     * @param $content
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function articleReplaceKeyword($content)
    {
        $data = ArticleReplaceKeyword::where([
            "node_id" => $this->node_id,
            "site_id" => $this->site_id
        ])->select();
        if (empty($data)) {
            return $content;
        }
        $temContent = $content;
        foreach ($data as $item) {
            $temContent = str_replace($item->keyword, $item->replaceLink, $temContent);
        }
        return $temContent;
    }

    /**
     * 返回对象  默认不填为success 否则是failed
     * @param int $msg
     * @param string $stat
     * @param int $data
     * @return array
     * @author guozhen
     */
    public function resultArray($msg = 0, $stat = '', $data = 0)
    {
        if (empty($stat)) {
            $status = "success";
        } else {
            $status = "failed";
        }
        return [
            'status' => $status,
            'data' => $data,
            'msg' => $msg
        ];
    }


    /**
     * curl get请求
     * @param $url
     * @return mixed
     */
    public function curl_get($url)
    {
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        //执行命令
        $data = curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        return $data;
    }

    /**
     * 发送curl post请求
     * @param $url
     * @param $data post数据 数组格式
     * @return mixed
     */
    public function curl_post($url, $data)
    {
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        //执行命令
        $data = curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        return $data;
    }


}