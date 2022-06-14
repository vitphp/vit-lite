<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

namespace vitphp\vitlite;

use think\facade\Db;
use think\facade\Session;
use vitphp\vitlite\auth\AnnotationAuth;
use vitphp\vitlite\model\SystemAdmin;

/**
 * 权限验证类
 * Class Auth
 * @package LiteAdmin
 */
class Auth
{

    /**
     * 执行验证
     * @param $path
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function auth($path)
    {
        $sp_path = $path;
        $username = Session::get('admin.username');

        if ($username === "admin") {
            return true;
        }
        if(is_string($sp_path)){
            $paths =  explode('/', $path);
            $path = [];
            $path[2] = array_pop($paths);
            $path[0] = array_shift($paths);
            $path[1] = implode('.', $paths);
        }
        $admin_id = Session::get('admin.id');
        $node = AnnotationAuth::checkAuth("app\\" . $path[0] . "\\controller\\" . $path[1], $path);


        if (!$node) {
            return false;
//            halt('当前PATH（'.$path.'）没有加入权限管理列表');
        }
        $meta = $node['meta'];
        # 如果没有设置，则设置默认
        if(!isset($meta['login'])){
            $meta['login'] = 1;
        }
        if(!isset($meta['auth'])){
            if($meta['login'] == 0){
                $meta['auth'] = 0;
            }else{
                $meta['auth'] = 1;
            }
        }
        # 如果要校验权限，那么必须登录
        if ($meta['auth'] == '1') {
            $meta['login'] = 1;
        }

        # login为0，不需要登录
        if($meta['login'] == 0){
            return true;
        }

        if ($meta['login'] == '1') {
            if ($admin_id) {
                if ($meta['auth'] == '1') {
                    # 需要权限校验
                    $role_nodes = Db::table('vit_auth_nodes')
                        ->whereIn('rule_id', Db::table('vit_auth_map')
                            ->where('admin_id', $admin_id)
                            ->column('role_id'))
                        ->group('node')
                        ->select()->column('node');
                    $user_nodes = Db::table('vit_auth_nodes')
                        ->where('uid', $admin_id)
                        ->select()->column('node');
                    $access = array_merge($role_nodes, $user_nodes);
                    $access = array_map(function ($d){
                        return strtolower($d);
                    },$access);
                    return in_array(strtolower($node['node']), $access);
                }
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * 获取当前用户全部权限 节点ID
     * @return array
     */
    public static function getAllAcess()
    {

        static $access;

        if (empty($access)) {

            $admin_id = Session::get('admin.id');

            if (!$admin_id) {
                return $access = [];
            }

            $admin = SystemAdmin::with('roles')
                ->where('id', $admin_id)
                ->find();

            $access = [];

            foreach ($admin->roles as $role) {
                if ($role['status'] !== 1) {
                    continue;
                }
                $access = array_merge($access, explode(',', $role['access_list']));
            }

            $access = array_unique($access);
        }

        return $access;
    }
}