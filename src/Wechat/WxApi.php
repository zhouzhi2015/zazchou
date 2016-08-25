<?php 

namespace zazchou\src\Wechat;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redis;
use Log;

class WxApi{

	/**
	 * AppID
	 * @var string
	 */
	private   $appid;
// 	= 'wxb1130e20fc134b29'
// 	public $appid = 'wx3b19cb4296356c07';
	
	/**
	 * AppSecret
	 * @var string
	 */
	private  $appsecret;
// 	= 'f0148a8cc40d07f63101b83e6767fd64'
// 	public $appsecret = '738d4a57755da9552937afdf94e64408';
	
	private $access_token;
	
	/**
	 * Cache key
	 */
	const CACHE_KEY = 'CXD:API:ACCESSTOKENMODEL:';
	
	/**
	 * Cache reduce 180s
	 */
	const CACHE_REDUCE = 180;
	
	/**
	 *
	 * curl超时设置
	 * @var int
	 */
	public $_timeout = 5;
	
	/**
	 * 构造函数
	 * @param unknown $appid
	 * @param unknown $appsecret
	 * @param unknown $mchid
	 * @param unknown $key
	 * @param unknown $notify_url
	 * @param string $sslcert_path
	 * @param string $sslkey_path
	 */
	public function __construct(){
		if($_SERVER['SERVER_NAME'] == 'api.cxd.kuaiqiangche.com'){
			$wxpayjsapi = config('wxpayjsapi');
		}else{
			$wxpayjsapi = config('wxconfig');
		}
		$this->appid = isset($wxpayjsapi['appid']) ? $wxpayjsapi['appid'] : '' ;
		$this->appsecret = isset($wxpayjsapi['secret']) ? $wxpayjsapi['secret'] : '';
	}
	
	/**
	 * 验证支付基础参数
	 * @return array 错误信息
	 */
	private function checkWxpayConfig(){
		if (empty($this->appid)){
			$res['status'] = 'F';
			$res['message'] = '缺少公众号APPID';
			return $res;
		}
		if (empty($this->appsecret)){
			$res['status'] = 'F';
			$res['message'] = 'API缺少公众帐号secret';
			return $res;
		}
	}
	
	/**
	 * 获取access_token
	 * @param string $appType 项目简称首字母
	 * 
	 * @return string access_token
	 * 
	 * https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=APPID&secret=APPSECRET
	 */
	public function getAccessToken(){

		$checkResult = $this->checkWxpayConfig();
		if($checkResult){
			$res['status'] = 'F';
			$res['message'] = $checkResult['message'];
			return $res;
		}
		
		$cacheKey = self::CACHE_KEY.$this->appid;
		$accessTokenInfo = Redis::get($cacheKey);
		$accessTokenInfo = unserialize($accessTokenInfo);
		$accessToken = isset($accessTokenInfo['access_token']) ? $accessTokenInfo['access_token'] : '';
		$accessTokenExpire = isset($accessTokenInfo['access_token_expire']) ? $accessTokenInfo['access_token_expire'] : '';
		$accessToken='';
		if (empty($accessToken) || ($accessTokenExpire <= time())){
			
// 			$param = ['grant_type' => 'client_credential', 'appid' => $this->appid, 'secret' => $this->appsecret];
// 			$url = $this->api_host . $this->_address . '?' . http_build_query($param);

			$url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->appsecret;
			$response = $this->get($url);
			if(!empty($response['errcode'])){
				$status = 'F';
				$message = $response['errcode'].$response['errmsg'];
			}else{
				$status = 'S';
				$message = $response['access_token'];
				$accessToken = $response['access_token'];
				$this->access_token = $response['access_token'];
				$accessTokenCache = $response['expires_in'] - self::CACHE_REDUCE;
				$accessTokenExpire = time() + $accessTokenCache;
				$accessTokenInfo = ['access_token' => $accessToken, 'access_token_expire' => $accessTokenExpire];
				$value = serialize($accessTokenInfo);
				Redis::setex($cacheKey, $accessTokenCache, $value); 
			}
		}else{
// 			echo 'not expire';
			$status = 'S';
			$message = $accessToken;
		}
		
		return array('status' => $status, 'message' => $message);
	}
	
	
	/**
	 *
	 * 网页授权接口微信服务器返回的数据，返回样例如下
	 * {
	 *  "access_token":"ACCESS_TOKEN",
	 *  "expires_in":7200,
	 *  "refresh_token":"REFRESH_TOKEN",
	 *  "openid":"OPENID",
	 *  "scope":"SCOPE",
	 *  "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
	 * }
	 * 其中access_token可用于获取共享收货地址
	 * openid是微信支付jsapi支付接口必须的参数
	 * @var array
	 */
	
