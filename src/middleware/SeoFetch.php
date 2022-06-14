<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------


namespace vitphp\vitlite\middleware;


use think\facade\Db;
use think\facade\View;
use think\Request;
use vitphp\vitlite\Tree;

class SeoFetch
{
    public function handle(Request $request,\Closure $next)
    {
        $domain = $request->host();
        $site = Db::name('system_site')->where('domain',$domain)->find();
        if ($site){
        }else{
           // header("HTTP/1.1 404 Not Found");exit;
            $site = Db::name('system_site')->where('uid','1')->find();
        }
        $menu = Db::name('menu')->where(['app'=>'index','status'=>'1','uid'=>$site['uid']])->select()->toArray();
         $menus = "";
         if($menu){
             $menus = Tree::array2tree($menu);
         }
        $site = $site?$site:[];
        View::assign([
            'system_site'=>$site,
             'menus'=>$menus,
        ]);
        return $next($request);
    }
}