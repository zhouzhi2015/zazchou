<?php 
/**
 * http请求方式
 * @author zhouzhi@kuaiqiangche.com
 * @version 20160819 14:14
 */
namespace zazchou\src\Utils;

class Http{
	
	/**
	 * 微信 以post方式提交xml到对应的接口url
	 *
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	static public function postXmlCurl($xml, $url, $useCert = false, $second = 30, $sslcertPath='', $sslkeyPath=''){
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
	
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);//严格校验
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE, 'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $sslcertPath);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE, 'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY,  $sslkeyPath);
		}
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
		$data = curl_exec($ch);
		
// 		var_dump($data, $url);

		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else {
			$error = curl_errno($ch);
			echo "curl出错，错误码:{$error}"."<br/>";
			echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
			curl_close($ch);
			return false;
		}
	}
	
	/**
	 * 获取接口地址
	 * @return string
	 */
	static final public function getApiUrl() {
		return "https://pay.api.kqc.cc";
	}
	
	static public function post($api, $data, $timeout, $returnArray) {
		$url = self::getApiUrl() . $api;
		$httpResultStr = self::request($url, "post", $data, $timeout);
		$result = json_decode($httpResultStr, !$returnArray ? false : true);
		if (!$result) {
			throw new Exception(Config::UNEXPECTED_RESULT . $httpResultStr);
		}
		return $result;
	}
	
	/*
	 *  @param $type boolean
	 * 	默认true, 即: url?para=json串,处理即urlencode(json_encode($data)
	 *  设置false, 即: url?key=value&key1=value1,处理即http_build_query($data)
	 */
	static public function get($api, $data, $timeout, $returnArray, $type = true) {
		$url = self::getApiUrl() . $api;
		$httpResultStr = self::request($url, $type ? "get" : 'new_get', $data, $timeout);
		$result = json_decode($httpResultStr,!$returnArray ? false : true);
		if (!$result) {
			throw new Exception(Config::UNEXPECTED_RESULT . $httpResultStr);
		}
		return $result;
	}
	
	static public function put($api, $data, $timeout, $returnArray) {
		$url = self::getApiUrl() . $api;
		$httpResultStr = self::request($url, "put", $data, $timeout);
		$result = json_decode($httpResultStr,!$returnArray ? false : true);
		if (!$result) {
			throw new Exception(Config::UNEXPECTED_RESULT . $httpResultStr);
		}
		return $result;
	}
	
	static public function delete($api, $data, $timeout, $returnArray) {
		$url = self::getApiUrl() . $api;
		$httpResultStr = self::request($url, "delete", $data, $timeout);
		$result = json_decode($httpResultStr,!$returnArray ? false : true);
		if (!$result) {
			throw new Exception(Config::UNEXPECTED_RESULT . $httpResultStr);
		}
		return $result;
	}
	
	static final public function request($url, $method, array $data, $timeout) {
		try {
			$timeout = (isset($timeout) && is_int($timeout)) ? $timeout : 20;
			$ch = curl_init();
			/*支持SSL 不验证CA根验证*/
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			/*重定向跟随*/
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	
			//设置 CURLINFO_HEADER_OUT 选项之后 curl_getinfo 函数返回的数组将包含 cURL
			//请求的 header 信息。而要看到回应的 header 信息可以在 curl_setopt 中设置
			//CURLOPT_HEADER 选项为 true
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLINFO_HEADER_OUT, false);
	
			//fail the request if the HTTP code returned is equal to or larger than 400
			//curl_setopt($ch, CURLOPT_FAILONERROR, true);
			$header = array("Content-Type:application/json;charset=utf-8;", "Connection: keep-alive;");
			$methodIgnoredCase = strtolower($method);
			switch ($methodIgnoredCase) {
				case "post":
					curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); //POST数据
					curl_setopt($ch, CURLOPT_URL, $url);
					break;
				case "get":
					curl_setopt($ch, CURLOPT_URL, $url."?para=".urlencode(json_encode($data)));
					break;
				case "new_get":
					curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($data));
					break;
				case "put":
					curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); //POST数据
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
					curl_setopt($ch, CURLOPT_URL, $url);
					break;
				case "delete":
					curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($data));
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
					break;
				default:
					throw new Exception('不支持的HTTP方式');
					break;
			}
	
			$result = curl_exec($ch);
			if (curl_errno($ch) > 0) {
				throw new Exception(curl_error($ch));
			}
			curl_close($ch);
			return $result;
		} catch (Exception $e) {
			return "CURL EXCEPTION: ".$e->getMessage();
		}
	}
	
}

?>