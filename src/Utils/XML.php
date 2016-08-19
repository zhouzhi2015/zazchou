<?php 
/**
 * XML处理相关函数
 * @author zhouzhi@kuaiqiangche.com
 * @version 20160818 17:15
 */
namespace  zazChou\src\Utils;

class XML{
	
	/**
	 * 作用：array转xml
	 * @param array $arr
	 * @return string
	 */
	public function arrayToXml($arr){
		$xml = "<xml>";
		foreach ($arr as $key => $val){
			if (is_numeric($val)){
				$xml.="<".$key.">".$val."</".$key.">";
			}else{
				$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
			}
		}
		$xml .= "</xml>";
		return $xml;
	}
	
	/**
	 * 作用：将xml转为array
	 * @param string $xml
	 * @return array
	 */
	public function xmlToArray($xml){
		$arrayData = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		return $arrayData;
	}
	
	
}

?>