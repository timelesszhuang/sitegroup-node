<?php

namespace app\tool\controller;

use app\common\controller\Common;

use app\tool\traits\FileExistsTraits;
use OSS\OssClient;
use think\Cache;
use think\Request;

/**
 * 页面静态化 入口文件
 * 该文件接收请求重新生成页面
 */
class Pagestaticentry extends Common
{
    use FileExistsTraits;

    /**
     * crontabstatic
     * crontab 定期请求
     */
    public function crontabstatic()
    {
        Cache::clear();
        // 详情页面生成
        (new Detailstatic())->index('crontab');
        // 配置文件中的静态化
        (new Envpagestatic())->index();
        // 详情类性的页面的静态化
        (new Detailmenupagestatic())->index();
        (new Indexstatic())->index();
        (new SiteMap)->index();
        exit(['status' => 'success', 'msg' => '首页静态化生成完成。']);
    }


    /**
     * 全部的页面静态化操作
     * @access public
     */
    public function allstatic()
    {
        Cache::clear();
        //全部的页面的静态化
        // 详情页面生成
        (new Detailstatic())->index();
        // 配置文件中的静态化
        (new Envpagestatic())->index();
        // 详情类性的页面的静态化
        (new Detailmenupagestatic())->index();
        (new Indexstatic())->index();
        (new SiteMap)->index();
        exit(['status' => 'success', 'msg' => '首页静态化生成完成。']);
    }


    /**
     * 首页生成相关操作
     * @access public
     */
    public function indexstatic()
    {
        Cache::clear();
        // 首先首页更新
        if ((new Indexstatic())->index()) {
            exit(['status' => 'success', 'msg' => '首页静态化生成完成。']);
        }
        exit(['status' => 'failed', 'msg' => '首页静态化生成失败。']);
    }


    /**
     * 文章静态化
     * @access public
     */
    public function articlestatic()
    {
        Cache::clear();
        //文章页面的静态化
        (new Detailstatic())->index();
        exit(['status' => 'success', 'msg' => '文章页面生成完成。']);
    }


    /**
     * 菜单 静态化入口
     * @access public
     */
    public function menustatic()
    {
        Cache::clear();
        //菜单详情页面 静态化 配置页面静态化
        if ((new Detailmenupagestatic())->index() && (new Envpagestatic())->index()) {
            exit(['status' => 'success', 'msg' => '栏目页静态化生成完成。']);
        }
        exit(['status' => 'failed', 'msg' => '栏目页静态化生成完成。']);
    }

    /**
     * 根据id和类型 重新生成静态化
     * 比如 修改文章相关信息之后重新生成
     * @param Request $request
     */
    public function reGenerateHtml(Request $request)
    {
        Cache::clear();
        $id = $request->post("id");
        $searchType = $request->post("searchType");
        $type = $request->post("type");
        if ($id && $searchType && $type) {
            //重新生成
            (new Detailrestatic())->exec_refilestatic($id, $searchType, $type);
        }
    }


    /**
     * 获取单条数据内容
     * @param $type
     * @param $name
     * @return array|string
     */
    public function staticOneHtml($type, $name)
    {
        return $this->staticOne($type, $name);
    }

    /**
     * 修改指定静态文件的内容 比如模板之类
     * @param $type
     * @param $name
     * @return array
     */
    public function generateOne($type, $name)
    {
        $content = $this->request->post("content");
        if (empty($content)) {
            return $this->resultArray("数据为空");
        }
        $this->generateStaticOne($type, $name, $content);
    }


    /**
     * url 安全的base64 编码
     * @access private
     */
    private function urlsafe_b64encode($string)
    {
        $data = base64_encode($string);
        $data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
        return $data;
    }


    /**
     * oss 测试
     */
    public function ossdemo()
    {
        $accessKeyId = "mHENtCjneaNtqGOC";
        $accessKeySecret = "iIaCOZXiqrbk81mwn8t3fTtNFOXyeJ";
        $endpoint = "oss-cn-qingdao.aliyuncs.com";
        $bucket = "salesman1";
        $object = "141414.jpg";
        $filePath = __FILE__;

        //图片加水印
//        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
//        $download_file = 'demo.jpg';
//        $water = '山东强比信息技术有限公司';
//        $code = $this->urlsafe_b64encode($water);
//        $options = array(
//            OssClient::OSS_FILE_DOWNLOAD => $download_file,
//            OssClient::OSS_PROCESS => "image/watermark,text_{$code},color_FFFFFF");
//        $ossClient->getObject($bucket, $object, $options);

//        try{
//            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
//            $ossClient->uploadFile($bucket, $object, $filePath);
//        } catch(OssException $e) {
//            printf(__FUNCTION__ . ": FAILED\n");
//            printf($e->getMessage() . "\n");
//            return;
//        }
//        print(__FUNCTION__ . ": OK" . "\n");
//        exit;


//        创建资源包
//        try {
//            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
//            $ossClient->createBucket($bucket, OssClient::OSS_ACL_TYPE_PRIVATE);
//        } catch (OssException $e) {
//            printf(__FUNCTION__ . ": FAILED\n");
//            printf($e->getMessage() . "\n");
//            return;
//        }
//        print(__FUNCTION__ . ": OK" . "\n");
//        EXIT;


//      下载资源
//        $object = "141414.jpg";
//        $localfile = "141414.php";
//        $options = array(
//            OssClient::OSS_FILE_DOWNLOAD => $localfile,
//        );
//        try {
//            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
//            $ossClient->getObject($bucket, $object, $options);
//        } catch (OssException $e) {
//            printf(__FUNCTION__ . ": FAILED\n");
//            printf($e->getMessage() . "\n");
//            return;
//        }
//        print(__FUNCTION__ . ": OK, please check localfile: 'upload-test-object-name.txt'" . "\n");
//
    }

}