	/**
	 *
	 * 通过跳转获取用户的openid，跳转流程如下：
	 * 1、设置自己需要调回的url及其其他参数，跳转到微信服务器https://open.weixin.qq.com/connect/oauth2/authorize
	 * 2、微信服务处理完成之后会跳转回用户redirect_uri地址，此时会带上一些参数，如：code
	 *
	 * @return 用户的openid
	 */
	public function getOpenid($code=''){
			if(empty($code)){
				return array('status' => 'F', 'message' => '缺少参数微code');
			}
			$url = $this->__CreateOauthUrlForOpenid($code);
			$response = $this->get($url);
			
			if(isset($response['errcode'])){
				return array('status' => 'F', 'message' => $response['errcode'].$response['errmsg']);
			}else{
				$openid = $response['openid'];
				return array('status' => 'S', 'message' => $openid);
			}
	}
	
	/**
	 * 通过openid获取用户基本信息
	 * @param string $openid
	 * @return multitype:string |multitype:string unknown
	 */
	public function getFansInfo($openid){
		if(empty($openid)){
			return array('status' => 'F', 'message' => '缺少参数微$openid');
		}
		$this->getAccessToken();
		
		$openidUrl = $this->_CreateOpenidUrlForUserInfo($openid, $this->access_token);
		$openidArr = $this->get($openidUrl);
		if(isset($response['errcode'])){
			return array('status' => 'F', 'message' => $response['errcode'].','.$response['errmsg']);
		}else{
			$openid = $response['openid'];
			return array('status' => 'S', 'message' => $openid);
		}
// 		$result = str_replace("NN=", '', file_get_contents($url));			//消除8进制对json_decode的影响，（尚未解决）
	}
	
	/**
	 * 备用获取code
	 */
	public function getOpenidBak($code=''){
// 			$sourceUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
			$sourceUrl = 'http://m.cxd.kuaiqiangche.cc/s';
			$baseUrl = urlencode($sourceUrl);
			$url = $this->__CreateOauthUrlForCode($baseUrl, "snsapi_info");
			Header("Location: $url");
			exit();
	}
	
	
	/**
	 * 通过OAuth 2.0工具获取用户基本信息
	 * @param string $code
	 * @return mixed错误信息|用户基础信息
	 */
	public function getOauthFansInfo($code){
		if(empty($code)){
			return array('status' => 'F', 'message' => '缺少参数微code');
		}
		//获取openid
		$accessTokenUrl = $this->__CreateOauthUrlForOpenid($code);
		$accessTokenArr = $this->get($accessTokenUrl);
		Log::info('accessTokenArr is \n'.$code.'\n'.serialize($accessTokenArr));
		
		if(empty($accessTokenArr)){
			return array('status' => 'F', 'message' => '用户openid获取失败');
		}
		if(isset($accessTokenArr['errcode']) && !empty($accessTokenArr['errcode'])){
			return array('status' => 'F', 'message' => $accessTokenArr['errcode'].','.$accessTokenArr['errmsg']);
		}
		//获取基础信息
		$openid = $accessTokenArr['openid'];
		$outhAccessToken = $accessTokenArr['access_token'];
		if(empty($openid) || empty($outhAccessToken)){
			return array('status' => 'F', 'message' => $outhAccessToken['errcode'].'参数错误');
		}
		$userInfoUrl = $this->_CreateOuthUrlForUserInfo($openid, $outhAccessToken);
		$userInfo = $this->get($userInfoUrl);
		Log::info('userInfo is \n'.serialize($userInfo));
		if(empty($userInfo)){
			return array('status' => 'F', 'message' => '用户信息获取失败');
		}
		if(isset($userInfo['errcode']) && !empty($userInfo['errcode'])){
			return array('status' => 'F', 'message' => $userInfo['errcode'].':'.$userInfo['errmsg']);
		}else{
			return array('status' => 'S', 'message' => $userInfo);
		}
	}
	
	
	/**
	 *
	 * 拼接签名字符串
	 * @param array $urlObj
	 * 
	 * @return 返回已经拼接好的字符串
	 */
	private function ToUrlParams($urlObj){
		$buff = "";
		foreach ($urlObj as $k => $v){
			if($k != "sign"){
				$buff .= $k . "=" . $v . "&";
			}
		}
		$buff = trim($buff, "&");
		return $buff;
	}
	
