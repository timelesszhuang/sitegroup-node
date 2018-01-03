<?php
/**
 * Created by PhpStorm.
 * oss 相关操作封装
 * User: 赵兴壮
 * Date: 17-6-12
 * Time: 上午9:44
 */

namespace app\tool\traits;

use OSS\OssClient;
use think\Config;

trait Osstrait
{

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
     * oss 对象上传
     * @param $object 服务器上文件名
     * @param $filepath 本地文件的绝对路径 比如/home/wwwroot/***.jpg
     */
    public function ossPutObject($object, $filepath)
    {
        $accessKeyId = Config::get('oss.accessKeyId');
        $accessKeySecret = Config::get("oss.accessKeySecret");
        $endpoint = Config::get('oss.endpoint');
        $bucket = Config::get('oss.bucket');
        $status = true;
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $ossClient->uploadFile($bucket, $object, $filepath);
            $msg = '上传成功';
        } catch (OssException $e) {
            $msg = $e->getMessage();
            $status = false;
        }
        return ['status' => $status, 'msg' => $msg];
    }


    /**
     * oss 获取对象
     * @access public
     */
    public function ossGetObject($object, $filepath)
    {
        $accessKeyId = Config::get('oss.accessKeyId');
        $accessKeySecret = Config::get("oss.accessKeySecret");
        $endpoint = Config::get('oss.endpoint');
        $bucket = Config::get('oss.bucket');
        $status = true;
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $filepath,
        );
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            //把 oss 的https://***/ 替换掉
            $object = str_replace(sprintf("https://%s.%s/", $bucket, $endpoint), '', $object);
            if (!$this->checkObjectExist($ossClient, $bucket, $object)) {
                //表示文件不存在的情况
                return true;
            }
            $ossClient->getObject($bucket, $object, $options);
            $msg = '获取成功';
        } catch (OssException $e) {
            $msg = $e->getMessage();
            $status = false;
        }
        return ['status' => $status, 'msg' => $msg];
    }


    /**
     * 删除 oss 中的对象
     * @access public
     * @param $object 要删除的对象  支持带着绝对路径
     * @return array
     */
    public function ossDeleteObject($object)
    {
        $accessKeyId = Config::get('oss.accessKeyId');
        $accessKeySecret = Config::get("oss.accessKeySecret");
        $endpoint = Config::get('oss.endpoint');
        $bucket = Config::get('oss.bucket');
        //如果路径里边包含绝对https 之类路径则替换掉 https://***/
        $object = str_replace($url = sprintf("https://%s.%s/", $bucket, $endpoint), '', $object);

        $status = true;
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            if (!$this->checkObjectExist($ossClient, $bucket, $object)) {
                //表示水印图片不存在的情况
                return $status;
            }
            $ossClient->deleteObject($bucket, $object);
            $msg = '删除成功';
        } catch (OssException $e) {
            $status = false;
            $msg = $e->getMessage();
        }
        return ['status' => $status, 'msg' => $msg];
    }


    /**
     * 获取oss 相关的水印图片到本地 images 目录下
     * @param $object 带着url的 相关oss路径
     * @param $localfilename  图片名 只需要文件名
     * @param $water 水印
     * @return array
     */
    public function get_osswater_img($object, $localfilename, $water)
    {
        $localfilename = ROOT_PATH . 'public/images/' . $localfilename;
        if (file_exists($localfilename)) {
            //文件已经存在的 不需要再请求一次
            return true;
        }
        $accessKeyId = Config::get('oss.accessKeyId');
        $accessKeySecret = Config::get("oss.accessKeySecret");
        $endpoint = Config::get('oss.endpoint');
        $bucket = Config::get('oss.bucket');
        //把 oss 的https://***/ 替换掉
        $object = str_replace(sprintf("https://%s.%s/", $bucket, $endpoint), '', $object);
        //图片加水印
        $status = true;
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            if (!$this->checkObjectExist($ossClient, $bucket, $object)) {
                //表示水印图片不存在的情况
                return true;
            }
            if ($water) {
                $code = $this->urlsafe_b64encode($water);
                $options = array(
                    OssClient::OSS_FILE_DOWNLOAD => $localfilename,
                    OssClient::OSS_PROCESS => "image/watermark,text_{$code},color_FFFFFF");
                $ossClient->getObject($bucket, $object, $options);
            } else {
                $ossClient->getObject($bucket, $object);
            }
        } catch (Exception $ex) {
            $status = false;
        }
        return $status;
    }


    /**
     * 判断object是否存在
     *
     * @param OssClient $ossClient OSSClient实例
     * @param string $bucket bucket名字
     * @return null
     */
    function checkObjectExist($ossClient, $bucket, $object)
    {
        $exist = false;
        try {
            $exist = $ossClient->doesObjectExist($bucket, $object);
        } catch (OssException $e) {
            //不存在的情况
        }
        return $exist;
    }


    /**
     * 生成唯一的string
     * @access public
     */
    public function formUniqueString()
    {
        return md5(uniqid(rand(), true));
    }


    /**
     * 解析文件路径 获取文件的后缀
     * @param string $fileurl 要获取后缀的文件url 路径
     * @access public
     */
    public function analyseUrlFileType($fileurl)
    {
        $type = '';
        $imgpathinfo = pathinfo(parse_url($fileurl)['path']);
        if (array_key_exists('extension', $imgpathinfo)) {
            $type = $imgpathinfo['extension'];
        }
        return $type;
    }

}