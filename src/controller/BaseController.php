<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

namespace vitphp\vitlite\controller;
use think\facade\Db;
use app\BaseController as ThinkController;

use think\App;
use think\db\exception\PDOException;
use think\db\Query;
use think\db\Where;
use think\facade\Cache;
use think\facade\View;
use vitcache\VitCache;
use vitphp\vitlite\auth\AnnotationAuth;
use vitphp\vitlite\middleware\CheckAccess;
use vitphp\vitlite\traits\Jump;

class BaseController extends ThinkController
{
    use Jump;

    protected $middleware = [
        CheckAccess::class
    ];

    /**
     * 初始化
     */
    protected function initialize()
    {

    }

    /**
     * 构造函数
     * BaseAdmin constructor.
     * @param App|null $app
     */
    public function __construct(App $app = null)
    {
        parent::__construct($app);
        # 获取登录用户的id
        $login_id = session('admin.id');
        global $_USER_;
        $_USER_ = VitCache::getUserById($login_id);
        session('admin', $_USER_);

        // 面包屑数据
        # 模块
        $module = $this->app->http->getName();
        # 控制器
        $controller = parse_name($this->request->controller(),0);
        # 方法
        $action = $this->request->action();

        $ctitie = '';
        $atitie = '';
        View::assign(['ctitle'=>$ctitie,'atitle'=>$atitie]);

        // 当前控制器
        $classuri = $this->app->http->getName().'/'.$this->request->controller();
        $name = $this->app->http->getName();

        $menu = Db::name('menu')->where(['app'=>'admin','status'=>'1'])->select()->toArray();

        //权限菜单分配
        $auth_map = Db::name('auth_map')->where('admin_id',$login_id)->select();
        // 下面这个不会合并角色
        //  $auth_map = Db::name('auth_map')->where('admin_id',$login_id)->find();
        if (!$auth_map->isEmpty()){
            $roleIds = array_column($auth_map->toArray(), 'role_id');
            // 下面这个不会合并角色
            // $role_nodes = Db::name('auth_nodes')->where('rule_id',$auth_map['role_id'])->group('node')->column('node');
            $role_nodes = Db::name('auth_nodes')->where('rule_id','in',$roleIds)->group('node')->column('node');
            $role_nodes = array_unique($role_nodes);
            $user_nodes = Db::name('auth_nodes')->where('uid',$login_id)->column('node');
            $nodes = array_map(function ($d){
                return strtolower($d);
            },array_merge($role_nodes, $user_nodes));
            $data = [];
            $s = AnnotationAuth::getAddonsAuth("index",false,false)[0]['list'];
            $nodes_codes = [];
            $nodes_keys = [];
            foreach ($s as $v){
                $node = strtolower($v['node']);
                $nodes_codes[] = $node;
                $nodes_keys[$node] = $v;
            }
            foreach ($menu as $v){
                if(isset($nodes_keys[$v['url']])){
                    $n = $nodes_keys[$v['url']];
                    if(!isset($n['meta']['auth'])){
                        $n['meta']['auth'] = 0;
                    }
                    if($n['meta']['auth'] === 1){
                        if(in_array($v['url'], $nodes)){
                            $data[] = $v;
                            continue;
                        }else{
                            continue;
                        }
                    }
                }
                if(in_array($v['url'], $nodes) || $v['url'] === "#"){
                    $data[] = $v;
                }
            }
            $menu = $data;
        }

        $menu = $this->getTree(0,1,$menu);
        $data = [];
        // create at 20210816223 查看另一个框架,判断了url为#或没有子项,则不显示
        foreach ($menu as $i=>$item){
            if($item['url'] == "#" && empty($item['children'])){
                continue;
            }
            $data[] = $item;
        }
        $menu = $data;

        //查询用户权限
        $userauth = Db::name('auth_map')->alias('a')->join('vit_role b','a.role_id=b.id')
            ->where('admin_id',session('admin.id'))
            ->field('a.*,b.title')
            ->select()->toArray();
        View::assign([
            'menu'=>$menu,
            'classuri'=>$classuri,
            'userauth'=>$userauth
        ]);
    }

