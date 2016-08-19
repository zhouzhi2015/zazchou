<?php 
/**
 * 字符串处理函数
 * @author zhouzhi@kuaiqiangche.com
 * @version 20160818 16:56
 */
namespace zazChou\src\Utils;

class String {
	
	/**
	 * 过滤参数
	 * @param mixed $value
	 * @return Ambigous <NULL, unknown>
	 */
	public function trimString($value){
		$res = null;
		if (null != $value){
			$res = $value;
			if (strlen($res) == 0){
				$res = null;
			}
		}
		return $res;
	}
	
	/**
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public function getNonceStr($length = 32) {
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
	private function formatUrlParams($paraMap, $urlencode=false){
		$buff = '';
		ksort($paraMap);
		foreach ($paraMap as $k => $v){
			if(($k != "sign") && ($v != "") && !is_array($v)){
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
	 * @return string 签名
	 */
	public function makeSign($Parameters){
		//签名步骤一：按字典序排序参数
		ksort($Parameters);
		$String = $this->formatUrlParams($Parameters, false);
		//签名步骤二：在string后加入KEY
		$String = $String."&key=".WxPayConfig::KEY;
		//签名步骤三：MD5加密
		$String = md5($String);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($String);
	
		return $result;
	}
	
	
}
?>