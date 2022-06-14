<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 石家庄萌折科技有限公司 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/ 以获得更多细节。
// +----------------------------------------------------------------------
namespace app;

class TencentSms
{
    protected $secretId;
    protected $secretKey;
    protected $appid;
    protected $sign;

    /**
     * @param string|array $mobile 手机号
     * @param $templateId
     * @param array $params 发送参数
     * @return array
     */
    public function request($mobile, $templateId, $params=[])
    {
        $this->secretId = getsetting('tencent_sms_secret_id');
        $this->secretKey = getsetting('tencent_sms_secret_key');
        $this->appid = getsetting('tencent_sms_appid');
        $this->sign = getsetting('tencent_sms_sign');
//        $this->secretId = "AKIDXtzoQmrfBSRowoPkob8PP8bnjOnvnOoy";
//        $this->secretKey = "vZw3tSkmX1EBsISLyKPDl33nGSN2PWNG";
//        $this->appid = '1400589116';
//        $this->sign = '萌折';

        $host = "sms.tencentcloudapi.com";
        $service = "sms";
        $version = "2021-01-11";
        $action = "SendSms";
        $region = "ap-beijing";
        $timestamp = time();
        $algorithm = "TC3-HMAC-SHA256";

        $toMobile = [];
        if (is_array($mobile)) {
            foreach ($mobile as &$mobile) {
                array_push($toMobile, '+86' . $mobile);
            }
        } else {
            array_push($toMobile, '+86' . $mobile);
        }
        $post = [
            'PhoneNumberSet' => $toMobile,
            'SmsSdkAppId' => $this->appid,
            'TemplateId' => $templateId,
            'SignName' => $this->sign,
            'TemplateParamSet' => $params
        ];
        $publicHeader = $this->getHeader($host, $post);

        $result = app_http_request("https://".$host, json_encode($post), $publicHeader);
        $result = json_decode($result) ? json_decode($result, true) : [];
        $code = $result['Response']['SendStatusSet'][0]['Code'] ?? '';
        $code = $result['Response']['Error']['Code'] ?? $code;
        //dump($result);
        if($code==='ok' || $code==='Ok'){
            return [1, '发送成功'];
        }else{
            $msg = $result['Response']['SendStatusSet'][0]['Message'] ?? '发送失败';
            $msg = $result['Response']['Error']['Message'] ?? $msg;
            return [-1, $msg];
        }
    }

    private function getHeader($host, $post)
    {
        $publicHeader = [
            'X-TC-Action' => 'SendSms',
            'X-TC-Timestamp' => time(),
            'X-TC-Version' => '2021-01-11',
            'X-TC-Region' => 'ap-beijing',
            'Authorization' => ''
        ];
        $publicHeader['Authorization'] = $this->getAuthorization($host, $post);

        return $publicHeader;
    }

    private function getAuthorization($host, $post)
    {
        $timestamp = time();
        $service = "sms";
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