<?php
namespace usualtool\Douyin;
/**
 * 抖音小程序专用
 */
class MiniPro{
    private $appline;
    private $applineNew;
    private $appid;
    private $secret;
    private $tokenlife=6900;
    private $ctokenlife=5400;
    /**
     * 构造函数
     * @param string $appid 小程序ID
     * @param string $secret 小程序APP Secret
     * @param bool $sandbox 是否使用沙盒环境
     */
    public function __construct($appid,$secret,$sandbox=false){
        if($sandbox):
            $this->appline="https://open-sandbox.douyin.com";
            $this->applineNew="https://open-sandbox.douyin.com";
        else:
            $this->appline="https://developer.toutiao.com";
            $this->applineNew="https://open.douyin.com";
        endif;
        $this->appid=$appid;
        $this->secret=$secret;
    }
    /**
     * 获取小程序 access_token
     * @param bool $refresh 传 true 跳过缓存强制重取
     * @return string 失败返回空串
     */
    public function GetToken($refresh=false){
        $cache=APP_ROOT."/log/douyin.minipro.".$this->appid.".token.json";
        if(!$refresh):
            $info=json_decode((string)@file_get_contents($cache),true);
            if(is_array($info) && !empty($info['access_token']) && isset($info['expires']) && time()<=$info['expires']):
                return $info['access_token'];
            endif;
        endif;
        $token=$this->GetNewToken();
        if($token===''):
            return '';
        endif;
        $this->MakeLogDir();
        @file_put_contents($cache,json_encode(array("access_token"=>$token,"expires"=>time()+$this->tokenlife)));
        return $token;
    }
    /**
     * 向抖音换取新的小程序 access_token
     * @return string 失败返回空串
     */
    public function GetNewToken(){
        $data=json_encode(array(
            "appid"=>$this->appid,
            "secret"=>$this->secret,
            "grant_type"=>"client_credential"
        ));
        $res=Http::PostData($this->appline."/api/apps/v2/token",$data,array("Content-Type: application/json"));
        if(is_array($res) && isset($res['err_no']) && (int)$res['err_no']===0 && !empty($res['data']['access_token'])):
            $this->tokenlife=isset($res['data']['expires_in']) ? max(60,(int)$res['data']['expires_in']-300) : 6900;
            return $res['data']['access_token'];
        endif;
        return '';
    }
    /**
     * 获取 client_token
     * @param bool $refresh 传 true 跳过缓存强制重取
     * @return string 失败返回空串
     */
    public function GetClientToken($refresh=false){
        $cache=APP_ROOT."/log/douyin.minipro.".$this->appid.".client_token.json";
        if(!$refresh):
            $info=json_decode((string)@file_get_contents($cache),true);
            if(is_array($info) && !empty($info['access_token']) && isset($info['expires']) && time()<=$info['expires']):
                return $info['access_token'];
            endif;
        endif;
        $token=$this->GetNewClientToken();
        if($token===''):
            return '';
        endif;
        $this->MakeLogDir();
        @file_put_contents($cache,json_encode(array("access_token"=>$token,"expires"=>time()+$this->ctokenlife)));
        return $token;
    }
    /**
     * 向抖音换取新的 client_token
     * @return string 失败返回空串
     */
    public function GetNewClientToken(){
        $data=json_encode(array(
            "client_key"=>$this->appid,
            "client_secret"=>$this->secret,
            "grant_type"=>"client_credentials"
        ));
        $res=Http::PostData($this->applineNew."/oauth/client_token/",$data,array("Content-Type: application/json"));
        if(is_array($res) && !empty($res['data']['access_token'])):
            $this->ctokenlife=isset($res['data']['expires_in']) ? max(60,(int)$res['data']['expires_in']-300) : 5400;
            return $res['data']['access_token'];
        endif;
        return '';
    }
    /**
     * 登录凭证校验
     * @param string $code 登录凭证
     * @param string $anonymous_code 匿名登录凭证
     * @return array err_no=0 时 data 含 session_key/openid/unionid
     */
    public function ChkLogin($code='',$anonymous_code=''){
        $data=json_encode(array(
            "appid"=>$this->appid,
            "secret"=>$this->secret,
            "code"=>$code,
            "anonymous_code"=>$anonymous_code
        ));
        return Http::PostData($this->appline."/api/apps/v2/jscode2session",$data,array("Content-Type: application/json"));
    }
    /**
     * 手机号码获取（新）
     * @param string $code 手机号码获取凭证
     * @return array err_no=0 时 data 含 phoneNumber/purePhoneNumber/countryCode
     */
    public function GetPhone($code){
        $data=json_encode(array(
            "access_token"=>$this->GetClientToken(),
            "code"=>$code
        ));
        return Http::PostData($this->applineNew."/api/apps/v1/get_phonenumber_info/",$data,array("Content-Type: application/json"));
    }
    /**
     * 手机号码获取（旧）
     * @param string $sessionKey ChkLogin 返回的 session_key
     * @param string $encryptedData 加密数据
     * @param string $iv 偏移量
     * @param string $data 返回解析数据（引用）
     * @return string 0 成功，-41003 失败
     */
    public function DecryptData($sessionKey,$encryptedData,$iv,&$data){
        $aesKey=base64_decode($sessionKey);
        $aesIV=base64_decode($iv);
        $aesCipher=base64_decode($encryptedData);
        $result=openssl_decrypt($aesCipher,"AES-128-CBC",$aesKey,1,$aesIV);
        $dataObj=json_decode($result);
        if($dataObj==NULL || empty($dataObj)){
            return "-41003";
        }
        if(isset($dataObj->watermark->appid) && $dataObj->watermark->appid!=$this->appid){
            return "-41003";
        }
        $data=$result;
        return 0;
    }
    /**
     * 生成小程序二维码
     * @param string $path 启动参数，格式 encode({path}?{query})，如 urlencode("pages/index/index?id=1")
     * @param int $width 宽度 280-1280，默认 430
     * @param string $appname 打开二维码的宿主：douyin/toutiao/toutiao_lite/douyin_lite/huoshan/all
     * @return array
     */
    public function QrCreate($path,$width=430,$appname="douyin"){
        $data=json_encode(array(
            "access_token"=>$this->GetClientToken(),
            "app_name"=>$appname,
            "path"=>$path,
            "width"=>(int)$width,
            "set_icon"=>false,
            "is_circle_code"=>false
        ));
        return Http::PostData($this->applineNew."/api/apps/v1/qrcode/create/",$data,array("Content-Type: application/json"));
    }
    /**
     * 文本内容安全检测
     * data[0].predicts[0].hit 为 true 表示文本包含违法违规内容
     * @param string $content 检测的文本内容
     * @return array
     */
    public function CheckMsg($content){
        $data=json_encode(array(
            "tasks"=>array(
                array("content"=>$content)
            )
        ));
        return Http::PostData($this->appline."/api/v2/tags/text/antidirt",$data,array("Content-Type: application/json","X-Token: ".$this->GetToken()));
    }
    /**
     * 图片内容安全检测
     * error=0 且 predicts 中无 hit=true 即通过
     * @param string $imagedata 图片 base64 数据（不含 data: 前缀）
     * @return array
     */
    public function CheckImage($imagedata){
        $data=json_encode(array(
            "app_id"=>$this->appid,
            "access_token"=>$this->GetToken(),
            "image_data"=>$imagedata
        ));
        return Http::PostData($this->appline."/api/apps/censor/image",$data,array("Content-Type: application/json"));
    }
    /**
     * 确保 token 缓存目录存在
     */
    private function MakeLogDir(){
        $dir=APP_ROOT."/log";
        if(!is_dir($dir)){
            @mkdir($dir,0777,true);
        }
    }
}