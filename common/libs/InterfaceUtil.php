<?
class InterfaceUtil
{
	public static function httpClientRequest($url, $data, $method = 'POST', $waitForResponse = true, $timeout = 60, $options = array())
	{
		$urlarr = parse_url($url);
		$data = is_array($data) ? http_build_query($data) : $data;
		$ch = curl_init();
		$timeout = $waitForResponse == true ? $timeout : 1;
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		if(isset($options['proxyIp']))//包含IP和端口号
			curl_setopt($ch, CURLOPT_PROXY, $options['proxyIp']);
		if(isset($options['header']))
			curl_setopt($ch, CURLOPT_HTTPHEADER, $options['header']);
		if(isset($options['encoding']))
			curl_setopt($ch, CURLOPT_ENCODING, $options['encoding']);
		if($options['userpwd'])
			curl_setopt($ch, CURLOPT_USERPWD, $options['userpwd']);

		if (strtolower($urlarr['scheme']) == 'https')
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);	
		}
		if (isset($urlarr['port']))
			curl_setopt($ch, CURLOPT_PORT, $urlarr['port']);
		if (strtoupper($method) == 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		else //GET method
		{
			if ($data)
			{
				if (false===strpos($url, '?'))
					$url .= '?'.$data;
				else
					$url .= '&'.$data;
			}
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		$output = curl_exec($ch);
        curl_close($ch);
		return $output;
	}
	
	public static function recordLog($word='', $logname = '', $logPath = '')
	{
		$logPath = empty($logPath) ? __DIR__ : $logPath;
		if(! is_dir($logPath)) mkdir($logPath, 0777, TRUE);
		$logname = empty($logname) ? date("Ym").".log" : (preg_match('/^\d{6}.*\.log$/', $logname) ? $logname : date("Ym").'-'.$logname.(preg_match('/\.log$/', $logname) ? '' : '.log'));
		file_put_contents(preg_replace('/\/$/',  '', $logPath).'/'.$logname, "执行日期：".strftime("%Y-%m-%d %H:%M:%S",time())."\n".$word."\n", FILE_APPEND);
	}
	
	public static function getMacAddr($os_type)
	{
		$return_array = array();
		switch ( strtolower($os_type) )
		{
			case "linux": @exec("ifconfig -a", $return_array); break;
			case "solaris": break;    
            case "unix": break;    
			case "aix": break;
			default: 
				@exec("ipconfig /all", $return_array);
				if (count($return_array) < 1)
				{
					$ipconfig = $_SERVER["WINDIR"]."\system32\ipconfig.exe";
					if (is_file($ipconfig))
						@exec($ipconfig." /all", $return_array);
					else    
						@exec($_SERVER["WINDIR"]."\system\ipconfig.exe /all", $return_array);
				}
			break;
		}
		$temp_array = array();$mac_addr = '';
		foreach($return_array as $value ) 
		{
			if (preg_match("/([0-9a-f]{2}[:|-]){5}[0-9a-f]{2}/i",
				$value,$temp_array))
			{
				$mac_addr = $temp_array[0];  break;    
			}    
		}    
		unset($temp_array);    
		return $mac_addr;    
	}    
	
	public static function genSystemConfigFile($file, $params = array())
	{		
		if(is_array($params) && count($params) > 0) $CUSTOM_DEFINED_CONSTANT_ARRAY = $params;
		else 
			return false;
			
		$str = '';
		foreach($CUSTOM_DEFINED_CONSTANT_ARRAY as $CONSTANT_KEY => $CONSTANT_VALUES)
		{	
			$str .= '$'.$CONSTANT_KEY.' = array('."\r\n";
			if(count($CONSTANT_VALUES) > 0) foreach($CONSTANT_VALUES as $K=>$V)
			{
				$K = preg_replace(array('/\\\/'), array(''), $K); $K = preg_replace(array('/\'/'), array('\\\''), $K); 
				$V = preg_replace(array('/\\\/'), array(''), $V); $V = preg_replace(array('/\'/'), array('\\\''), $V);
				$str .= "\t".'\''.$K.'\'=>\''.$V.'\','."\r\n";
			}
			$str .= ');'."\r\n";
		}
		
		$content = self::genConfigFileHeaderFooter($str);
		
		is_file($file) && unlink($file);
		if(!is_dir($file_dir = dirname($file))) mkdir($file_dir, 0777, true);
		file_put_contents($file, $content);
		return true;
	}
	
	private static function genConfigFileHeaderFooter($content)
	{
		$str = '';
		$str .= '<?php'."\r\n";
		$str .= '/**'."\r\n";
		$str .= ' * 创建时间:'.date('Y-m-d H:i:s')."\r\n";
		$str .= ' */'."\r\n";
		$str .= "\r\n";
		
		$str .= $content;
		
		$str .= '?>'."\r\n";
		
		return $str;
	}
	
	public static function genSign($sort_para, $key) 
	{
		//将请求参数格式化为字符串，不同数据类型，加密出的密钥不同例:$a=1,和$a='1';走以下流程会得到不同加密结果
		$sort_para = array_map(function($n){ return strval($n); }, $sort_para);
		$sort_para = self::filterParam($sort_para);
		//把数组所有元素，按照"参数=参数值"的模式用"&"字符拼接成字符串
		$prestr = self::genLinkstring($sort_para);
		//把拼接后的字符串再与安全校验码直接连接起来
		$prestr = $prestr.$key;
		//把最终的字符串签名，获得签名结果
		$mysgin = md5($prestr);
		return $mysgin;
	}
	
	/**
	 * 把数组所有元素，按照"参数=参数值"的模式用"&"字符拼接成字符串
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	public static function genLinkstring($para) 
	{
		$arg  = "";
		while (list ($key, $val) = each ($para)) {
			$arg.=$key."=".$val."&";
		}
		//去掉最后一个&字符
		$arg = substr($arg,0,count($arg)-2);
		
		//如果存在转义字符，那么去掉转义
		if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}
		
		return $arg;
	}
	
	/**
	 * 把数组所有元素，按照"参数=参数值"的模式用"&"字符拼接成字符串，并对字符串做urlencode编码
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	public static function getLinkstringUrlencode($para) 
	{
		$arg  = "";
		while (list ($key, $val) = each ($para)) {
			$arg.=$key."=".urlencode($val)."&";
		}
		//去掉最后一个&字符
		$arg = substr($arg,0,count($arg)-2);
		
		//如果存在转义字符，那么去掉转义
		if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}
		
		return $arg;
	}
	
	public static function getDecodeRequestParam($paras)
	{
		$return_array = array();
		if(is_array($paras) && count($paras) > 0)foreach($paras as $k=>$para) 
		{
			$value = self::onceUrldecode($para);
			//eg:$str = 'b64d,jtj|VGhpcyBpcyBhbiBlbmNvZGVkIHN0cmluZw==';
			if(preg_match('/^(b64d,\s*jta\|)/i', $value))
			{
				$value = substr($value, strpos($value, '|') + 1);
				$value = ('' != $value && preg_match('/\s/', $value)) ? preg_replace('/\s/', '+', $value) : $value;
				$value = base64_decode($value);
				$value = self::isJsonStr($value) ? json_decode($value, true) : $value;
			}
			else if(preg_match('/^(b64d,\s*jtj\|)/i', $value))
			{
				$value = substr($value, strpos($value, '|') + 1);
				$value = ('' != $value && preg_match('/\s/', $value)) ? preg_replace('/\s/', '+', $value) : $value;
				$value = base64_decode($value);
				$value = self::isJsonStr($value) ? json_decode($value) : $value;
			}
			else if(preg_match('/^(b64d\|)/i', $value))
			{
				$value = substr($value, strpos($value, '|') + 1);
				$value = ('' != $value && preg_match('/\s/', $value)) ? preg_replace('/\s/', '+', $value) : $value;
				$value = base64_decode($value);
			}
			else if(preg_match('/^(jtj\|)/i', $value))
			{
				$value = substr($value, strpos($value, '|') + 1);
				$value = self::isJsonStr($value) ? json_decode($value) : $value;
			}
			else if(preg_match('/^(jta\|)/i', $value)) 
			{
				$value = substr($value, strpos($value, '|') + 1);
				$value = self::isJsonStr($value) ? json_decode($value, true) : $value;
			}
			else
			{
				//'{\"point\":\"30\",\"code\":\"\",\"type\":2,\"promotionId\":\"21\",\"prolongDay\":30,\"test\":\"\\u8fd9\\u662f\\u4e00\\u4e2a\\u6d4b\\u8bd5\"}';
				$str1 = str_replace('\\"','"',$value); 
				$str2 = str_replace('\\u','\u',$str1);
				//过滤之后：{"point":"30","code":"","type":2,"promotionId":"21","prolongDay":30,"test":"\u8fd9\u662f\u4e00\u4e2a\u6d4b\u8bd5"}
				$value = trim($str2); 
			}
			$return_array[$k] = $value;
		}
		return $return_array;
	}
	
	/**
	 * 避免二次解码导致加号丢失
	 */
	public static function onceUrldecode($string) 
	{
		if(preg_match('#%[0-9A-Z]{2}#isU', $string) > 0) {
			$string = rawurldecode($string);
		}
		return $string;
	}
	
	/**
	 * 除去数组中的空值和签名参数
	 * @param $para 签名参数组
	 * return 去掉空值与签名参数后的新签名参数组
	 */
	public  static function filterParam($para) 
	{
		$para_filter = array();
		while (list ($key, $val) = each ($para)) {
			if($key == "sign" || $val == "")continue;
			else $para_filter[$key] = $para[$key];
		}
		return self::sortParam($para_filter);
	}
	/**
	 * 对数组排序
	 * @param $para 排序前的数组
	 * return 排序后的数组
	 */
	public static function sortParam($para) 
	{
		ksort($para);
		reset($para);
		return $para;
	}
	
	public static function compressStrings($text)
	{
		return base64_encode(gzdeflate($text, 9));
	}
	
	public static function decompressStrings($text)
	{
		if($text) return @gzinflate(base64_decode($text));
		else return false;
	}
	
	/**
	 * preg_match("/^{.*}$/", $text)	判断{"a":"123", "b":"32"}
	 * preg_match("/^\[.*\]$/", $text)	判断["23", "53", "32", "666"]
	 */
	public static function isJsonStr($text) {
		return is_scalar($text) && (preg_match("/^{.*}$/", $text) || preg_match("/^\[.*\]$/", $text));
	}
	
	/**
	 * 
		 $m = getMemcache('127.0.0.1', '11211');
		 if($m) {
			print_r($m->getStats());
		 }
	 * 
	 * usleep(50000) = 休息 5s , 1个小时运行60 * 60 * 60 * 5 = 	1080000次
	 *					    1s	 						   		216000次
	 */
	public static function getMemcache($host=MC_HOST, $port=MC_PORT)
	{
		$i = 0;
		while(true)
		{
			set_time_limit(0); $i ++;
			$memcache = new \Memcache();
			$mem_status = @$memcache->connect($host, $port);//检查memcache是否可以连通
			if($mem_status !== true) { 
				if($i > 216000) return false;//超过1小时不响应
				sleep(rand(1, 5)); continue; 
			} unset($mem_status);
			return $memcache;
		}
		return false;
	}
		
	public static function getHttpClient($queue_name, $limit, $host = HTTPSQS_HOST, $port = HTTPSQS_PORT, $auth = HTTPSQS_AUTH, $charset = HTTPSQS_CHARSET)
	{
		$httpsqs = new HttpsqsClient($host, $port, $auth, $charset);
		$queue_status_json = $httpsqs->status_json($queue_name);
		$queue_status = json_decode($queue_status_json);
		if(empty($queue_status) || $queue_status->unread > intval($limit)) { 
			return false;
		}
		return $httpsqs;
	}
	
}
?>
