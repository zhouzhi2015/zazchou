<?php
/**
 * 支付宝支付API
 * @author zhouzhi@kuaiqiangche.com
 * @version 20160825 16:29
 */

namespace zazChou\Alipay;

use Illuminate\Support\Facades\Redis;
use Log;
use zazChou\Utils\String;
use zazChou\Utils\Http;
use zazChou\Utils\XML;

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
		$this->service =  isset($alipayConfig['wap_service']) ? $alipayConfig['service'] : '';
		$this->_input_charset = isset($alipayConfig['input_charset']) ? $alipayConfig['input_charset'] : 'utf-8';
		//设置基本参数
		$basicParams = array('partner', 'seller_id', 'cacert', 'key', 'service', 'payment_type', '_input_charset');
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
		return array('stuatus' => 'S');
	}
	
	/**
	 * wap支付跳转取得支付链接
	 */
	public function getPayLink(){	
		//检测必填参数
		$params = array('out_trade_no', 'subject', 'total_fee', 'return_url', 'notify_url', 'partner', 
				'seller_id', 'key', 'cacert', 'service', 'payment_type', '_input_charset');
		$paramCheck = self::verifyNeedParams($params, $this->parameters);
		if(is_array($paramCheck['status']) && ($paramCheck['status'] == 'F')){
			return $paramCheck;
		}
		
// 		$param = $this->buildRequestPara($this->parameters);
// 		return $this->__gateway_new . $this->createLinkstringUrlencode($param);

		$param = $this->buildRequestForm($this->parameters);
		return $param;
	}
	
	/**
	 * 建立请求，以表单HTML形式构造（默认）
	 * @param $para_temp 请求参数数组
	 * @param $method 提交方式。两个值可选：post、get
	 * @param $button_name 确认按钮显示文字
	 * @return 提交表单HTML文本
	 */
	function buildRequestForm($paraTemp, $method, $button_name) {
		//待请求参数数组
		$para = $this->buildRequestPara($paraTemp);
	
		$sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$this->alipay_gateway_new."_input_charset=".trim(strtolower($this->alipay_config['input_charset']))."' method='".$method."'>";
		while (list ($key, $val) = each ($para)) {
			$sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
		}
	
		//submit按钮控件请不要含有name属性
		$sHtml = $sHtml."<input type='submit'  value='".$button_name."' style='display:none;'></form>";
	
		$sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
	
		return $sHtml;
	}
	
// 	/**
// 	 * 生成要请求给支付宝的参数数组
// 	 * @param $para_temp 请求前的参数数组
// 	 * @return 要请求的参数数组
// 	 */
// 	private function buildRequestPara($paraTemp){
// 		$mysign = String::makeSign($paraTemp, $this->key);
// 		$this->parameters['sign'] = $mysign;
// 		$this->parameters['sign_type'] = strtoupper(trim($this->sign_type));
// // 		$para_sort['sign'] = $mysign;
// // 		$para_sort['sign_type'] = strtoupper(trim($this->sign_type));
	
