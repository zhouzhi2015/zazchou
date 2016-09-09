<?php 
/**
 * 全局参数配置
 * @author zhouzhi@kuaiqiangche.com
 * @version 1.0.0 at 20160823 11:28
 */
namespace  kqcPay\Config;

class ConfigMsg{
	
	const VALID_BC_PARAM = 'APP ID,APP Secret,Master Secret参数值均不能为空,请重新设置';
	const VALID_SIGN_PARAM = 'APP ID, timestamp,APP(Master) Secret参数值均不能为空,请设置';
	const NEED_RETURN_URL = "当channel参数为 ALI_WEB return_url为必填";
	const NEED_WX_JSAPI_OPENID = "微信公众号支付(WX_JSAPI) 需要openid字段";
	const NEED_VALID_PARAM = "字段值不合法:";
	const APP_INVALID = "签名错误，请检查签名算法及所使用secret是否正确";
	
	const UNEXPECTED_RESULT = "非预期的返回结果:";
	const NEED_PARAM = "需要必填字段:";
	
	const VALID_MASTER_SECRET = 'Master Secret参数值不能为空,请设置';
	const VALID_APP_SECRET = 'APP Secret参数值不能为空,请设置';
	
	const VALID_PARAM_RANGE = '参数 %s 不在限定的范围内, 请重新设置';
}

?>