<?php
/**
 * Created by PhpStorm.
 * User: qiangbi
 * Date: 17-6-8
 * Time: 下午3:18
 */

namespace app\tool\controller;

use app\index\model\Question;
use app\index\model\ScatteredTitle;
use app\tool\model\ArticleInsertA;
use app\tool\model\ArticlekeywordSubstitution;
use app\tool\model\ArticleReplaceKeyword;
use app\tool\model\SiteErrorInfo;
use app\tool\model\SitePageinfo;
use think\Cache;
use app\tool\model\SystemConfig;
use think\View;

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
        $temp_arr = [];
        if (!empty($content)) {
            preg_match_all("/./u", $content, $arr);
            $i = 0;
            while ($i < $count) {
                $temp_arr = array_rand($arr[0], $count);
                // 如果count是1 有可能返回的不是数组 需要判断下
                if (!is_array($temp_arr)) {
                    $temp_arr = [$temp_arr];
                }
                foreach ($temp_arr as $item) {
                    if (!$this->checkAscii($arr[0][$item]) || $item < 15) {
                        $i = 0;
                        continue;
                    } else {
                        file_put_contents("code.txt", $arr[0][$item] . "\r\n", FILE_APPEND);
                        $i++;
                    }
                }
            }
            return $temp_arr;
        }
        return false;
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
        $temp_data = collection($data)->toArray();
        // 总数
        $count = count($temp_data);
        $keys = rand(1, 5);
        if ($count <= 5) {
            $keys = $count;
        }
        return $this->runGetKeys($content, $keys, $temp_data);
    }

    /**
     * 返回最终替换后的内容
     * @param $content
     * @param $count
     * @param $links_data
     * @return string
     */
    public function runGetKeys($content, $count, $links_data)
    {
        //获取文章中的指定点  并且是从大到小排好序的
        $positions = $this->getKey($content, $count);
        $links = [];
        foreach ($this->foreachLink($links_data, $count) as $item) {
            array_push($links, $item);
        }
        $tempContent = $content;
        for ($i = ($count - 1); $i > -1; $i--) {
            $pre_one = mb_substr($tempContent, 0, $positions[$i]);
            $next_one = mb_substr($tempContent, $positions[$i]);
            $tempContent = $pre_one . $links[$i] . $next_one;
        }
        return $tempContent;
    }


    /**
     * 循环获取a链接
     * @param $node_id
     * @param $site_id
     * @return \Generator
     */
    public function foreachLink($data, $count)
    {
        //随机取a链接 有可能是1个链接  或多个链接
        $for_arr = array_rand($data, $count);
        if (!is_array($for_arr)) {
            $for_arr = [$for_arr];
        }
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

    /**
     * 根据id重新生成文章
     * @param $id
     * @param $searachType
     * @param $type_id
     * @return bool
     */
    public function exec_articlestatic($id, $searachType, $type_id)
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $site_name = $siteinfo['site_name'];
        $node_id = $siteinfo['node_id'];
        // 根据类型判断
        switch ($searachType) {
            // 文章
            case "article":
                $commonType = "articletype_id";
                $model = "\app\index\model\Article";
                $content = "content";
                $title = "title";
                $field = "id,title";
                $href = "/article/article";
                $template = "article.html";
                $generate_html = "article/article";
                break;
            // 问答
            case "question":
                $commonType = "type_id";
                $model = "\app\index\model\Question";
                $content = "content_paragraph";
                $title = "question";
                $href = "/question/question";
                $template = "question.html";
                $field = "id,question as title";
                $generate_html = "question/question";
                break;
            // 产品
            case "product":
                $commonType = "type_id";
                $model = "\app\index\model\Product";
                $content = "detail";
                $title = "name";
                $href = "/product/product";
                $template = "product.html";
                $field = "*";
                $generate_html = "product/product";
                break;
        }
        //判断文件是否存在
        if (!file_exists($generate_html . $id . ".html")) {
            $this->make_error($href . $id . ".html");
            return false;
        }
        // 获取menu信息
        $menuInfo = \app\tool\model\Menu::where([
            "node_id" => $node_id,
            "type_id" => $type_id
        ])->find();
        // 获取pageInfo信息
        $sitePageInfo = SitePageinfo::where([
            "node_id" => $node_id,
            "site_id" => $site_id,
            "menu_id" => $menuInfo["id"]
        ])->find();
        // 根据类型分配数据
        switch ($searachType) {
            case "article":
                $commonsql = "id = $id and node_id=$node_id and $commonType=$type_id and";
                $common_list_sql = "($commonsql is_sync=20 ) or  ($commonsql site_id = $site_id)";
                break;
            case "question":
                $common_list_sql = ["id" => $id, "type_id" => $type_id, "node_id" => $node_id];
                break;
            case "product":
                $common_list_sql = "id = $id and node_id=$node_id and type_id=$type_id";
                break;
        }
        // 取出指定id的文章
        $common_data = $model::where($common_list_sql)->find();
        //截取出 页面的 description 信息
        $description = mb_substr(strip_tags($common_data[$content]), 0, 200);
        preg_replace('/^&.+\;$/is', '', $description);
        //获取网站的 tdk 文章列表等相关 公共元素
        $assign_data = Commontool::getEssentialElement('detail', $common_data[$title], $description, $sitePageInfo['akeyword_id']);
        //获取上一篇和下一篇
        //获取上一篇
        // 根据类型分配数据
        switch ($searachType) {
            case "article":
                $pre_common_sql = "id <$id and node_id=$node_id and $commonType=$type_id and ";
                $pre_sql = "($pre_common_sql is_sync=20 ) or  ( $pre_common_sql site_id = $site_id)";
                break;
            case "question":
                $pre_sql = ["id" => ["lt", $id], "node_id" => $node_id, "type_id" => $type_id];
                break;
            case "product":
                $pre_sql = "id =id <$id and node_id=$node_id and type_id=$type_id ";
                break;
        }
        // 上一篇
        $pre_common = $model::where($pre_sql)->field($field)->order("id", "desc")->find();
        //上一页链接
        if ($pre_common && ($searachType == "article")) {
            $pre_common = ['href' => $href . $pre_common['id'] . ".html", 'title' => $pre_common['title']];
        } else if ($pre_common) {
            $pre_common = ['href' => $href . $pre_common['id'] . ".html"];
        }
        //最后一条 不需要有 下一页
        // 根据类型分配数据
        switch ($searachType) {
            case "article":
                $next_common_sql = "id >$id and node_id=$node_id and $commonType=$type_id and ";
                $next_sql = "($next_common_sql is_sync=20 ) or  ( $next_common_sql site_id = $site_id)";
                break;
            case "question":
                $next_sql = ["id" => ["gt", $id], "node_id" => $node_id, "type_id" => $type_id];
                break;
            case "product":
                $next_sql = "id >id and node_id=$node_id and type_id=$type_id";
                break;
        }
        // 获取下一篇
        $next_common = $model::where($next_sql)->field($field)->find();
        //下一页链接
        if ($next_common) {
            $next_common['href'] = $href . $next_common['id'] . ".html";
        }
        // 首先需要把base64 缩略图 生成为 文件
//        $water = $assign_data['site_name'] .' '.$assign_data['url'];
        $water = $siteinfo['walterString'];
        if (($searachType == "article") && isset($common_data["thumbnails_name"])) {
            //存在 base64缩略图 需要生成静态页
            preg_match_all('/<img[^>]+src\s*=\\s*[\'\"]([^\'\"]+)[\'\"][^>]*>/i', $common_data["thumbnails_name"], $match);
            if (!empty($match[1])) {
                $this->form_img_frombase64($match[1], $common_data["thumbnails_name"], $water);
            }
        } else if (($searachType == "product") && isset($common_data["base64"])) {
            //存在 base64缩略图 需要生成静态页
            preg_match_all('/<img[^>]+src\s*=\\s*[\'\"]([^\'\"]+)[\'\"][^>]*>/i', $common_data["base64"], $match);
            if (!empty($match[1])) {
                $this->form_img_frombase64($match[1], $common_data["base64"], $water);
            }
        }
        //替换图片 base64 为 图片文件
        $temp_content = $this->form_img($common_data[$content], $water);
        // 替换关键字
        $temp_content = $this->replaceKeyword($node_id, $site_id, $temp_content);
        // 将A链接插入到内容中去
        $contentWIthLink = $this->contentJonintALink($node_id, $site_id, $temp_content);
        if ($contentWIthLink) {
            $temp_content = $contentWIthLink;
        }
        //最终数据
        $latestData = [
            'd' => $assign_data,
            'pre_article' => $pre_common,
            'next_article' => $next_common,
        ];
        // 根据类型分配数据
        switch ($searachType) {
            case "article":
                $latestData['article'] = ["title" => $common_data->title, "auther" => $common_data->auther, "create_time" => $common_data->create_time, "content" => $temp_content];
                break;
            case "question":
                $latestData["question"] = $common_data;
                break;
            case "product":
                $latestData['product'] = ["name" => $common_data->name, "image" => "<img src='/images/{$common_data->image_name}' alt='{$common_data->name}'>", 'sn' => $common_data->sn, 'type_name' => $common_data->type_name, "summary" => $common_data->summary, "detail" => $common_data->detail, "create_time" => $common_data->create_time];
                break;
        }
        $content = (new View())->fetch('template/' . $template, $latestData);
        //判断目录是否存在
        if (!file_exists($searachType)) {
            $this->make_error($searachType);
            return false;
        }
        $make_web = file_put_contents($generate_html . $common_data["id"] . '.html', chr(0xEF) . chr(0xBB) . chr(0xBF) . $content);
    }


    /**
     * 根据base64 生成图片
     * @access public
     * @param $base64img data:image/png;base64,***********
     * @param $img_name ***.jpg  ***.png
     * @param $water 水印字符串站点名+域名
     */
    private function form_img_frombase64($base64img, $img_name, $water)
    {
        //保存base64字符串为图片
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64img, $result)) {
            //匹配出 图片的类型
            $new_file = "images/$img_name";
            if (strpos($img_name, '.') === false) {
                $type = $result[2];
                $new_file = "images/$img_name" . '.' . $type;
            } else {
                //生成缩略图
                $info = pathinfo($img_name);
                $type = $info['extension'];
            }
            //获取图片的位置
            $ttcPath = dirname(THINK_PATH) . '/6.ttc';
            $dst = imagecreatefromstring(base64_decode(str_replace($result[1], '', $base64img)));
            //水印颜色
//            $color = imagecolorallocatealpha($dst, 255, 255, 255, 30);
            $color = imagecolorallocatealpha($dst, 197, 37, 19, 30);
            //添加水印
            imagettftext($dst, 12, 0, 10, 22, $color, $ttcPath, $water);
            $func = "image{$type}";
            $create_status = $func($dst, $new_file);
            imagedestroy($dst);
            if ($create_status) {
                return ['/' . $new_file, true];
            }
            return ['/' . $new_file, false];
        }
    }

    /**
     * 根据内容生成图片
     * @access public
     */
    public function form_img($content, $water)
    {
        //从中提取出 base64 中的内容
        //使用正则匹配
        //匹配base64 文件类型
        preg_match_all('/<img[^>]+src\s*=\\s*[\'\"]([^\'\"]+)[\'\"][^>]*>/i', $content, $match);
        if (!empty($match)) {
            if (array_key_exists(1, $match)) {
                foreach ($match[1] as $k => $v) {
                    $img_name = md5(uniqid(rand(), true));
                    list($file_name, $status) = $this->form_img_frombase64($v, $img_name, $water);
                    //需要替换掉内容中的数据
                    if ($status) {
                        $content = str_replace($v, $file_name, $content);
                    }
                }
            }
        }
        return $content;
    }

    /**
     * 关键词替换
     * @param $content
     * @return string
     */
    public function articleReplaceKeyword($content)
    {
        $siteinfo = Site::getSiteInfo();
        $site_id = $siteinfo['id'];
        $node_id = $siteinfo['node_id'];
        $data = ArticleReplaceKeyword::where([
            "node_id" => $node_id,
            "site_id" => $site_id
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
     * @param $array 响应数据
     * @return array
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
     * 获取静态文件列表
     * @param $type
     * @param $page
     * @return array|string
     */
    public function staticOne($type, $name)
    {
        // 检查文件夹
        if (!is_dir($type)) {
            return json_encode([
                "msg" => "文件未生成",
                "status" => "failed",
            ]);
        }
        $resource = opendir($type);
        $content = '';
        $filename = ROOT_PATH . "public/" . $type . "/" . $name . ".html";
        if (file_exists($filename)) {
            $content = base64_encode(file_get_contents($filename));
            return json_encode([
                "msg" => "",
                "status" => "success",
                "data" => $content
            ]);
        }
        return json_encode([
            "msg" => "文件未生成",
            "status" => "failed",
        ]);
    }

    /**
     * 修改静态文件列表
     * @param $type
     * @param $page
     * @return array|string
     */
    public function generateStaticOne($type, $name, $content)
    {
        // 检查文件夹
        if (!is_dir($type)) {
            return $this->resultArray("文件夹不存在");
        }
        $filename = ROOT_PATH . "public/" . $type . "/" . $name . ".html";
        if (file_exists($filename)) {
            $content = file_put_contents($filename, chr(0xEF) . chr(0xBB) . chr(0xBF) . $content);
            return json_encode([
                "msg" => "修改成功",
                "status" => "success",
                "data" => ""
            ]);
        }
        return json_encode([
            "msg" => "文件未生成",
            "status" => "failed",
            "data" => ''
        ]);
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
    public function curl_post($url,$data)
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


    /**
     * ping百度程序
     * @param $data
     */
    public function pingBaidu($data)
    {
        $siteinfo = Site::getSiteInfo();
        $html=<<<ENF
<?xml version="1.0" encoding="UTF-8"?>
<methodCall>
    <methodName>weblogUpdates.extendedPing</methodName>
    <params>
        <param>
            <value><string>{$siteinfo['site_name']}</string></value>
        </param>
        <param>
            <value><string>{$siteinfo['url']}</string></value>
        </param>
ENF;

        foreach ($data as $item) {
            $html .= <<<ONE
        <param>
            <value><string>{$item}</string></value>
        </param>
ONE;
            $html.="</params></methodCall>";
            $this->curl_post("http://ping.baidu.com/ping/RPC2",$html);
        }
    }
}