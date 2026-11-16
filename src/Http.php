<?php
namespace usualtool\Douyin;
class Http{
    /**
     * GET 请求数据
     * @param string $url 完整的请求地址
     * @param array $headers 附加请求头，如 array("access-token: xxx")
     * @return array JSON 解析后的数组
     */
    public static function GetData($url,$headers=array()){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if(!empty($headers)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $output = curl_exec($ch);
        if(PHP_VERSION_ID < 80000) curl_close($ch);
        return json_decode($output,true);
    }
    /**
     * POST 请求数据
     * 响应不是 JSON 时（如二进制图片），转成 base64 的 data URI 字符串返回
     * @param string $url 完整的请求地址
     * @param string $data 请求体，JSON 字符串
     * @param array $headers 附加请求头，如 array("Content-Type: application/json","X-Token: xxx")
     * @return array|string JSON 数组或 data:image/png;base64,... 字符串
     */
    public static function PostData($url,$data='',$headers=array()){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        if(!empty($data)){
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if(!empty($headers)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        if(PHP_VERSION_ID < 80000) curl_close($ch);
        if(is_null(json_decode($output))){
            return "data:image/png;base64,".base64_encode($output);
        }else{
            return json_decode($output,true);
        }
    }
}