// 		return $this->parameters;
// 	}
	
	
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
	
	/**
	 * 生成要请求给支付宝的参数数组
	 * @param $para_temp 请求前的参数数组
	 * @return 要请求的参数数组
	 */
	private function buildRequestPara($paraTemp){
		//除去待签名参数数组中的空值和签名参数
		$paraFilter = $this->paraFilter($paraTemp);
		//对待签名参数数组排序
		$paraSort = $this->argSort($paraFilter);
		//生成签名结果
		$mysign = $this->buildRequestMysign($paraSort);
	
		//签名结果与签名方式加入请求提交参数组中
		$paraSort['sign'] = $mysign;
		$paraSort['sign_type'] = strtoupper(trim($this->sign_type));

		return $paraSort;
	}

	/**
	 * 除去数组中的空值和签名参数
	 * @param $para 签名参数组
	 * return 去掉空值与签名参数后的新签名参数组
	 */
	private function paraFilter($para){
		$para_filter = array();
		while ((list ($key, $val) = each($para)) == true) {
			if ($key == 'sign' || $key == 'sign_type' || $val == '') {
				continue;
			} else {
				$para_filter[$key] = $para[$key];
			}
		}
		return $para_filter;
	}

	/**
	 * 对数组排序
	 * @param $para 排序前的数组
	 * return 排序后的数组
	 */
	private function argSort($para){
		ksort($para);
		reset($para);
		return $para;
	}

	/**
	 * 生成签名结果
	 * @param $para_sort 已排序要签名的数组
	 * return 签名结果字符串
	 */
	private function buildRequestMysign($para_sort){
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = $this->createLinkstring($para_sort);

		$mysign = '';
		switch (strtoupper(trim($this->sign_type))) {
			case 'MD5':
				$mysign = $this->md5Sign($prestr, $this->key);
				break;
			default:
				$mysign = '';
		}

		return $mysign;
	}

	/**
	 * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	private function createLinkstring($para){
		$arg = '';
		while ((list ($key, $val) = each($para)) == true) {
			$arg .= $key . '=' . $val . '&';
		}
		//去掉最后一个&字符
		$arg = substr($arg, 0, count($arg) - 2);

		//如果存在转义字符，那么去掉转义
		if (get_magic_quotes_gpc()) {
			$arg = stripslashes($arg);
		}

		return $arg;
	}

	/**
	 * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	private function createLinkstringUrlencode($para)
	{
		$arg = '';
		while ((list ($key, $val) = each($para)) == true) {
			$arg .= $key . '=' . urlencode($val) . '&';
		}
		//去掉最后一个&字符
		$arg = substr($arg, 0, count($arg) - 2);

		//如果存在转义字符，那么去掉转义
		if (get_magic_quotes_gpc()) {
			$arg = stripslashes($arg);
		}

		return $arg;
	}

	/**
	 * 签名字符串
	 * @param $prestr 需要签名的字符串
	 * @param $key 私钥
	 * return 签名结果
	 */
	private function md5Sign($prestr, $key)
	{
		$prestr = $prestr . $key;
		return md5($prestr);
	}

	/**
	 * 验证签名
	 * @param $prestr 需要签名的字符串
	 * @param $sign 签名结果
	 * @param $key 私钥
	 * return 签名结果
	 */
	private function md5Verify($prestr, $sign, $key)
	{
		$prestr = $prestr . $key;
		$mysgin = md5($prestr);

		if ($mysgin == $sign) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 获取返回时的签名验证结果
	 * @param $para_temp 通知返回来的参数数组
	 * @param $sign 返回的签名结果
	 * @return 签名验证结果
	 */
	private function getSignVeryfy($para_temp, $sign)
	{
		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($para_temp);

		//对待签名参数数组排序
		$para_sort = $this->argSort($para_filter);

		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = $this->createLinkstring($para_sort);

		$is_sgin = false;
		switch (strtoupper(trim($this->sign_type))) {
			case 'MD5':
				$is_sgin = $this->md5Verify($prestr, $sign, $this->key);
				break;
			default:
				$is_sgin = false;
		}

		return $is_sgin;
	}

	/**
	 * 获取远程服务器ATN结果,验证返回URL
	 * @param $notify_id 通知校验ID
	 * @return 服务器ATN结果
	 * 验证结果集：
	 * invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空
	 * true 返回正确信息
	 * false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
	 */
	private function getResponse($notify_id)
	{
		$transport = strtolower(trim($this->transport));
		$partner = trim($this->partner);
		$veryfy_url = '';
		if ($transport == 'https') {
			$veryfy_url = $this->__https_verify_url;
		} else {
			$veryfy_url = $this->__http_verify_url;
		}
		$veryfy_url = $veryfy_url . 'partner=' . $partner . '&notify_id=' . $notify_id;
		$response_txt = $this->getHttpResponseGET($veryfy_url, $this->cacert);

		return $response_txt;
	}

	/**
	 * 远程获取数据，GET模式
	 * 注意：
	 * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
	 * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
	 * @param $url 指定URL完整路径地址
	 * @param $cacert_url 指定当前工作目录绝对路径
	 * return 远程输出的数据
	 */
	private function getHttpResponseGET($url, $cacert_url)
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 显示输出结果
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true); //SSL证书认证
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); //严格认证
		curl_setopt($curl, CURLOPT_CAINFO, $cacert_url); //证书地址
		$responseText = curl_exec($curl);
		//var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
		curl_close($curl);

		return $responseText;
	}
	
}