    /**
     * 万能列表方法
     * @param $query
     * @param bool $multipage
     * @param array $param
     * @return mixed
     */
    protected function _list($query,$multipage = true,$pageParam = [])
    {
        if ($this->request->isGet()){
            if ($multipage){
                $pageResult = $query->paginate(null,false,['query'=>$pageParam]);
                View::assign('page',$pageResult->render());
                $result = $pageResult->all();
            }else{
                $result = $query->select();
            }
            if (false !== $this->_callback('_list_before', $result, [])) {
//                $list = $this->getTree(0,1,$result);
                $list = $result;
                View::assign('list',$list);
                return View::fetch();
            }
            return $result;
        }
    }

    /**
     * 获取树形结构菜单
     * @param $parentid
     * @param int $floor
     * @return mixed
     */
    public function getTree($parentid,$floor = 1,$menu_list){
        $data = $this->getLevel($parentid,$menu_list);
        $floor++;
        $result = array();
        if(is_array($data)){
            foreach ($data as $k=>$v){
                $result[$k] = $v;
                $child = $this->getLevel($v['id'],$menu_list);
                if ($child && $floor <= 3) {
                    $result[$k]['children'] = $child;
                }
            }
        }
        return $result;
    }

    /**
     * 根据父类ID查找菜单的子项
     * @param $parentid
     * @return array
     */
    public function getLevel($parentid,$menu_list){
        $result = array();
        foreach ($menu_list as $key=>$value){
            if($value['pid']==$parentid){
                $result[] = $value;
            }
        }
        return $result;
    }


    /**
     * 表单万能方法
     * @param $query
     * @param string $tpl
     * @param string $pk
     * @param array $where
     * @return array|mixed
     */
    protected function _form(Query $query, $tpl = '', $pk='', $where = []) {
        $pk = $pk?:($query->getPk()?:'id');
        $defaultPkValue = isset($where[$pk])?$where[$pk]:null;
        $pkValue = $this->request->get($pk,$defaultPkValue);

        if ($this->request->isGet()){
            $vo = ($pkValue !== null) ? $query->where($pk,$pkValue)->where(new Where($where))->find():[];
            if (false !== $this->_callback('_form_before', $vo)) {
                return View::fetch($tpl,['vo'=>$vo]);
            }
            return $vo;
        }
        $data = $this->request->post();
        if (false !== $this->_callback('_form_before', $data)) {
            try{
                if (isset($data[$pk])){
                    $where[$pk] = ['=',$data[$pk]];
                    $result = $query->where(new Where($where))->update($data);
                    $last_id = $data[$pk];
                }else{
                    $result = $query->insert($data);
                    $last_id = $query->getLastInsID();
                }
            }catch (PDOException $e){
                $this->error($e->getMessage());
            }
            //手动释放所有查询条件（此处TP有bug  导致数据库链接对象拿到错误的表名）
//            $query->removeOption();
            // 重置查询对象
            $query = $query->newQuery();
            $last_data = $query->find($last_id);
            if (false !== $this->_callback('_form_after',  $last_data)) {
                if ($result !== false) {
                    $this->success('恭喜, 数据保存成功!', '');
                }
                $this->error('数据保存失败, 请稍候再试!');
            }else{
                $this->error("表单后置操作失败，请检查数据！");
            }
        }
    }

    /**
     * @param $ids
     * @throws PDOException
     * @throws \think\Exception
     */
    protected function _del($query, $ids)
    {
        $fields = $query->getTableFields();
        if (in_array('is_deleted',$fields)){
            $res = $query->whereIn('id', $ids)
                ->update(['is_deleted' => 1]);
        }else{
            $res = $query->whereIn('id', $ids)
                ->delete();
        }
        if ($res) {
            $this->success('删除成功！', '');
        } else {
            $this->error("删除失败");
        }
    }

    protected function _change($query, $id, $data)
    {
        $res = $query->where('id', $id)->update($data);
        if ($res) {
            $this->success('切换状态操作成功！');
        } else {
            $this->error('切换状态操作失败！');
        }
    }

    /**
     * 回调唤起
     * @param $method
     * @param $data1
     * @param $data2
     * @return bool
     */
    protected function _callback($method, &$data)
    {
        foreach ([$method, "_" . $this->request->action() . "{$method}"] as $_method) {
            if (method_exists($this, $_method) && false === $this->$_method($data)) {
                return false;
            }
        }
        return true;
    }

}
