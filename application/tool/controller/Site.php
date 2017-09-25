<?php

namespace app\tool\controller;

use app\tool\model\UserDefinedForm as userForm;
use app\index\model\Pv;
use app\tool\model\Rejection;
use app\tool\model\SiteErrorInfo;
use app\common\controller\Common;
use app\tool\model\SiteUser;
use app\tool\model\UserDefinedForm;
use think\Cache;
use think\Config;
use think\Db;
use think\Request;
use think\Validate;
use app\tool\controller\FileExistsTraits;

/**
 * 站点相关操作
 * 链轮类型
 * 获取主站链接
 * 友联获取
 * js 公共代码获取
 * 联系方式获取
 */
class Site extends Common
{
    use FileExistsTraits;

    /**
     * 获取链轮的相关信息
     *  两种链轮类型  1 循环链轮  需要返回  next_site 也就是本网站需要链接到的网站  main_site  表示主节点 从id 小的 链接到比较大的  最大的id 链接到最小的id 上
     *              2 金字塔型  需要返回要指向的 主节点  二级节点之间不需要互相链
     * @access public
     * @return mixed  第一个字段是 链轮的类型 10 表示 循环链轮 20 表示 金字塔型链轮
     * @todo  还要考虑到手机站的情况 手机站的互链情况
     */
    public static function getLinkInfo($site_type_id, $site_id, $site_name, $node_id)
    {
        //首先获取当前的节点id
        $site_type = Db::name('site_type')->where('id', $site_type_id)->find();
        $chain_type = $site_type['chain_type'];
        //10表示循环链轮 20 表示 金字塔型链轮
        //获取主节点////////////////////////////////////////////
        //返回 主站的域名 id 等
        //有可能没有设置主站  需要有个地方记录下错误信息
        $main_site = Db::name('site')->where(['site_type' => $site_type_id, 'main_site' => '20'])->field('id,site_name,url')->find();
        if (!$main_site) {
            //没有设置主节点 需要提示下错误信息
            $site_info = new SiteErrorInfo();
            $site_info->addError([
                'msg' => $site_type['name'] . "站点分类没有设置主站点",
                'operator' => '页面静态化',
                'site_id' => $site_id,
                'site_name' => $site_name,
                'node_id' => $node_id,
            ]);
            //错误信息
        }
        //判断主节点是不是当前的节点
        if ($site_id == $main_site['id']) {
            $main_site = [];
        }
        $next_site = [];
        if ($chain_type == '10' && Db::name('site')->where(['site_type' => $site_type_id, 'main_site' => '10'])->count() > 2) {
            //如果该分类下的非主节点的数量小于 3个 则 不需要互相链接  否则形成的 互链 bug，容易被搜索引擎 K掉
            //链轮的时候为 id 小的 链接到id 大的，然后最终 id 最大的连接到 最小的id
            $chain_site = Db::name('site')->where(['site_type' => $site_type_id, 'main_site' => '10', 'id' => ['gt', $site_id]])->field('url,site_name')->find();
            if ($chain_site) {
                $next_site = $chain_site;
            } else {
                //说明没有取到id 比较大的
                //取下id 最小的
                $chain_site = Db::name('site')->where(['site_type' => $site_type_id, 'main_site' => '10'])->order('id asc')->field('url,site_name')->find();
                $next_site = $chain_site;
            }
        } else if ($chain_type == '20') {
            //表示金字塔型的 链轮
            //不需要返回 其他信息
        }
        return [$chain_type, $next_site, $main_site];
    }


    /**
     * 获取站点相关配置信息
     * @access public
     */
    public static function getSiteInfo()
    {
        if ($info = Cache::get(Config::get('site.CACHE_LIST')['SITEINFO'])) {
            return $info;
        }
        $site_id = Config::get('site.SITE_ID');
        //第一次进来的时候就需要获取下全部的栏目 获取全部的关键词
        $info = Db::name('site')->where('id', $site_id)->find();
        if (empty($info)) {
            //如果为空的话 处理方式
            //表示该
            exit("未找到站点id {$site_id} 的配置信息");
        }
        Cache::set(Config::get('site.CACHE_LIST')['SITEINFO'], $info, Config::get('site.CACHE_TIME'));
        return $info;
    }

