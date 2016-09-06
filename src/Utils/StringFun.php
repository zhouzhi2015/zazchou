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
	 * @param $machKey  MD5加密密钥  / rsa加密私钥
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
			case 'RSA':
				$mysign = self::rsaSign($prestr, trim($machKey));
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
	static public function createLinkstringUrlencodeSign($para){
		$arg = '';
		while (((list ($key, $val) = each($para)) == true)) {
			if($key == 'sign'){
				$arg .= $key . '=' . urlencode($val) . '&';
			}else{
				$arg .= $key . '=' . $val . '&';
			}
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
	
	/**
	 * RSA验签
	 * @param $data 待签名数据
	 * @param $ali_public_key_path 支付宝的公钥文件路径
	 * @param $sign 要校对的的签名结果
	 * return 验证结果
	 */
	static public function rsaVerify($data, $public_key_path, $sign){
		$pubKey = file_get_contents($public_key_path);
		$res = openssl_get_publickey($pubKey);
		$result = (bool) openssl_verify($data, base64_decode($sign), $res);
		openssl_free_key($res);
		return $result;
	}
	
	/**
	 * RSA签名
	 * @param $data 待签名数据
	 * @param $private_key_path 商户私钥文件路径
	 * return 签名结果
	 */
	static public function rsaSign($data, $private_key_path){
		$priKey = file_get_contents($private_key_path);
		$res = openssl_get_privatekey($priKey);
		openssl_sign($data, $sign, $res);
		openssl_free_key($res);
		//base64编码
		$sign = base64_encode($sign);
		return $sign;
	}

// 	---------------------回调-----------------	
	/**
	 * 验证签名
	 * @param $prestr 需要签名的字符串
	 * @param $sign 签名结果
	 * @param $key 私钥
	 * return 签名结果
	 */
	static public function md5Verify($prestr, $sign, $key){
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
	 * @param $signType 签名类型 MD5，RSA，DSA
	 * @param $mchKey MD5密钥/RSA公有密钥
	 * @return 签名验证结果
	 */
	static public function getSignVeryfy($paraTemp, $sign, $signType, $mchKey){
		//除去待签名参数数组中的空值和签名参数
		$paraFilter = self::paraFilter($paraTemp);
		//对待签名参数数组排序
		$paraSort = self::argSort($paraFilter);
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = self::createLinkstring($paraSort);

		$isSgin = false;
		switch (strtoupper(trim($signType))) {
			case 'MD5':
				$isSgin = self::md5Verify($prestr, $sign, $mchKey);
				break;
			case "RSA" :
				$isSgin = self::rsaVerify($prestr, trim($mchKey), $sign);
				break;
			default:
				$isSgin = false;
		}
		return $isSgin;
	}
	
//----------------- 	alipay -E--------------
	
}
?>