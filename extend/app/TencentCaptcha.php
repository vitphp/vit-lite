<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 石家庄萌折科技有限公司 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/ 以获得更多细节。
// +----------------------------------------------------------------------
namespace app;

class TencentCaptcha
{
    protected $secretId;
    protected $secretKey;
    protected $captchaAppId;
    protected $appSecretKey;


    /**
     * 验证腾讯云验证码结果
     * @param $ticket
     * @param $randstr
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function captcha($ticket, $randstr)
    {
        $this->secretId = getsetting('tencent_sms_secret_id');
        $this->secretKey = getsetting('tencent_sms_secret_key');
        $this->captchaAppId = (int)getsetting('tencent_captcha_app_id');
        $this->appSecretKey = getsetting('tencent_captcha_app_secret_key');

        $host = "captcha.tencentcloudapi.com";
        $service = "captcha";
        $version = "2021-01-11";
        $action = "SendSms";
        $region = "ap-beijing";
        $timestamp = time();
        $algorithm = "TC3-HMAC-SHA256";

        $post = [
            'CaptchaType' => 9,
            'Ticket' => $ticket,
            'UserIp' => getip(),
            'Randstr' => $randstr,
            'CaptchaAppId' => $this->captchaAppId,
            'AppSecretKey' => $this->appSecretKey
        ];
        $publicHeader = $this->getHeader($host, $post);

        $result = app_http_request("https://".$host, json_encode($post), $publicHeader);
        $result = json_decode($result) ? json_decode($result, true) : [];
        $captchaCode = $result['Response']['CaptchaCode'] ?? -1;
        $msg = $result['Response']['Error']['Message'] ?? '验证失败';
        if($captchaCode!==1){
            return [-1, $msg];
        }

        return [1, '验证通过'];
    }

    private function getHeader($host, $post)
    {
        $publicHeader = [
            'X-TC-Action' => 'DescribeCaptchaResult',
            'X-TC-Timestamp' => time(),
            'X-TC-Version' => '2019-07-22',
            'Authorization' => ''
        ];
        $publicHeader['Authorization'] = $this->getAuthorization($host, $post);

        return $publicHeader;
    }

    private function getAuthorization($host, $post)
    {
        $timestamp = time();
        $service = "captcha";
        $algorithm = "TC3-HMAC-SHA256";

        // step 1: build canonical request string
        $httpRequestMethod = "POST";
        $canonicalUri = "/";
        $canonicalQueryString = "";
        $canonicalHeaders = "content-type:application/json; charset=utf-8\n"."host:".$host."\n";
        $signedHeaders = "content-type;host";
        $payload = json_encode($post);
        $hashedRequestPayload = hash("SHA256", $payload);
        $canonicalRequest = $httpRequestMethod."\n"
            .$canonicalUri."\n"
            .$canonicalQueryString."\n"
            .$canonicalHeaders."\n"
            .$signedHeaders."\n"
            .$hashedRequestPayload;

        // step 2: build string to sign
        $date = gmdate("Y-m-d", $timestamp);
        $credentialScope = $date."/".$service."/tc3_request";
        $hashedCanonicalRequest = hash("SHA256", $canonicalRequest);
        $stringToSign = $algorithm."\n"
            .$timestamp."\n"
            .$credentialScope."\n"
            .$hashedCanonicalRequest;
//        echo $stringToSign.PHP_EOL;

        // step 3: sign string
        $secretDate = hash_hmac("SHA256", $date, "TC3".$this->secretKey, true);
        $secretService = hash_hmac("SHA256", $service, $secretDate, true);
        $secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
        $signature = hash_hmac("SHA256", $stringToSign, $secretSigning);
//        echo $signature.PHP_EOL;

        // step 4: build authorization
        $authorization = $algorithm
            ." Credential=".$this->secretId."/".$credentialScope
            .", SignedHeaders=content-type;host, Signature=".$signature;
        return $authorization;
    }

}