    /**
     * @param $ip
     * @return mixed
     * 调用接口根据ip查询地址
     */
    public function get_ip_info($ip)
    {
        $curl = curl_init(); //这是curl的handle
        $url = "http://ip.taobao.com/service/getIpInfo.php?ip=$ip";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl, CURLOPT_HEADER, 0); //don't show header
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //相当关键，这句话是让curl_exec($ch)返回的结果可以进行赋值给其他的变量进行，json的数据操作，如果没有这句话，则curl返回的数据不可以进行人为的去操作（如json_decode等格式操作）
        curl_setopt($curl, CURLOPT_TIMEOUT, 2);
        $data = curl_exec($curl);
        return json_decode($data, true);
    }

    /**
     * 页面浏览一次存储一次
     * pv存储
     * @access public
     */
    public function pv()
    {
        session_write_close();
        $request = Request::instance();
        $nowip = $request->ip();
        $data = $this->get_ip_info($nowip);
        $siteinfo = Site::getSiteInfo();
        $pvdata['node_id'] = $siteinfo['node_id'];
        $pvdata['site_id'] = $siteinfo['id'];
        //国家
        $pvdata['country'] = $data['data']['country'];
        $pvdata['country_id'] = $data['data']['country_id'];
        $pvdata['area_id'] = $data['data']['area_id'];
        $pvdata['region'] = $data['data']['region'];
        $pvdata['region_id'] = $data['data']['region_id'];
        $pvdata['city'] = $data['data']['city'];
        $pvdata['city_id'] = $data['data']['city_id'];
        $pvdata['ip'] = $data['data']['ip'];
        $pvdata['create_time'] = time();
        $pvdata['referer'] = '';
        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            $pvdata['referer'] = $_SERVER['HTTP_REFERER'];
        }
        Pv::create($pvdata);
    }


    /**
     * 甩单填写 注入相关操作
     * @access public
     */
    public function Rejection()
    {
        session_write_close();
        $rule = [
            ["name", "require", "请输入您的姓名"],
            ["phone", "require", "请输入您的电话"],
//            ["email", "require", "请输入您的邮箱"],
//            ["company", "require", "请输入您的公司名"],
        ];
        $validate = new  Validate($rule);
        $request = Request::instance();
        $nowip = $request->ip();
        $ipdata = $this->get_ip_info($nowip);
        $siteinfo = Site::getSiteInfo();
        $formdata = $this->request->post();
        if (empty($ipdata['data'])) {
            $data['country_id'] = "";
            $data['area_id'] = "";
            $data['region'] = "";
            $data['region_id'] = "";
            $data['city'] = "";
            $data['city_id'] = "";
            $data['country'] = "";
            $data['country_id'] = "";
            $data['ip'] = "";
        } else {
            $data['node_id'] = $siteinfo['node_id'];
            $data['site_id'] = $siteinfo['id'];
            //国家
            $data['country'] = $ipdata['data']['country'];
            $data['country_id'] = $ipdata['data']['country_id'];
            $data['area_id'] = $ipdata['data']['area_id'];
            $data['region'] = $ipdata['data']['region'];
            $data['region_id'] = $ipdata['data']['region_id'];
            $data['city'] = $ipdata['data']['city'];
            $data['city_id'] = $ipdata['data']['city_id'];
            $data['ip'] = $ipdata['data']['ip'];
        }
        $data['create_time'] = time();
        $data['referer'] = '';
        $data["name"] = strip_tags(quotemeta($formdata['name']));
        $data["phone"] = strip_tags(quotemeta($formdata['phone']));
        $data["email"] = strip_tags(addslashes($formdata['email']));
        $data["company"] = strip_tags(addslashes($formdata['company']));
        //提交甩单次数过多
        $nowtime = time();
        $oldtime = time() - 60 * 2;
        $where["create_time"] = ['between', [$oldtime, $nowtime]];
        $countnum = Db::name('rejection')->where($where)->field('ip')->select();
        $num = sizeof($countnum);
        if ($num > 4) {
            return $this->resultArray('访问次数过多', 'failed');
        }
        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            $data['referer'] = $_SERVER['HTTP_REFERER'];
        }

        if (!$validate->check($data)) {
            return $this->resultArray($validate->getError(), "failed");
        }
        if (!Rejection::create($data)) {
            return $this->resultArray("申请失败", "failed");
        }
        $email = $this->getEmailAccount();
        if ($email) {
            $site_obj = \app\tool\model\Site::get($siteinfo['id']);
            if (isset($site_obj->user_id)) {
                $siteUser = SiteUser::get($site_obj->user_id);
                if ($siteUser) {
                    $content = "公司名称:" . $data["company"] . "</br>" . "联系人:" . $data["name"] . "</br>" . "电话:" . $data["phone"] . "</br>" . "邮箱:" . $data["email"];
                    $this->phpmailerSend($email["email"], $email["password"], $email["host"], $siteUser->name . "的甩单", $siteUser->email, $content, $email["email"]);
                }
            }
        }
        return $this->resultArray("尊敬的用户，我们已经收到您的请求，稍后会有专属客服为您服务。");
    }


    /**
     * 甩单填写 注入相关操作
     * @access public
     */
    public function DefinedRejection()
    {
//        session_write_close();
        $request = Request::instance();
        $tag = $request->post('tag');
        if (!$tag) {
            return $this->resultArray("尊敬的客户，提交错误，请稍后再试。", "failed");
        }
        $definedform = userForm::get(['tag' => $tag]);
        //唯一标志
        //node_id 获取到的node_id
        $node_id = $definedform->node_id;
        //表单数据
        $siteinfo = Site::getSiteInfo();
        if ($node_id != $siteinfo['node_id']) {
            return $this->resultArray("尊敬的客户，提交错误，请稍后再试。", "failed");
        }
        //dump($definedform->form_info);die;
        $form_info = unserialize($definedform->form_info);
//dump($form_info);die;
        $rule = [];
        foreach ($form_info as $k => $v) {
            if ($v['require']) {
                $rule[] = [$k, 'require', '请输入您的' . $v['name']];
            }
        }
        $validate = new  Validate($rule);
        $nowip = $request->ip();
        $ipdata = $this->get_ip_info($nowip);
        $formdata = $this->request->post();
//        dump($formdata);die;
        if (empty($ipdata['data'])) {
            $data['node_id'] = $siteinfo['node_id'];
            $data['site_id'] = $siteinfo['id'];
            $data['country_id'] = "";
            $data['area_id'] = "";
            $data['region'] = "";
            $data['region_id'] = "";
            $data['city'] = "";
            $data['city_id'] = "";
            $data['country'] = "";
            $data['country_id'] = "";
            $data['ip'] = "";
        } else {
            $data['node_id'] = $siteinfo['node_id'];
            $data['site_id'] = $siteinfo['id'];
            //国家
            $data['country'] = $ipdata['data']['country'];
            $data['country_id'] = $ipdata['data']['country_id'];
            $data['area_id'] = $ipdata['data']['area_id'];
            $data['region'] = $ipdata['data']['region'];
            $data['region_id'] = $ipdata['data']['region_id'];
            $data['city'] = $ipdata['data']['city'];
            $data['city_id'] = $ipdata['data']['city_id'];
            $data['ip'] = $ipdata['data']['ip'];
        }
        $data['create_time'] = time();
        $data['referer'] = '';
        $tag = $definedform->tag;
        $wh['tag'] = $tag;
        $UserDefinedForm = (new UserDefinedForm())->where($wh)->find();
        $data['tag_id'] = $UserDefinedForm['id'];
        if (array_key_exists("field1", $formdata)) {
            $olddata["field1"] = strip_tags(quotemeta($formdata['field1']));
            $data["field1"] = $form_info['field1']['name'] . ':' . $olddata['field1'];
        } else {
            $data["field1"] = '';
        }
        if (array_key_exists("field2", $formdata)) {
            $olddata["field2"] = strip_tags(quotemeta($formdata['field2']));
            $data["field2"] = $form_info['field2']['name'] . ':' . $olddata['field2'];
        } else {
            $data["field2"] = '';
        }
        if (array_key_exists("field3", $formdata)) {
            $olddata["field3"] = strip_tags(quotemeta($formdata['field3']));
            $data["field3"] = $form_info['field3']['name'] . ':' . $olddata['field3'];
        } else {
            $data["field3"] = '';
        }
        if (array_key_exists("field4", $formdata)) {
            $olddata["field4"] = strip_tags(quotemeta($formdata['field4']));
            $data["field4"] = $form_info['field4']['name'] . ':' . $olddata['field4'];
        } else {
            $data["field4"] = '';
        }
        //提交甩单次数过多
        $nowtime = time();
        $oldtime = time() - 60 * 2;
        $where["create_time"] = ['between', [$oldtime, $nowtime]];
        $countnum = Db::name('rejection')->where($where)->field('ip')->select();
        $num = sizeof($countnum);
        if ($num > 4) {
            return $this->resultArray('访问次数过多', 'failed');
        }
        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            $data['referer'] = $_SERVER['HTTP_REFERER'];
        }
        if (!$validate->check($data)) {
            return $this->resultArray($validate->getError(), "failed");
        }
        if (!isset($data['node_id'])) {
            return $this->resultArray("申请失败", "failed");
        }
        if (!Rejection::create($data)) {
            return $this->resultArray("申请失败", "failed");
        }
        $email = $this->getEmailAccount();
//        $this->phpmailerSend($email['email'], $email['password'], $email["host"], "您有新的线索","1318911846@qq.com", "sfasd",$email['email']);
//        die;
//        dump($email);die;
        if ($email) {
            $site_obj = \app\tool\model\Site::get($siteinfo['id']);
            if (isset($site_obj->user_id)) {
                $siteUser = SiteUser::get($site_obj->user_id);
                if ($siteUser) {
                    $content = $data["field1"] . "</br>" . $data["field2"] . "</br>" . $data["field3"] . "</br>" . $data["field4"] . '</br>' . '【乐销易－北京易至信科技有限公司】';
//                    file_put_contents('demo.txt', print_r($content, true), FILE_APPEND);
//                    file_put_contents('demo.txt', print_r($email, true), FILE_APPEND);
//                    file_put_contents('demo.txt', $siteUser->name . $siteUser->email, FILE_APPEND);
                    //这个地方有问题
//                    dump();die;
                    $this->phpmailerSend($email['email'], $email['password'], $email["host"], $siteUser->name . "您有新的线索", $siteUser->email, $content, $email["email"]);
                }
            }
        }
        return $this->resultArray("尊敬的用户，我们已经收到您的请求，稍后会有专属客服为您服务。");
    }

}
