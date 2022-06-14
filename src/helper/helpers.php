<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

define('IA_ROOT', str_replace("\\", '/', dirname(dirname(__FILE__))));

use think\facade\Db;

//返回json
function jsonErrCode($msg){
    $result = [
        'code' => 0,
        'msg' => $msg,
    ];
    echo json_encode($result);exit;
}
function jsonSucCode($msg,$data=""){
    $result = [
        'code' => 1,
        'msg' => $msg,
        'data'=>$data
    ];
    echo json_encode($result);exit;
}

/**
 * 获取ip地址
 * @return mixed|string
 */
function getip() {
    static $ip = '';
    $ip = $_SERVER['REMOTE_ADDR'];
    if(isset($_SERVER['HTTP_CDN_SRC_IP'])) {
        $ip = $_SERVER['HTTP_CDN_SRC_IP'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
        foreach ($matches[0] AS $xip) {
            if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                $ip = $xip;
                break;
            }
        }
    }
    if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $ip)) {
        return $ip;
    } else {
        return '127.0.0.1';
    }
}

//随机32位字符串
if (!function_exists('createNoncestr')) {
    function createNoncestr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
}

/**
 * 创建二维码
 */
function createQrcode($url)
{
    if($url){
        require root_path() . 'extend' . DIRECTORY_SEPARATOR . 'qrcode' . DIRECTORY_SEPARATOR . 'phpqrcode.php';
        $errorCorrectionLevel = 'L';
        $matrixPointSize = '6';
        QRcode::png($url, false, $errorCorrectionLevel, $matrixPointSize);
        die;
    }
}

/**
 * 保存设置
 * @param $name
 * @param string $value
 * @return int|string
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\DbException
 * @throws \think\db\exception\ModelNotFoundException
 */
function setSetting($name, $value='',$addons=''){
    $data = ['name' => $name, 'value' => $value, 'addons' => !empty($addons)?$addons:'setup'];
    $get = Db::name('settings')->where(['name'=>$data['name'],'addons'=>$data['addons']])->find();
    if($get){
        $res = Db::name('settings')->where(['id'=>$get['id']])->update($data);
    }else{
        $res =  Db::name('settings')->insert($data);
    }
    return $res;
}

/**
 * 获取设置
 * @param $name
 * @return mixed
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\DbException
 * @throws \think\db\exception\ModelNotFoundException
 */
function getSetting($name, $addons="")
{
    global $_SETTING_;

    if (!$_SETTING_) {
        $sett = DB::name('settings')->where('1=1')->order('id desc ')->select();
        $_SETTING_ = $sett->isEmpty() ? [] : $sett->toArray();
    }

    $data = [];
    if($_SETTING_){
        foreach ($_SETTING_ as $k=>$v){
            $data[$v['addons']][$v['name']] = $v['value'];
        }
        if($addons){
            return isset($data[$addons][$name]) ?  $data[$addons][$name] : "";
        }else{
            return isset($data["setup"][$name]) ?  $data["setup"][$name] : "";
        }
    }else{
        return '';
    }
}

function media($fileUrl, $storage =null, $domain = false){
    if(substr($fileUrl,0,4) == '/app'){
        // 如果是/app斜杠开头的都是本地
        return $fileUrl;
    }
    if(substr($fileUrl,0,8) == '/upload/'){
        return '/public'.$fileUrl;
    }
    if(substr($fileUrl,0,1) == '/' && !is_numeric(substr($fileUrl,1,1))){
        // 如果是/开头，并且第二位不是数字，直接返回
        return $fileUrl;
    }else if(substr($fileUrl,0,1) !== '/'){
        return $fileUrl;
        // 只要不是/开头都拼接上当前地址
        $storage = getSetting("atta_type");
        $storageMap = [
            '2'=>'domain',
            '3'=>'tx_domain',
            '4'=>'al_domain',
            '5'=>'ftp_domain'
        ];
        $domainStr = getSetting($storageMap[$storage] ?? '','setup');
        return $domainStr.str_replace("//","/",'/'.$fileUrl);
    }
    // 如果是https://,http://,//开头直接返回
    if(strpos($fileUrl, "http://") !== false
        || strpos($fileUrl, "https://") !== false
        || strpos($fileUrl, "//") !== false
    ){
        return $fileUrl;
    }
    // 如果$storage 不为空
    if(!is_null($storage)){
        // 如果 $storage == 'act'则取当前默认$storage
        if($storage == 'act'){
            $storage = getSetting("atta_type");
        }
        $storageMap = [
            '2'=>'domain',
            '3'=>'tx_domain',
            '4'=>'al_domain',
            '5'=>'ftp_domain'
        ];
        $name = $storageMap[$storage] ?? '';
        if($name){
            $domainStr = getSetting($name,'setup');
            // 如果有设置domain，则返回数组
            if($domain){
                return [$domainStr,$fileUrl];
            }
            // 如果域名是/结尾直接拼接
            if(substr($domainStr,strlen($domainStr)-1,1) == '/'){
                $fileSrc = $domainStr.$fileUrl;
            }else{
                // 否则加上斜杠再拼接
                $fileSrc =  $domainStr.str_replace("//","/",'/'.$fileUrl);
            }
            return $fileSrc;
        }
    }
    // 如果是https://,http://,//开头直接返回
    if(strpos($fileUrl, "http://") !== false
        || strpos($fileUrl, "https://") !== false
        || strpos($fileUrl, "//") !== false
    ){
        return $fileUrl;
    }else{
        // 如果是/app/开头的直接返回
        if(substr($fileUrl,0,5) === '/app/'){
            return $fileUrl;
        }
        // 否则拼接绝对路径
        return ROOT_PATH.$fileUrl;
    }
}

