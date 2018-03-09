<?php

namespace app\tool\controller;

use app\common\controller\Coding;
use think\Request;


/**
 * 配置文件中的页面静态化
 * 执行首页静态化相关操作
 */
class Template extends CommonToken
{

    public $templatepath = " ";
    public $templatestaticpath = "";
    public $templatestaticbkpath = '';
    public $templatehtmlbkpath = '';
    public $staticsuffix = [
        '.ico' => 'image',
        '.jpg' => 'image',
        '.jpeg' => 'image',
        '.png' => 'image',
        '.gif' => 'image',
        '.bmp' => 'image',
        '.svg' => 'image',
        '.tif' => 'image',
        '.css' => 'css',
        '.less' => 'css',
        '.saas' => 'css',
        '.js' => 'js',
        '.ttf' => 'font',
        '.eot' => 'font',
        '.otf' => 'font',
        '.woff' => 'font',
    ];

    public $htmlsuffix = [
        '.html' => 'html',
        '.htm' => 'html',
        '.phtml' => 'html',
        '.tpl' => 'html'
    ];


    public function _initialize()
    {
        parent::_initialize();
        $this->templatepath = ROOT_PATH . "public/template/";
        $this->templatestaticpath = ROOT_PATH . 'public/templatestatic/';
        $this->templatestaticbkpath = ROOT_PATH . 'public/templatebk/templatestatic/';
        $this->templatehtmlbkpath = ROOT_PATH . 'public/templatebk/templatehtml/';
    }

