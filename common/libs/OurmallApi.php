<?php
class OurmallApi{

    private static function httpClientRequest($url, $parameters, $method = 'POST', $options = array())
    {
        $urlarr = parse_url($url);
        $withAttach = isset($options['withAttach']) ? $options['withAttach'] : false;
        if ($withAttach) {
            $method = 'POST';
            $rt = self::buildHttpQueryMulti($parameters);
            $body = $rt['multipartbody'];
            $options['header'][] = "Content-Type: multipart/form-data; boundary=" . $rt['boundary'];
        } else {
            $body = is_array($parameters) ? http_build_query($parameters) : $parameters;
        }
        $ch = curl_init();
        $timeout = isset($options['timeout']) ? $options['timeout'] : 60;
        $waitForResponse = isset($options['waitForResponse']) ? $options['waitForResponse'] : true;
        $timeout = $waitForResponse == true ? $timeout : 1;
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        if (isset($options['proxyIp'])) {//包含IP和端口号
            curl_setopt($ch, CURLOPT_PROXY, $options['proxyIp']);
        }
        if (isset($options['header'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $options['header']);
        }
        if (isset($options['encoding'])) {
            curl_setopt($ch, CURLOPT_ENCODING, $options['encoding']);
        }
        if (strtolower($urlarr['scheme']) == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        if (isset($urlarr['port']))
            curl_setopt($ch, CURLOPT_PORT, $urlarr['port']);
        if (strtoupper($method) == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else //GET method
        {
            if ($body) {
                if (false === strpos($url, '?'))
                    $url .= '?' . $body;
                else
                    $url .= '&' . $body;
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private static function buildHttpQueryMulti($params)
    {
        if (!$params) return '';
        uksort($params, 'strcmp');

        $return['boundary'] = $boundary = uniqid('------------------');
        $MPboundary = '--' . $boundary;
        $endMPboundary = $MPboundary . '--';
        $multipartbody = '';

        foreach ($params as $parameter => $value) {
            $multipartbody .= $MPboundary . "\r\n";
            if ($value{0} == '@') {
                $url = ltrim($value, '@');
                $content = file_get_contents($url);
                $array = explode('?', basename($url));
                $filename = $array[0];

                $multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . '"; filename="' . $filename . '"' . "\r\n";
                if (in_array($parameter, array('pic', 'image'))) {
                    $multipartbody .= "Content-Type: image/unknown\r\n\r\n";
                } else {
                    $multipartbody .= "Content-Type: application/octet-stream\r\n\r\n";
                }
                $multipartbody .= $content . "\r\n";
            } else {
                $multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . "\"\r\n\r\n";
                $multipartbody .= $value . "\r\n";
            }
        }
        $multipartbody .= $endMPboundary;
        $return['multipartbody'] = $multipartbody;
        return $return;
    }

    public static function genSign($sort_para, $key)
    {
        //将请求参数格式化为字符串
        $sort_para = array_map(function ($n) {
            return strval($n);
        }, $sort_para);
        $sort_para = self::filterParam($sort_para);
        //把数组所有元素，按照"参数=参数值"的模式用"&"字符拼接成字符串
        $prestr = self::genLinkstring($sort_para);
        //把拼接后的字符串再与安全校验码直接连接起来
        $prestr = $prestr . $key;
        //把最终的字符串签名，获得签名结果
        $mysgin = md5($prestr);
        return $mysgin;
    }

    //把数组所有元素，按照"参数=参数值"的模式用"&"字符拼接成字符串
    public static function genLinkstring($para)
    {
        $arg = "";
        while (list ($key, $val) = each($para)) {
            $arg .= $key . "=" . $val . "&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);
        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }
        return $arg;
    }

    //除去数组中的空值和签名参数
    public static function filterParam($para)
    {
        $para_filter = array();
        while (list ($key, $val) = each($para)) {
            if ($key == "sign" || $val == "") continue;
            else $para_filter[$key] = $para[$key];
        }
        return self::sortParam($para_filter);
    }

    //对数组排序
    public static function sortParam($para)
    {
        ksort($para);
        reset($para);
        return $para;
    }

    public static function isJsonStr($text)
    {
        return is_scalar($text) && (preg_match("/^{.*}$/", $text) || preg_match('/^\[.*\]$/', $text));
    }

    /**
     * 获取爬虫对象，通过爬虫名称（支持爬虫:ebay,smt,amz,wish）
     */
    public static function getCrawl($crawlName)
    {
        $crawlObjectName = ucfirst(strtolower($crawlName)) . 'Crawl';
        if (!is_file($crawlFile = COMMON_LIBS_CRAWL_PATH . $crawlObjectName.EXT_CLASS)) return NULL;
        require_once(COMMON_LIBS_CRAWL_PATH . 'ICrawl.php');
        require_once($crawlFile);
        $cObject = new $crawlObjectName();
        if (!is_a($cObject, 'ICrawl')) return NULL;
        return $cObject;
    }

    /**
     * 获取爬虫对象，通过爬虫名称（支持爬虫:ebay,smt,amz,wish）
     */
    public static function getYoutubeCrawl($crawlName)
    {
        $crawlCacheName = "YOUTUBE_CRAWL_OBJECT_" . strtoupper($crawlName);
        if (defined('ZBA_ROOT_PATH')) require_once(ZBA_ROOT_PATH . 'core/Registry.php');
        if (Registry::exists($crawlCacheName)) return Registry::get($crawlCacheName);
        $crawlObjectName = ucfirst(strtolower($crawlName)) . 'Crawl';
        if (!is_file($crawlFile = COMMON_CONFIG_PATH . 'libs/util/youtubecrawl/' . $crawlObjectName . '.php')) return NULL;
        require_once(COMMON_CONFIG_PATH . 'libs/util/youtubecrawl/IYoutubeCrawl.php');
        require_once($crawlFile);
        $cObject = new $crawlObjectName();
        if (!is_a($cObject, 'IYoutubeCrawl')) return NULL;
        Registry::set($crawlCacheName, $cObject);
        return $cObject;
    }

    /**
     * 向移动设备推送消息
     * array($memberUid, $deviceVersion, $platform, $jpushCode, $message)
     */
    public static function pushMessage($params)
    {
        global $MEMBER_DEVICE_VERSIONS;
        if (empty($params['jpushCode'])) throw new Exception('Push code is empty');
        if (! isset($MEMBER_DEVICE_VERSIONS[$params['deviceVersion']])) throw new Exception('deviceVersion invalid');
        $config = $MEMBER_DEVICE_VERSIONS[$params['deviceVersion']];
        switch($config['app'])
        {
            case 'jpush':
                if (!is_file($jpushFile = __DIR__ . '/jpush/JPush.php')) throw new Exception('JPush File not exists');
                require_once($jpushFile);
                if (! in_array($params['platform'], array('ios', 'android'))) throw new Exception('plateform invalid');
                $plus = isset($params['plus']) && is_array($params['plus']) ? $params['plus'] : array();
                $pushCacheName = "PUSH_OBJECT_JPUSH_MESSAGE_" . $params['deviceVersion'];
                if (defined('ZBA_ROOT_PATH')) require_once(ZBA_ROOT_PATH . 'core/Registry.php');
                $client = NULL;
                if (Registry::exists($pushCacheName)) $client = Registry::get($pushCacheName);
                else {
                    $client = new JPush($config['appKey'],//appKey
                        $config['masterSecret'], //masterSecret
                        TEMP_PATH . date('Ym') . '-jpush.log');//log
                    Registry::set($pushCacheName, $client);
                }
                try {
                    $plusParams = array("memberUid" => $params['memberUid'], 'nowTime' => date('Y-m-d H:i:s')) + $plus;
                    $badge = (isset($params['unReadCnt']) && !empty($params['unReadCnt'])) ?intval($params['unReadCnt']):'+1';
                    $production = $params['production'] == true ? true : false;
                    $result = $client->push()
                        ->setPlatform(array($params['platform']))
                        ->addRegistrationId($params['jpushCode'])
                        ->addAndroidNotification($params['message'], 'OurMall', 1, $plusParams)
                        ->addIosNotification($params['message'], NULL, $badge, true, NULL, $plusParams)
                        ->setMessage($params['message'], 'OurMall', 'chat', $plusParams)
                        ->setOptions(100000, 3600, null, $production)
                        ->send();
                    return $result->data->msg_id;
                } catch (Exception $ex) {
                    throw new Exception('Message send error:'.$ex->getMessage());
                }
                break;
            case 'getui':
                //please refer to the document: http://docs.getui.com/server/php/start/
                if (!is_file($getuiFile = __DIR__ . '/igetui/IGt.Push.php')) throw new Exception('GeTui File not exists');
                require_once($getuiFile);
                //$igt = new IGeTui(HOST,APPKEY,MASTERSECRET);
                $igt = new IGeTui(NULL,$config['appKey'],$config['masterSecret'],false);
                require_once(__DIR__ . '/igetui/template/ourmall.php');
                //消息模版：
                // 1.TransmissionTemplate:透传功能模板
                // 2.LinkTemplate:通知打开链接功能模板
                // 3.NotificationTemplate：通知透传功能模板
                // 4.NotyPopLoadTemplate：通知弹框下载功能模板
//    	$template = IGtNotyPopLoadTemplateForOurmall($config + array('title'=>'Ourmall','content'=>$params['message']));
//    	$template = IGtLinkTemplateForOurmall($config + array('title'=>'Ourmall','content'=>$params['message']));
//    	$template = IGtNotificationTemplateForOurmall($config + array('title'=>'Ourmall','content'=>$params['message']));
                $template = IGtTransmissionTemplateForOurmall($config + array('title'=>'Ourmall','content'=>$params['message']));
                //个推信息体
                $message = new IGtSingleMessage();
                $message->set_isOffline(true);//是否离线
                $message->set_offlineExpireTime(3600*12*1000);//离线时间
                $message->set_data($template);//设置推送消息类型
//	$message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送
                //接收方
                $target = new IGtTarget();
                $target->set_appId($config['appId']);
                $target->set_clientId($params['jpushCode']);
//    $target->set_alias(Alias);
                try {
                    $rep = $igt->pushMessageToSingle($message, $target);
                    return $rep['taskId'];
                }catch(RequestException $e){
                    $requstId =$e.getRequestId();
                    $rep = $igt->pushMessageToSingle($message, $target, $requstId);
                    if(isset($rep['taskId'])) return $rep['taskId']; else {
                        if(is_dir(TEMP_PATH)) file_put_contents(TEMP_PATH . date('Ym') . '-jpush.log', print_r($rep, 1), 8);
                    }
                }
                break;
            default:
                throw new Exception('Error');
                break;
        }
    }

    public static function getMailGun($params = array())
    {
        $mailgunCacheName = "MAILGUN_OBJECT_GET_MAILGUN";
        if (defined('ZBA_ROOT_PATH')) require_once(ZBA_ROOT_PATH . 'core/Registry.php');
        $client = NULL;
        if (Registry::exists($mailgunCacheName)) $client = Registry::get($mailgunCacheName);
        else {
            if (!is_file($autoloadFile = __DIR__ . '/Mailgun/autoload.php')) throw new Exception('mailgun File not exists');
            include($autoloadFile);
            global $MAILGUN_API_KEY, $MAILGUN_VERSIONS;
            $apiKey = isset($MAILGUN_VERSIONS[$params['version']]) ? $MAILGUN_VERSIONS[$params['version']]['apiKey'] : $MAILGUN_API_KEY;
            $client = new \Mailgun\Mailgun($apiKey);
            Registry::set($mailgunCacheName, $client);
        }
        return $client;
    }

    public static function getPhpMailer($params=array())
    {
        require_once(__DIR__ . '/phpmailer/class.phpmailer.php');
        require_once(__DIR__ . '/phpmailer/class.smtp.php');
        return new PHPMailer();
    }

    public static function getAwsTools($params = array())
    {
        $m = in_array($params['m'], array('email', 'db', 'notification', 'queue')) ? $params['m'] : 'email';
        $awsCacheName = "AWS_OBJECT_GET_AWSTOOLS_".$m;
        if (defined('ZBA_ROOT_PATH')) require_once(ZBA_ROOT_PATH . 'core/Registry.php');
        $client = NULL;
        if (Registry::exists($awsCacheName)) $client = Registry::get($awsCacheName);
        else {
            if (!is_file($autoloadAwsFile = __DIR__ . '/awstools/aws.php')) throw new Exception('Aws file not exists');
            if (!is_file($autoloadAwsToolFile = __DIR__ . '/awstools/simple'.$m.'.php')) throw new Exception('Aws tool file not exists');
            include($autoloadAwsFile); include($autoloadAwsToolFile);
            global $AWS_API_VERSIONS;
            $api = isset($AWS_API_VERSIONS[$params['version']]) ? $AWS_API_VERSIONS[$params['version']] : $AWS_API_VERSIONS[1];
            $std = "Simple".ucfirst($m);
            $client = new $std($api['key'], $api['secret'], $api['endpoint']);
            Registry::set($awsCacheName, $client);
        }
        return $client;
    }

    /**
     * @param $params array('to', 'name', 'subject', 'body') or array('tos'=>array(array('to'=>'', 'name'=>''), array('to'=>'', 'name'=>'')), 'subject', 'body')
     * @return bool
     * @throws Exception
     */
    public static function sendMailByAwsSmtp($params)
    {
        global $AWS_API_VERSIONS;
        $v = isset($AWS_API_VERSIONS[$params['version']]) ? $AWS_API_VERSIONS[$params['version']] : 1;
        $smtpOptions = $AWS_API_VERSIONS[$v];

        $tos = array();//array(array('to'=>'', 'name'=>''), array('to'=>'', 'name'=>''), array('to'=>'', 'name'=>''))
        if(is_array($params['tos'])) $tos = $params['tos'];
        if(isset($params['to'])) $tos[] = array('to'=>$params['to'], 'name'=>isset($params['name']) ? $params['name'] : $params['to']);
        if(count($tos) == 0) return false;

        $ccs = array();//array(array('cc'=>'', 'name'=>''), array('cc'=>'', 'name'=>''), array('cc'=>'', 'name'=>''))
        if(is_array($params['ccs'])) $ccs = $params['ccs'];
        if(isset($params['cc'])) $ccs[] = array('cc'=>$params['cc'], 'name'=>isset($params['ccname']) ? $params['ccname'] : $params['cc']);

        $mail = self::getPhpMailer();
        $mail->isSMTP(true);
        $mail->Host       = $smtpOptions['host'];
        $mail->Port       = $smtpOptions['port'];
        $mail->SMTPSecure = $smtpOptions['secure'];
        $mail->SMTPDebug  = 0;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpOptions['key'];
        $mail->Password   = $smtpOptions['secret'];
        $mail->Mailer     = "smtp";
        $mail->CharSet    = "UTF-8";
        $mail->Subject    = $params['subject'];
        $mail->setFrom('service@ourmall.com', 'Ourmall');//客户回复邮件地址
        $mail->msgHTML($params['body']);

        foreach($tos as $to) { $mail->addAddress($to['to'], $to['name']); }
        if(is_array($ccs)) foreach($ccs as $cc) { $mail->addCC($cc['cc'], $cc['name']); }

        if(! $mail->send()) throw new Exception($mail->ErrorInfo);
        $mail->clearAddresses();
        return true;
    }

}