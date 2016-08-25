<?php 
/**
 * 微支付类
 */
namespace zazchou\src\Wechat;

use Illuminate\Support\Facades\Redis;
use Log;
use zazchou\src\Utils\String;
use zazchou\src\Utils\Http;
use zazchou\src\Utils\XML;

class WxPay{
	
	public $values;
	public $parameters;
	public $response;
	public $appid;			//微信公众号身份的唯一标识
	public $appsecret;
	public $mch_id;			//受理商ID，身份标识
	public $key;			//商户支付密钥Key
	public $curl_timeout;	//本例程通过curl使用HTTP POST方法，默认为30秒
	public $notify_url;		//异步通知url
	public $result;
	public $prepay_id;		//使用统一支付接口得到的预支付id
	public $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";			//统一下单
	
	public $sslcert_path;	//	SSLCERT_PATH,证书路径,注意应该填写绝对路径
	public $sslkey_path;	//	SSLKEY_PATH
	public $refund_url = "https://api.mch.weixin.qq.com/secapi/pay/refund"; 	//退款url
	
	/**
	 * return message
	 */
	const  VALIDE_WX_PARAM = '缺少微支付参数';
	/**
	 * Cache key
	 */
	const PRE_CACHE_KEY = 'MICRO:API:ORDER:PREPAREID:'; 
	
	/**
	 * 配置参数
	 * @param string $appid
	 * @param string $appsecret
	 * @param string $mch_id
	 * @param string $key
	 * @param string $notify_url
	 * @param string $sslcert_path
	 * @param string $sslkey_path
	 */
	public function __construct($appId, $appSecret, $mchId, $key, $sslcertPath='', $sslkeyPath=''){
		$this->appid = !empty($appId) ? $appId : '' ;
		$this->appsecret = !empty($appSecret) ? $appSecret : '' ;
		$this->mch_id = !empty($mchId) ? $mchId : '';
		$this->key = !empty($key) ? $key : '';
		$this->sslcert_path = !empty($sslcertPath) ? $sslcertPath : '';
		$this->sslkey_path = !empty($sslkeyPath) ? $sslkeyPath : '';
		$this->curl_timeout = 30;
		//设置基本参数
		$basicParams = array('appid', 'appsecret', 'mch_id', 'key', 'sslcert_path', 'sslkey_path');
		foreach($basicParams as $value){
			$this->setParameter($value, $this->$value);
		}
	}
	
	/**
	 * 设置支付参数
	 * @param unknown $parameter
	 * @param unknown $parameterValue
	 */
	function setParameter($parameter,$parameterValue){
		$this->parameters[$this->trimString($parameter)] = $this->trimString($parameterValue);
	}
	function trimString($value){
		$ret = null;
		if($value != null){
			$ret = $value;
			if(strlen($ret) == 0){
				$ret = null;
			}
		}
		return $ret;
	}
	
	/**
	 * 	作用：设置prepay_id
	 */
	function setPrepayId($prepayId){
		$this->prepay_id = $prepayId;
	}
	
	/**
	 * 验证必填参数
	 * @param array|string $params 参数key
	 * @param array $data
	 * @return multitype:string
	 */
	static public function verifyNeedParams($params, $data){
		if(is_string($params)){
			if(!isset($data[$params]) || empty($data[$params])){
				return array('status' => 'F', 'message' => self::VALIDE_WX_PARAM . $params);
			}
		}else if(is_array($params)){
			foreach ($params as $field) {
				if(!isset($data[$field]) || empty($data[$field])){
					return array('status' => 'F', 'message' => self::VALIDE_WX_PARAM . $field);
				}
			}
		}
		return array('stuatus' => 'S');
	}
	
