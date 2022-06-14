<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

namespace vitphp\vitlite\model;

use think\Model;

/**
 * 后台用户模型
 * Class SystemAdmin
 * @package app\common\model
 */
class SystemAdmin extends Model
{

    protected $name = "users";

    /**
     * 用户名 字段
     * @return string
     */
    public static function username()
    {
        return 'username';
    }
    /**
     * 关联角色
     * @return \think\model\relation\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(SystemRole::class,SystemAuthMap::class,'role_id','admin_id');
    }
}