    /**
     * Converts bytes into human readable file size.
     *
     * @param string $bytes
     * @return string human readable file size (2,87 Мб)
     * @author Mogilev Arseny
     */
    public function FileSizeConvert($bytes)
    {
        $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );
        foreach ($arBytes as $arItem) {
            if ($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                $result = strval(round($result, 2)) . " " . $arItem["UNIT"];
                break;
            }
        }
        return $result;
    }

    /**
     * 获取文件的后缀
     * @access private
     */
    private function getfilesuffix($file)
    {
        $type = 'other';
        $pathinfo = pathinfo($file);
        $filesuffix = array_key_exists('extension', $pathinfo) ? $pathinfo['extension'] : '';
        if (!$filesuffix) {
            return ['type' => $type, 'suffix' => ''];
        }
        if (array_key_exists($filesuffix, $this->staticsuffix)) {
            $type = $this->staticsuffix[$filesuffix];
            return ['type' => $type, 'suffix' => $filesuffix];
        }
        if (array_key_exists($filesuffix, $this->htmlsuffix)) {
            $type = $this->htmlsuffix[$filesuffix];
            return ['type' => $type, 'suffix' => $filesuffix];
        }
        return ['type' => $type, 'suffix' => $filesuffix];
    }


    /**
     * 模板列表
     * @access public
     */
    public function templatelist()
    {
        $list = Request::instance()->get('list');
        if ($list) {
            switch ($list) {
                case 'static':
                    return $this->templatefilelist($this->templatestaticpath, '/templatestatic/');
                    break;
                case 'html':
                    return $this->templatefilelist($this->templatepath, '/template/');
                    break;
            }
        } else {
            //如果类型不存在的话亲你跪求模板文件
            return $this->templatefilelist($this->templatepath, '/template/');
        }
    }


    /***
     * 静态文件列表相关
     * @access public
     */
    public function templatefilelist($path, $relativepath)
    {
        //获取文件列表
        $fileArray = [];
        if (false != ($handle = opendir($path))) {
            while (false !== ($file = readdir($handle))) {
                //去掉"“.”、“..”以及带“.xxx”后缀的文件
                if ($file != "." && $file != ".." && $file != ".env" && $file != 'README.md') {
                    $suffix = $this->getfilesuffix($file);
                    $filepath = $path . $file;
                    $filesize = filesize($filepath);
                    $formatsize = $filesize ? $this->FileSizeConvert($filesize) : '0';
                    $relativefilepath = $relativepath . $file;
                    $downloadpath = $this->getDownLoadpath($relativefilepath);
                    $fileArray[] = [
                        'name' => $file,
                        'path' => $this->siteurl . $relativefilepath,
                        //下载路径
                        'downloadpath' => $downloadpath,
                        //字节数
                        'size' => $formatsize,
                        //文件类型
                        'type' => $suffix['type'],
                        //后缀
                        'suffix' => $suffix['suffix'],
                        //文件的修改时间
                        'filemtime' => date('Y-m-d H:i:s', filemtime($filepath)),
                        //文件创建时间
                        'filectime' => date('Y-m-d H:i:s', filectime($filepath)),
                    ];
                }
            }
            //关闭句柄
            closedir($handle);
        }
        if ($fileArray) {
            $data = ['status' => 'success', 'msg' => '模板文件列表获取成功。', 'filelist' => $fileArray];
        } else {
            $data = ['status' => 'failed', 'msg' => '模板未传递或没有获取到模板文件。', 'filelist' => $fileArray];
        }
        return json_encode($data);
    }


    /**
     * 获取下载链接
     * @access public
     */
    public function getDownLoadpath($filepath)
    {
        return $this->siteurl . '/downloadtemplatefile?filetoken=' . Coding::tiriEncode($filepath);
    }


    /**
     * 获取 Oss 相关文件
     * list：static html 等数据
     * 1、 新上传文件 添加 会传递 oss_path 跟 filename 类型 flag  add
     *
     * 2、 修改文件内容的情况  传递 oss_path 跟 filename  类型 flag update
     *
     * 3、 替换文件  添加 osspath 跟 file_name 类型 要替换另外一个 flag replace
     * @access public
     */
    public function manageTemplateFile()
    {
        $list = Request::instance()->get('list');
        //表示是add update 还是replace模板文件
        $flag = Request::instance()->get('flag');
        //要拉取的文件
        $osspath = Request::instance()->get('osspath');
        //要操作的文件名
        $filename = Request::instance()->get('filename');
        $rootpath = $this->templatepath;
        $filepath = $rootpath . $filename;
        $bkpath = $this->templatehtmlbkpath;
        if ($list == 'static') {
            $rootpath = $this->templatestaticpath;
            $filepath = $rootpath . $filename;
            $bkpath = $this->templatestaticbkpath;
        }
        if ($flag == 'add') {
            // 添加文件 oss 拉取下来
            if (file_exists($filepath)) {
                return json_encode(['status' => 'failed', 'msg' => '服务器已经存在该文件，请选择替换或查证后再试']);
            }
            $status = $this->ossGetObject($osspath, $filepath);
            return json_encode(['status' => $status['status'] ? 'success' : 'failed', 'msg' => $status['status'] ? '添加成功' : '添加失败，请稍后重试']);
        } else if ($flag == 'update') {
            if (!file_exists($filepath)) {
                return json_encode(['status' => 'failed', 'msg' => '您修改的文件不存在，请稍后重试。']);
            }
            //修改文件 只需要file_get_content
            //  需要备份在templatebk中 文件的内容
            file_put_contents($bkpath . date('Y-m-d-H:i:s') . $filename, file_get_contents($filepath));
            if (file_put_contents($filepath, file_get_contents($osspath)) === false) {
                //失败的情况
                return json_encode(['status' => 'failed', 'msg' => '更新失败请稍后重试。']);
            }
            return json_encode(['status' => 'success', 'msg' => '修改文件成功。']);
        } else {
            //replace 替换
            //首先备份下文件
            copy($filepath, $bkpath . date('Y-m-d-H-i-s') . $filename); //拷贝到新目录
            unlink($filepath); //删除旧目录下的文件
            $status = $this->ossGetObject($osspath, $filepath);
            return json_encode(['status' => $status['status'] ? 'success' : 'failed', 'msg' => $status['status'] ? '添加成功' : '添加失败，请稍后重试']);
        }
        //获取 oss 相关数据
    }


    /**
     * 模板读操作
     * 需要传递 site_id 跟 filename 不带 .html(只需要filename)
     * @access public
     * @todo 如果是图片之类其他内容不需要读取内容
     */
    public function templateread()
    {
        $list = Request::instance()->get('list');
        $filename = Request::instance()->get('filename');
        $path = $this->templatepath;
        $file_path = $path . $filename;
        if ($list == 'static') {
            $path = $this->templatestaticpath;
            $file_path = $path . $filename;
        }
        //要操作的文件名
        if (!file_exists($file_path)) {
            return json_encode(['status' => 'failed', 'msg' => '文件不存在,请查证后再试。', 'filename' => $filename, 'content' => '']);
        }
        //读取文件内容
        $content = file_get_contents($file_path);
        if ($content) {
            return json_encode(['status' => 'success', 'msg' => '获取内容成功。', 'filename' => $filename, 'content' => $content]);
        } else {
            return json_encode(['status' => 'failed', 'msg' => '获取内容失败，请稍后重试。', 'filename' => $filename, 'content' => '']);
        }
    }


}