	/**
	 * 获取prepay_id
	 */
	public function getPrepayId(){
		//检测必填参数
		$params = array('out_trade_no', 'body', 'total_fee', 'trade_type', 'notify_url', 'appid', 'appsecret', 'mch_id', 'key');
		$paramCheck = self::verifyNeedParams($params, $this->parameters);
		if(is_array($paramCheck['status']) && ($paramCheck['status'] == 'F')){
			return $paramCheck;
		}
		if(($this->parameters["trade_type"] == "JSAPI") && empty($this->parameters["openid"])){
			$res['status'] = 'F';
			$res['message'] = '统一支付接口中，缺少必填参数openid';
			return $res;
		}
		
		$this->postXml();
		$this->result = XML::xmlToArray($this->response);
		Log::info('Info: wx get prepayid result data is'.serialize($this->result));
		if(($this->result['return_code'] == 'SUCCESS') && ($this->result['result_code'] == 'SUCCESS') ){
			$prepay_id = $this->result["prepay_id"];
			return array('status' => 'S', 'message' => $prepay_id);
		}else{
			$message = $this->result['return_msg'];
			return array('status' => 'F', 'message' => $message, 'data' => !empty($this->result['err_code_des']) ? $this->result['err_code_des']:'');
		}
	}
	
	/**
	 * 	作用：post请求xml
	 */
	private function postXml(){
		$this->parameters["nonce_str"] = String::getNonceStr(32);
		$this->parameters["sign"] = String::makeSign($this->parameters, $this->key);
		$xml =  XML::arrayToXml($this->parameters);
		$this->response = HTTP::postXmlCurl($xml, $this->url, false, $this->curl_timeout);
		return $this->response;
	}
	
	/**
	 * 获取jsapi支付的参数
	 * @return json数据，可直接填入js函数作为参数
	 */
	private function getJsApiParameters(){
		$jsApiObj['appId'] = $this->appid;
		$timeStamp = time();
		$jsApiObj['timeStamp'] = "$timeStamp";
		$jsApiObj['nonceStr'] = String::getNonceStr();
		$jsApiObj['package'] = "prepay_id=".$this->prepay_id;
		$jsApiObj['signType'] = "MD5";
		$jsApiObj['paySign'] = String::makeSign($jsApiObj, $this->key);
		$jsApiParameters = json_encode($jsApiObj);
		return $jsApiParameters;
	}
	
	/**
	 * 微支付申请退款
	 * @return array $res
	 */
	public function refund(Request $request){
		//检测必填参数
		if(empty($this->parameters["out_trade_no"]) && ($this->parameters["transaction_id"])) {
			$res['status'] = 'F';
			$res['message'] = '退款申请接口中，out_trade_no、transaction_id 至少填一个！';
			return $res;
		}
		$params = array('out_refund_no', 'total_fee', 'refund_fee', 'appid', 'appsecret', 'mch_id', 'key', 'sslcert_path', 'sslkey_path');
		$paramCheck = self::verifyNeedParams($params, $this->parameters);
		if(is_array($paramCheck['status']) && ($paramCheck['status'] == 'F')){
			return $paramCheck;
		}
		$this->parameters['op_user_id'] = $this->mch_id;
		$this->parameters["nonce_str"] = String::getNonceStr(32);
		$this->parameters["sign"] = String::makeSign($this->parameters, $this->key);
		
		$xml = XML::arrayToXml($this->parameters);
		$resXml = Http::postXmlCurl($xml, $this->refund_url, true, $this->curl_timeout);
		Log::info('wxpay refund result is:\n'.serialize($resXml));
		if($resXml === false){
			$res['message'] = '退款失败 =。=!!'; $res['status'] = 'F';
			return $res;
		}
		$res = XML::xmlToArray($resXml);
		Log::info('Info: wxpay refund result is:\n'.serialize($res));
		if(($res['return_code'] == "SUCCESS") && ($res['result_code'] == "SUCCESS")){
			$res['message'] = "退款成功";
			$res['status'] = 'S';
		}else{
			$errCodeDes = isset($res['err_code_des']) ? $res['err_code_des'] : '';
			$res['message'] = $res['return_msg'].$errCodeDes;
			$res['status'] = 'F';
		}
		return $res;
	}

}
?>