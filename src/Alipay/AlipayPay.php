<?php
/**
 * 支付宝支付API
 * @author zhouzhi@kuaiqiangche.com
 * @version 20160825 16:29
 */

namespace zazChou\Alipay;

use Illuminate\Support\Facades\Redis;
use Log;
use zazChou\Utils\StringFun;
use zazChou\Utils\HttpFun;
use zazChou\Utils\XMLFun;

class AlipayPay{
	
	private $__gateway_new = 'https://mapi.alipay.com/gateway.do?';

	private $__https_verify_url = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';

	private $__http_verify_url = 'http://notify.alipay.com/trade/notify_query.do?';

	private $service;

	private $partner; //合作者身份ID

	private $_input_charset = 'utf-8'; //参数编码字符集

	private $sign_type; //签名方式 DSA、RSA、MD5三个值可选，必须大写。 = 'MD5'

	private $notify_url; //服务器异步通知页面路径

	private $return_url; //页面跳转同步通知页面路径

	private $out_trade_no; //商户网站唯一订单号

	private $payment_type = 1; // 	支付类型。仅支持：1（商品购买）。

	private $seller_id; //卖家支付宝用户号

	private $total_fee; //交易金额

	private $subject; //商品名称

	private $body; //商品描述

	private $show_url; // 商品展示网址	

	private $exter_invoke_ip;

	private $app_pay = 'Y'; //是否使用支付宝客户端支付

	private $key;

	private $transport;

	private $cacert;
	
	private $parameters = array();

	/**
	 * return message
	 */
	const  VALIDE_PAY_PARAM = '缺少微支付参数';
	
	
	public function __construct($alipayConfig){
		$this->partner = isset($alipayConfig['partner']) ? $alipayConfig['partner'] : '';
		$this->seller_id = isset($alipayConfig['seller_id']) ? $alipayConfig['seller_id'] : '';
		$this->key = isset($alipayConfig['key']) ? $alipayConfig['key'] : '';
		$this->cacert = isset($alipayConfig['cacert']) ? $alipayConfig['cacert'] : '';
		$this->service =  isset($alipayConfig['service']) ? $alipayConfig['service'] : '';
		$this->sign_type =  isset($alipayConfig['sign_type']) ? $alipayConfig['sign_type'] : '';
		$this->_input_charset = isset($alipayConfig['input_charset']) ? $alipayConfig['input_charset'] : 'utf-8';
		//设置基本参数
		$basicParams = array('partner', 'seller_id', 'cacert', 'key', 'service', 'sign_type', '_input_charset', 'payment_type',);
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
	 * 验证必填参数
	 * @param array|string $params 参数key
	 * @param array $data
	 * @return multitype:string
	 */
	static public function verifyNeedParams($params, $data){
		if(is_string($params)){
			if(!isset($data[$params]) || empty($data[$params])){
				return array('status' => 'F', 'message' => self::VALIDE_PAY_PARAM . $params);
			}
		}else if(is_array($params)){
			foreach ($params as $field) {
				if(!isset($data[$field]) || empty($data[$field])){
					return array('status' => 'F', 'message' => self::VALIDE_PAY_PARAM . $field);
				}
			}
		}
		return true;
	}
	
	/**
	 * wap支付跳转取得支付链接
	 */
	public function getPayLink(){	
		//检测必填参数
		$params = array('out_trade_no', 'subject', 'total_fee', 'return_url', 'notify_url', 'partner', 
				'seller_id', 'key', 'cacert', 'service','sign_type', 'payment_type', '_input_charset');
		$paramCheck = self::verifyNeedParams($params, $this->parameters);
		if(is_array($paramCheck) && ($paramCheck['status'] == 'F')){
			return $paramCheck;
		}
//		链接跳转
// 		$param = $this->buildRequestPara($this->parameters);
// 		$url = $this->__gateway_new.StringFun::createLinkstringUrlencode($param);
// 		return $url;
		//表单提交
		$param = $this->buildRequestForm($this->parameters, 'get', '确认');
		return $param;
	}
	
	/**
	 * 建立请求，以表单HTML形式构造（默认）
	 * @param $para_temp 请求参数数组
	 * @param $method 提交方式。两个值可选：post、get
	 * @param $button_name 确认按钮显示文字
	 * @return 提交表单HTML文本
	 */
	private function buildRequestForm($paraTemp, $method, $button_name) {
		//待请求参数数组
		$para = $this->buildRequestPara($paraTemp);
		
		$sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$this->__gateway_new."_input_charset=".trim(strtolower($this->_input_charset))."' method='".$method."'>";
		while (list ($key, $val) = each ($para)) {
			$sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/><br/>";
		}
		//submit按钮控件请不要含有name属性
		$sHtml = $sHtml."<input type='submit'  value='".$button_name."' style='display:none'></form>";
		$sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";

		return $sHtml;
	}	
	
	/**
	 * 生成要请求给支付宝的参数数组
	 * @param $para_temp 请求前的参数数组
	 * @return 要请求的参数数组
	 */
	private function buildRequestPara($paraTemp){
		//除去待签名参数数组中的空值和签名参数
		$paraFilter = StringFun::paraFilter($paraTemp);
		//对待签名参数数组排序
		$paraSort = StringFun::argSort($paraFilter);
		//生成签名结果
		$mysign = StringFun::buildRequestMysign($paraSort, $this->key, $this->sign_type);
		//签名结果与签名方式加入请求提交参数组中
		$paraSort['sign'] = $mysign;
		$paraSort['sign_type'] = strtoupper(trim($this->sign_type));
	
		return $paraSort;
	}
	
	
	
	//-----alipay回调----待使用-
	/**
	 * 验证消息是否是支付宝发出的合法消息
	 */
	public function verify(){
		// 判断请求是否为空
		if (empty($_POST) && empty($_GET)) {
			return false;
		}
		$data = $_POST ?: $_GET;
		// 生成签名结果
		$is_sign = $this->getSignVeryfy($data, $data['sign']);
		// 获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
		$response_txt = 'true';
		if (! empty($data['notify_id'])) {
			$response_txt = $this->getResponse($data['notify_id']);
		}
		// 验证
		// $response_txt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
		// isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
		if (preg_match('/true$/i', $response_txt) && $is_sign) {
			return true;
		} else {
			return false;
		}
	}
	
}