<?php 
/**
 * 字符串处理函数
 * @author zhouzhi@kuaiqiangche.com
 * @version 20160818 16:56
 */
namespace zazChou\Utils;

class String {
	
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
	 * return 签名结果字符串
	 */
	static public function buildRequestMysign($paraSort, $machKey){
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = $this->createLinkstring($paraSort);
	
		$mysign = '';
		switch (strtoupper(trim($this->sign_type))) {
			case 'MD5':
				$mysign = $this->md5Sign($prestr, $machKey);
				break;
			default:
				$mysign = '';
		}
	
		return $mysign;
	}
	
	
}
?>