/**
 * 生成字符串
 * @param int $length 要生成的长度
 * @param int $type 生成字符串内容的范围
 * @return string|null
 */
function redom($length = 8, $type = 0)
{
    $strPol = [
        "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz",
        "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789",
        "abcdefghijklmnopqrstuvwxyz0123456789",
        "0123456789",
        "abcdefghijklmnopqrstuvwxyz"
    ];
    $str = null;
    $max = strlen($strPol[$type]) - 1;

    for ($i = 0; $i < $length; $i++) {
        $str .= $strPol[$type][rand(0, $max)];
    }

    return $str;
}

/**
 * 密码加密
 * @param $pass
 * @return false|string|null
 */
function pass_en($pass){
    $options =[
        "cost"=>config('admin.cost')
    ];

    return password_hash($pass,PASSWORD_DEFAULT, $options);
}

/**
 * 密码校验
 * @param $pass
 * @param $hash
 * @return bool
 */
function pass_compare($pass, $hash){
    return password_verify($pass, $hash);
}

/**
 * 唯一日期编码
 * @param integer $size
 * @param string $prefix
 * @return string
 */
function uniqidDate($size = 16, $prefix = '')
{
    if ($size < 14) $size = 14;
    $string = $prefix . date('Ymd') . (date('H') + date('i')) . date('s');
    while (strlen($string) < $size) $string .= rand(0, 9);
    return $string;
}

if (!function_exists('app_http_request')) {
    /**
     * http/https请求
     * @param string $url 请求的链接，若为get则将参数拼接而成
     * @param string $data 请求的参数，json格式
     * @param array $header 请未头，数组
     * @param array $extra 其他参数，用于扩展curl
     * @return array|bool|string
     */
    function app_http_request($url, $data = null, $header = [], $extra = [])
    {
        if ((!$data || empty($data)) && !$header) {
            $header = [
                'Content-Type' => 'application/json; charset=utf-8',
            ];
        }
        if (empty($header['Content-Type'])) $header['Content-Type'] = 'application/json; charset=utf-8';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        foreach ($extra as $opt => $value) {
            if (strpos($opt, 'CURLOPT_') !== false) {
                curl_setopt($curl, constant($opt), $value);
            } else if (is_numeric($opt)) {
                curl_setopt($curl, $opt, $value);
            }
        }
        if (!empty($header)) {
            foreach ($header as $key => $value) {
                $header[$key] = ucfirst($key) . ':' . $value;
            }
            $headers = array_values($header);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');

        $output = curl_exec($curl);
        curl_close($curl);

        return $output;
    }
}

/**
 * 权限校验
 * @param $path
 * @return mixed
 */
function auth($path){
    return \vitphp\mengzhe\Auth::auth($path);
}

/**
 * 替换系统自带的缓存解析方法
 * @param $json
 * @return mixed
 */
function cache_decode($json)
{
    return json_decode($json, true);
}

/**
 * 将手机号中间四位换成*号
 * @param $str
 * @return mixed
 */
function hide_phone($str)
{
    if ($str == '') return '';
    $resStr = substr_replace($str, '****', 3, 4);
    return $resStr;
}

if (!function_exists('save_sys_log')) {
    /**
     * 写入系统日志
     * @param string $authText 操作行为
     * @param string $description 操作描述
     */
    function save_sys_log($authText, $description=null, $userName=null)
    {
        $request = \think\facade\Request::instance();
        $app = app('http')->getName();
        $controller = $request->controller();
        $action = $request->action();
        $userName = $userName ?? session('admin.username');
        Db::name('sys_log')->insert([
            'user_name' => $userName,
            'auth' => $app . '/' . $controller . '/' . $action,
            'auth_text' => $authText,
            'description' => $description,
            'ip' => getip(),
            'create_time' => time()
        ]);
    }
}