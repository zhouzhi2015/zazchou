<?php 
/**
 * 字符串处理函数
 * @author zhouzhi@kuaiqiangche.com
 * @version 20160818 16:56
 */
namespace zazChou\Utils;

class StringFun{

	//----------------- 	wxpay -S--------------
	/**
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	static public function getNonceStr($length = 32) {
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str = "";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return $str;
	}

	/**
	 * 作用：格式化参数格式化成url参数
	 * @param array $paraMap 非空参数值的参数
	 * @param boolean $urlencode 是否需要转义
	 * @return string 参数按照key=value的格式，按参数名ASCII字典序排序
	 */
	static public function formatUrlParams($paraMap, $urlencode=false){
		$buff = '';
		ksort($paraMap);
		foreach ($paraMap as $k => $v){
			if(($k != "sign") && ($v != "") && !is_array($v) && ($k != 'sign_type')){
				if($urlencode){
					$v = urlencode($v);
				}
				$buff .= $k . "=" . $v . "&";
			}
		}
		$requestParam='';
		if (strlen($buff) > 0){
			$requestParam = trim($buff, "&");
		}
		return $requestParam;
	}
	
	/**
	 * 作用：生成签名
	 * @param array $Parameters 数组
	 * @param string 商户支付密钥 $machKey
	 * @return string 签名
	 */
	static public function makeSign($Parameters, $machKey){
		//签名步骤一：按字典序排序参数
		ksort($Parameters);
// 		reset($Parameters);
		$String = self::formatUrlParams($Parameters, false);
		//签名步骤二：在string后加入KEY
		$String = $String."&key=".$machKey;
		//签名步骤三：MD5加密
		$String = md5($String);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($String);
	
		return $result;
	}
//----------------- 	wxpay -E--------------	
	
//----------------- 	alipay -S--------------
	/**
	 * 除去数组中的空值和签名参数
	 * @param $para 签名参数组
	 * return 去掉空值与签名参数后的新签名参数组
	 */
	static public function paraFilter($para){
		$paraFilter = array();
		while ((list ($key, $val) = each($para)) == true) {
			if (($key == 'sign') || ($key == 'sign_type') || ($val == '')) {
				continue;
			} else {
				$paraFilter[$key] = $para[$key];
			}
		}
		return $paraFilter;
	}
	
	/**
	 * 对数组排序
	 * @param $para 排序前的数组
	 * return 排序后的数组
	 */
	static public function argSort($para){
		ksort($para);
		reset($para);
		return $para;
	}
	
	/**
	 * 生成签名结果
	 * @param $para_sort 已排序要签名的数组
	 * @param $machKey  MD5加密密钥
	 * @param $signType 签名类型
	 * return 签名结果字符串
	 */
	static public function buildRequestMysign($paraSort, $machKey, $signType){
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = self::createLinkstring($paraSort);
	
		$mysign = '';
		switch (strtoupper(trim($signType))) {
			case 'MD5':
				$mysign = self::md5Sign($prestr, $machKey);
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
	static public function createLinkstring($para){
		$arg = '';
		while ((list ($key, $val) = each($para)) == true) {
			$arg .= $key . '=' . $val . '&';
		}
		//去掉最后一个&字符
		$arg = substr($arg, 0, count($arg) - 2);
	
		//如果存在转义字符，那么去掉转义
//		if (get_magic_quotes_gpc()) {
//			$arg = stripslashes($arg);
//		}
	
		return $arg;
	}
	
	/**
	 * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	static public function createLinkstringUrlencode($para){
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
	static public function md5Sign($prestr, $key){
		$prestr = $prestr . $key;
		return md5($prestr);
	}
	
	
	
	
	
	
// 	---------------------回调-----------------
	/**
	 * 验证消息是否是支付宝发出的合法消息
	 */
//	public function verify(){
//		// 判断请求是否为空
//		if (empty($_POST) && empty($_GET)) {
//			return false;
//		}
//		$data = $_POST ?: $_GET;
//		// 生成签名结果
//		$is_sign = $this->getSignVeryfy($data, $data['sign']);
//		// 获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
//		$response_txt = 'true';
//		if (! empty($data['notify_id'])) {
//			$response_txt = $this->getResponse($data['notify_id']);
//		}
//		// 验证
//		// $response_txt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
//		// isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
//		if (preg_match('/true$/i', $response_txt) && $is_sign) {
//			return true;
//		} else {
//			return false;
//		}
//	}
	
	/**
	 * 验证签名
	 * @param $prestr 需要签名的字符串
	 * @param $sign 签名结果
	 * @param $key 私钥
	 * return 签名结果
	 */
//	static public function md5Verify($prestr, $sign, $key){
//		$prestr = $prestr . $key;
//		$mysgin = md5($prestr);
//
//		if ($mysgin == $sign) {
//			return true;
//		} else {
//			return false;
//		}
//	}
	
	/**
	 * 获取返回时的签名验证结果
	 * @param $para_temp 通知返回来的参数数组
	 * @param $sign 返回的签名结果
	 * @return 签名验证结果
	 */
//	static public function getSignVeryfy($para_temp, $sign){
//		//除去待签名参数数组中的空值和签名参数
//		$para_filter = self::paraFilter($para_temp);
//
//		//对待签名参数数组排序
//		$para_sort = self::argSort($para_filter);
//
//		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
//		$prestr = self::createLinkstring($para_sort);
//
//		$is_sgin = false;
//		switch (strtoupper(trim(self::sign_type))) {
//			case 'MD5':
//				$is_sgin = self::md5Verify($prestr, $sign, self::key);
//				break;
//			default:
//				$is_sgin = false;
//		}
//
//		return $is_sgin;
//	}
	
	/**
	 * 获取远程服务器ATN结果,验证返回URL
	 * @param $notify_id 通知校验ID
	 * @return 服务器ATN结果
	 * 验证结果集：
	 * invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空
	 * true 返回正确信息
	 * false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
	 */
//	private function getResponse($notify_id){
//		$transport = strtolower(trim($this->transport));
//		$partner = trim($this->partner);
//		$veryfy_url = '';
//		if ($transport == 'https') {
//			$veryfy_url = $this->__https_verify_url;
//		} else {
//			$veryfy_url = $this->__http_verify_url;
//		}
//		$veryfy_url = $veryfy_url . 'partner=' . $partner . '&notify_id=' . $notify_id;
//		$response_txt = $this->getHttpResponseGET($veryfy_url, $this->cacert);
//
//		return $response_txt;
//	}
//
	/**
	 * 远程获取数据，GET模式
	 * 注意：
	 * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
	 * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
	 * @param $url 指定URL完整路径地址
	 * @param $cacert_url 指定当前工作目录绝对路径
	 * return 远程输出的数据
	 */
//	private function getHttpResponseGET($url, $cacert_url){
//		$curl = curl_init($url);
//		curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头
//		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 显示输出结果
//		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true); //SSL证书认证
//		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); //严格认证
//		curl_setopt($curl, CURLOPT_CAINFO, $cacert_url); //证书地址
//		$responseText = curl_exec($curl);
//		//var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
//		curl_close($curl);
//
//		return $responseText;
//	}
	
//----------------- 	alipay -E--------------
	
}
?>