	/**
	 *
	 * 构造获取code的url连接
	 * @param string $redirectUrl 微信服务器回跳的url，需要url编码
	 *
	 * @return 返回构造好的url
	 */
	private function __CreateOauthUrlForCode($redirectUrl, $snsapiBase = "snsapi_base"){
		$urlObj["appid"] = $this->appid;
		$urlObj["redirect_uri"] = "$redirectUrl";
		$urlObj["response_type"] = "code";
		$urlObj["scope"] = "$snsapiBase";
		$urlObj["state"] = "STATE"."#wechat_redirect";
		$bizString = $this->ToUrlParams($urlObj);
		return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
	}
	
	/**
	 *
	 * 构造获取open和access_toke的url地址
	 * @param string $code，微信跳转带回的code
	 *
	 * @return 请求的url
	 */
	private function __CreateOauthUrlForOpenid($code){
		$urlObj["appid"] = $this->appid;
		$urlObj["secret"] = $this->appsecret;
		$urlObj["code"] = $code;
		$urlObj["grant_type"] = "authorization_code";
		$bizString = $this->ToUrlParams($urlObj);
		return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
	}
	
	/**
	 * 构造获取用户基础信息的url地址
	 * @param string $openid
	 * @param string $outhAccessToken
	 * 
	 * @return string 请求的url
	 */
	private function _CreateOuthUrlForUserInfo($openid, $outhAccessToken){
		$urlObj["access_token"] = $outhAccessToken;
		$urlObj["openid"] = $openid;
		$bizString = $this->ToUrlParams($urlObj);
		return "https://api.weixin.qq.com/sns/userinfo?".$bizString;
	}
	
	
	/**
	 * 构造获取用户基础信息的url地址
	 * @param string $openid
	 * @param string $outhAccessToken
	 *
	 * @return string 请求的url
	 */
	private function _CreateOpenidUrlForUserInfo($openid, $accessToken){
		$urlObj["access_token"] = $accessToken;
		$urlObj["openid"] = $openid;
		$bizString = $this->ToUrlParams($urlObj);
		return  "https://api.weixin.qq.com/cgi-bin/user/info?".$bizString."&lang=zh_CN";
	}
	
	/**
	 *
	 * 执行curl前的预处理
	 * @access public
	 * @param string $url
	 * @param string $method
	 * @param string $data
	 * @param mixed $file
	 * @param $returnFileType boolean
	 * @return array
	 */
	public function exec($url, $method, $data, $file=null, $returnFileType=false){
		$jsonStr = $this->curl($url, $method, $data, $file, $returnFileType);
		if(!is_array($jsonStr) && !is_null(json_decode($jsonStr))){
			return json_decode($jsonStr, true);
		}else{
			return $jsonStr;
		}
	}
	
	/**
	 * 执行curl请求
	 * @access public
	 * @param string $url
	 * @param string $method
	 * @param mixed $data
	 * @param mixed $file
	 * @return array
	 */
	protected function curl($url, $method, $data=null, $file=null, $returnFileType=false){
		$ch = curl_init();
		//设置选项，包括URL
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT,$this->_timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_timeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if(strtolower(parse_url($url,PHP_URL_SCHEME)) == "https"){
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}
		if("POST" == $method){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}elseif ("UPLOAD" == $method){
			if(version_compare(phpversion(), '5.4.0') >= 0){
				$fileds = array('media' => new CURLFile($file));
			}else{
				$fileds = array('media'=>'@'.$file);
			}
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fileds);
		}
		//执行并获取HTML文档内容
		$output = curl_exec($ch);
	
		if($returnFileType){
			$curlInfo = curl_getinfo($ch);
			curl_close($ch);
			return array('type' => $curlInfo['content_type'], 'data' => $output);
		}
		//释放curl句柄
		curl_close($ch);
		return $output;
	}
	
	/**
	 *
	 * GET请求
	 * @access public
	 * @param string $url
	 * @param mixed $data
	 * @param $returnFileType boolean
	 * @return array
	 */
	public function get($url, $data=null, $returnFileType=false){
		return $this->exec($url, "GET", $data, null, $returnFileType);
	}
	
	/**
	 *
	 * POST请求
	 * @access public
	 * @param string $url
	 * @param string $data
	 * @return array
	 */
	public function post($url, $data){
		return $this->exec($url, "POST", $data);
	}
	
	/**
	 *
	 * 上传文件
	 * @access public
	 * @param string $url
	 * @param mixed $data
	 * @param string $file
	 * @return array
	 */
	public function uploadFile($url, $data, $file){
		return $this->exec($url, "UPLOAD", $data, $file);
	}
	
}

?>