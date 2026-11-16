<?php
namespace usualtool\Douyin;
/**
 * 抖音开放平台
 */
class Douyin{
    private $client_key;
    private $client_secret;
    private $api="https://open.douyin.com";
    /**
     * 构造函数
     * @param string $client_key 应用的 client_key
     * @param string $client_secret 应用的 client_secret
     */
    public function __construct($client_key, $client_secret) {
        $this->client_key = $client_key;
        $this->client_secret = $client_secret;
    }
    /**
     * 获取应用级 access_token（client_token，带本地文件缓存）
     * 官方有效期 7200 秒，这里按 5400 秒缓存，提前过期以免边界失效
     * @return string 失败返回空串
     */
    public function GetToken() {
        $file=APP_ROOT."/log/douyin.token.json";
        $info=json_decode((string)@file_get_contents($file),true);
        if(is_array($info) && !empty($info['access_token']) && isset($info['expires']) && time()<$info['expires']):
            return $info['access_token'];
        endif;
        $token=$this->GetNewToken();
        if($token===''):
            return '';
        endif;
        $dir=APP_ROOT."/log";
        if(!is_dir($dir)):
            @mkdir($dir,0777,true);
        endif;
        @file_put_contents($file,json_encode(array("access_token"=>$token,"expires"=>time()+5400)));
        return $token;
    }
    /**
     * 向抖音换取新的应用级 access_token
     * @return string 失败返回空串
     */
    public function GetNewToken(){
        $url=$this->api."/oauth/access_token?client_key=".$this->client_key
            ."&client_secret=".$this->client_secret
            ."&grant_type=client_credential";
        $res=Http::GetData($url);
        return (is_array($res) && !empty($res['access_token'])) ? $res['access_token'] : '';
    }
    /**
     * 生成网页授权URL
     * @param string $redirect_uri 回调地址，需与开放平台控制台配置一致
     * @param string $scope 授权范围，多个用逗号分隔：user_info、video.list 等
     * @param string $state 自定义状态值，回调时原样带回，用于防 CSRF
     * @return string 授权 URL
     */
    public function GetAuthorizeUrl($redirect_uri,$scope="user_info,video.list",$state=""){
        return $this->api."/platform/oauth/connect/?client_key=".$this->client_key
            ."&response_type=code&scope=".rawurlencode($scope)
            ."&redirect_uri=".rawurlencode($redirect_uri)
            ."&state=".rawurlencode($state);
    }
    /**
     * 用网页授权 code 换取用户 access_token
     * @param string $code 授权回调带回的 code
     * @return array
     */
    public function GetAccessTokenByCode($code){
        $url=$this->api."/oauth/access_token?client_key=".$this->client_key
            ."&client_secret=".$this->client_secret
            ."&code=".rawurlencode($code)
            ."&grant_type=authorization_code";
        return Http::GetData($url);
    }
    /**
     * 刷新用户 access_token
     * @param string $refresh_token 换取用户 token 时一并返回的 refresh_token
     * @return array
     */
    public function RefreshToken($refresh_token){
        $data=http_build_query(array(
            "client_key"=>$this->client_key,
            "refresh_token"=>$refresh_token,
            "grant_type"=>"refresh_token"
        ));
        return Http::PostData($this->api."/oauth/refresh_token/",$data,array("Content-Type: application/x-www-form-urlencoded"));
    }
    /**
     * 获取授权用户信息
     * @param string $access_token 用户 access_token，放在 access-token 请求头
     * @param string $open_id 用户 open_id
     * @return array data 内含 nickname、avatar、union_id 等
     */
    public function GetUserInfo($access_token,$open_id){
        $url=$this->api."/oauth/userinfo/?open_id=".rawurlencode($open_id);
        return Http::GetData($url,array("access-token: ".$access_token));
    }
    /**
     * 查询授权用户的视频列表
     * @param string $access_token 用户 access_token
     * @param string $open_id 用户 open_id
     * @param int $cursor 分页游标，首次传 0，后续取返回的 data.cursor
     * @param int $count 每页条数，默认 10
     * @return array data.list 为视频列表，data.has_more 表示是否还有下一页
     */
    public function GetVideoList($access_token,$open_id,$cursor=0,$count=10){
        $url=$this->api."/api/douyin/v1/video/video_list/?open_id=".rawurlencode($open_id)
            ."&cursor=".(int)$cursor."&count=".(int)$count;
        return Http::GetData($url,array("access-token: ".$access_token));
    }
    /**
     * 查询视频统计数据
     * @param string $access_token 用户 access_token
     * @param string $open_id 用户 open_id
     * @param string $item_ids 视频 item_id，多个用英文逗号分隔
     * @return array data.list 内含各视频的播放、点赞等统计
     */
    public function GetVideoData($access_token,$open_id,$item_ids){
        $url=$this->api."/api/douyin/v1/video/video_data/?open_id=".rawurlencode($open_id)
            ."&item_ids=".rawurlencode($item_ids);
        return Http::GetData($url,array("access-token: ".$access_token));
    }
    /**
     * 初始化视频上传
     * @param string $access_token 用户 access_token
     * @param string $open_id 用户 open_id
     * @param string $filename 文件名，需带扩展名如 video.mp4
     * @param int $filesize 文件字节数
     * @return array data 内含 upload_id、video_id、upload_url
     */
    public function InitUpload($access_token,$open_id,$filename,$filesize){
        $data=json_encode(array(
            "open_id"=>$open_id,
            "filename"=>$filename,
            "file_size"=>(int)$filesize
        ));
        return Http::PostData($this->api."/api/douyin/v1/video/init_upload/",$data,array("Content-Type: application/json","access-token: ".$access_token));
    }
    /**
     * 发布视频
     * @param string $access_token 用户 access_token
     * @param string $open_id 用户 open_id
     * @param string $video_id 上传后得到的 video_id
     * @param string $text 视频标题文案
     * @return array data.item_id 为发布后的作品 ID
     */
    public function CreateVideo($access_token,$open_id,$video_id,$text=""){
        $data=json_encode(array(
            "open_id"=>$open_id,
            "video_id"=>$video_id,
            "text"=>$text
        ));
        return Http::PostData($this->api."/api/douyin/v1/video/create_video/",$data,array("Content-Type: application/json","access-token: ".$access_token));
    }
}