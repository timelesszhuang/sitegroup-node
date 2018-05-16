<?php
// +----------------------------------------------------------------------
// | Description: 用户
// +----------------------------------------------------------------------
// | Author: linchuangbin <linchuangbin@honraytech.com>
// +----------------------------------------------------------------------

namespace app\tool\model;


use think\Cache;
use think\Db;
use think\Model;

class Childsitelist extends Model
{
    public function childsitelistcache($site_id)
    {
        return Cache::remember('childsitelist' . $site_id, function () use ($site_id) {
            $childsitelist = $this->where(['site_id' => $site_id])->field('district_id as id,en_name,name,p_id')->order(['sort' => 'desc', 'district_id' => 'asc'])->select();
            $site_info = Db::name('Site')->where(['id' => $site_id])->find();
            if(!($site_info&&$childsitelist)){
                return [];
            }
            foreach ($childsitelist as $key => $value) {
                $childsitelist[$key]['url'] = 'http://' . $value['en_name'] . $site_info['domain'];
            }
            return $childsitelist;
        });
    }
}