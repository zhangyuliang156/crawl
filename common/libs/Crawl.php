<?
require_once(__DIR__ . '/simple_html_dom.php');
abstract class Crawl
{
    protected $dom;
    protected $urlCache=array();
    function __construct($url = '')
    {
        if('' != $url) {
            $this->load($url);
        }
    }

    function __destory() {
        if($this->dom) $this->dom->clear();
    }

    protected function clear() {
        if($this->dom) $this->dom->clear();
        $this->dom = null; $this->urlCache = array();
    }

    protected function load($url, $plus=array())
    {
        if($this->dom) $this->clear();
        $httpPlus= is_array($plus['http']) ? $plus['http'] : array();
        $contents = $this->_get_content_by_url($url, $httpPlus);
        if (empty($contents) || strlen($contents) > MAX_FILE_SIZE) {
            return false;
        }
        $this->urlCache = array('md5'=>md5($url), 'time'=>time());
        return $this->loadDomByContents($contents, $plus);
    }

    protected function loadDomByContents($contents, $plus=array())
    {
        $lowercase = isset($plus['lowercase']) ? $plus['lowercase'] : true;
        $forceTagsClosed = isset($plus['forceTagsClosed']) ? $plus['forceTagsClosed'] : true;
        $targetCharset = isset($plus['targetCharset']) ? $plus['targetCharset'] : DEFAULT_TARGET_CHARSET;
        $stripRN = isset($plus['stripRN']) ? $plus['stripRN'] : true;
        $defaultBRText = isset($plus['defaultBRText']) ? $plus['defaultBRText'] : DEFAULT_BR_TEXT;
        $defaultSpanText = isset($plus['defaultSpanText']) ? $plus['defaultSpanText'] : DEFAULT_SPAN_TEXT;

        $dom = new simple_html_dom(null, $lowercase, $forceTagsClosed, $targetCharset, $stripRN, $defaultBRText, $defaultSpanText);
        $dom->load($contents, $lowercase, $stripRN);
        $this->dom = $dom;
        return $this->dom;
    }

    protected function _get_content_by_url($url, $plus=array())
    {
        $plus['timeout'] = isset($plus['timeout']) ? $plus['timeout'] : 60;
        $plus['useragent'] = isset($plus['useragent']) ? $plus['useragent'] : "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.122 Safari/537.36 OPR/24.0.1558.64";
        $plus['header'] = isset($plus['header']) ? $plus['header'] : array('Accept-Language:en-US,en;q=0.8,zh-CN;q=0.6,zh;q=0.4','Connection: Keep-Alive','Cache-Control: no-cache', 'Expect:');
        $plus['cookiefile'] = isset($plus['cookiefile']) ? $plus['cookiefile'] : '';
        $plus['cookiesave'] = isset($plus['cookiesave']) ? $plus['cookiesave'] : false;
        $plus['cookiesend'] = isset($plus['cookiesend']) ? $plus['cookiesend'] : false;
        $plus['proxyip'] = isset($plus['proxyip']) ? $plus['proxyip'] : '';
        $plus['encoding'] = isset($plus['encoding']) ? $plus['encoding'] : '';
        $plus['getheader'] 	= isset($plus['getheader']) ? $plus['getheader'] : false;
        $plus['maxredirs'] = isset($plus['maxredirs']) ? intval($plus['maxredirs']) : 0;
        $plus['body'] = is_array($plus['body']) ? http_build_query($plus['body']) : $plus['body'];
        $plus['userpwd'] = isset($plus['userpwd']) ? $plus['userpwd'] : '';
        $urlarr = parse_url($url);

        $ch = curl_init();
        if('' != $plus['cookiefile']) {
            if($plus['cookiesave']) curl_setopt($ch, CURLOPT_COOKIEJAR, $plus['cookiefile']);
            if($plus['cookiesend']) curl_setopt($ch, CURLOPT_COOKIEFILE, $plus['cookiefile']);
        }
        if($plus['proxyip']) {//包含IP和端口号
            curl_setopt($ch, CURLOPT_PROXY, $plus['proxyip']);
        }
        if($plus['encoding']) {
            curl_setopt($ch, CURLOPT_ENCODING, $plus['encoding']);
        }
        if($plus['userpwd']) {
            curl_setopt($ch, CURLOPT_USERPWD, $plus['userpwd']);
        }
        curl_setopt($ch, CURLOPT_COOKIESESSION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $plus['header']);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $plus['useragent']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $plus['timeout']);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);

        if (strtolower($urlarr['scheme']) == 'https')
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        if('post' == strtolower($plus['method']))
        {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $plus['body']);
        }
        else
        {
            if ($plus['body'])
            {
                if (false===strpos($url, '?'))
                    $url .= '?'.$plus['body'];
                else
                    $url .= '&'.$plus['body'];
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);

        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $plus['maxredirs']);
            $return_contents = curl_exec($ch);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            $maxredirs = ($plus['maxredirs'] == 0) ? 20 : $plus['maxredirs'];
            $return_contents = $this->curl_redir_exec($ch, $maxredirs, 0, $plus);
        }
        list($responseHeader, $responseBody) = explode("\r\n\r\n", $return_contents, 2);
        curl_close($ch);
        return (true == $plus['getheader']) ? array('header'=>$responseHeader, 'body'=>$responseBody) : $responseBody;
    }

    private function curl_redir_exec($ch, $curl_max_loops=20, $curl_loops=0, $plus=array())
    {
        $data = curl_exec($ch);
        if($curl_loops >= $curl_max_loops) {
            return $data;
        }
        list($header, $body) = explode("\r\n\r\n", $data, 2);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 301 || $http_code == 302)
        {
            $newHeaders = array();
            $matches = array(); $headerArrays = explode("\n", $header);
            foreach($headerArrays as $content) {
                preg_match('/Location:(.*)/',$content, $matches[0]); if($matches[0]) $location = trim($matches[0][1]);
                preg_match('/Host:(.*)/',$content, $matches[1]); if($matches[1]) $newHeaders[] = trim($matches[1][0]);
                preg_match('/Referer:(.*)/',$content, $matches[2]); if($matches[2]) $newHeaders[] = trim($matches[2][0]);
                preg_match('/Content-Type:(.*)/',$content, $matches[3]); if($matches[3]) $newHeaders[] = trim($matches[3][0]);
            }
            preg_match_all('/Set-Cookie:\s*(.*?);/',$header, $out);
            if(isset($out[1])) {
                $cvs = array(); foreach($out[1] as $cv) { if(! preg_match('/JSESSIONID=/', $cv)) $cvs[] = $cv; }
            }
            $newHeaders[] = 'Cookie: '.implode('; ', $cvs);
            if ($plus['callback']['onRedirectHeader']) {
                try {
                    $newHeadersCopy = $newHeaders;
                    $newHeaders = call_user_func($plus['callback']['onRedirectHeader'], $headerArrays, $newHeaders);
                    $newHeaders = is_array($newHeaders) ? $newHeaders : $newHeadersCopy; unset($newHeadersCopy);
                } catch (\Exception $e) {
                } catch (\Error $e) {
                }
            }
            $url = @parse_url($location);
            if (!$url) {
                return $data;
            }
            $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
            if (!$url['scheme']) {
                $url['scheme'] = $last_url['scheme'];
            }
            if (!$url['host']) {
                $url['host'] = $last_url['host'];
            }
            if (!$url['path']) {
                $url['path'] = $last_url['path'];
            }
            $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']?'?'.$url['query']:'');
            curl_setopt($ch, CURLOPT_URL, $new_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $newHeaders);
            return $this->curl_redir_exec($ch, $curl_max_loops, $curl_loops + 1);
        } else {
            return $data;
        }
    }

    protected function _str_encoding($str, $to='UTF-8', $from='GBK') {
        return mb_convert_encoding($str, $to, $from);
    }

    protected function _is_json_str($text) {
        if(is_string($text)) {
            @json_decode($text); return (json_last_error() === JSON_ERROR_NONE);
        }
        return false;
    }

    protected function _get_random_ip($cond = array())
    {
        $re = '/\d{1,3}(\.\d{1,3}){3}/';
        $ip_long = array(); $rand_key = 0;

        if(isset($cond['code']))
        {
            //@http://ipblock.chacuo.net/
            switch(strtolower($cond['code']))
            {
                case 'cn'://中国
                    $ip_long = array(
                        array('36.56.0.0', '36.63.255.255'),
                        array('61.232.0.0', '61.237.255.255'),
                        array('106.80.0.0', '106.95.255.255'),
                        array('121.76.0.0', '121.77.255.255'),
                        array('123.232.0.0', '123.235.255.255'),
                        array('139.196.0.0', '139.215.255.255'),
                    );
                    break;
                case 'us'://美国
                    $ip_long = array(
                        array('28.0.0.0', '30.255.255.255'),
                        array('32.0.0.0', '35.255.255.255'),
                        array('44.0.0.0', '45.1.255.255'),
                        array('50.0.0.0', '50.21.127.255'),
                        array('65.49.0.0', '65.61.191.255'),
                        array('72.30.0.0', '72.37.255.255'),
                    );
                    break;
                case 'fr'://法国
                    $ip_long = array(
                        array('90.0.0.0', '90.127.255.255'),
                        array('176.128.0.0', '176.191.255.255'),
                        array('37.64.0.0', '37.71.255.255'),
                        array('5.48.0.0', '5.51.255.255'),
                        array('89.2.0.0', '89.3.255.255'),
                        array('188.7.0.0', '188.7.255.255'),
                    );
                    break;
                case 'de'://德国
                    $ip_long = array(
                        array('2.160.0.0', '2.175.255.255'),
                        array('77.0.0.0', '77.15.255.255'),
                        array('78.46.0.0', '78.55.255.255'),
                        array('141.1.0.0', '141.7.255.255'),
                        array('87.77.0.0', '87.79.255.255'),
                        array('46.4.0.0', '46.5.255.255'),
                    );
                    break;
                case 'gb'://英国
                    $ip_long = array(
                        array('90.192.0.0', '90.223.255.255'),
                        array('2.24.0.0', '2.31.255.255'),
                        array('5.64.0.0', '5.71.255.255'),
                        array('80.192.0.0', '80.195.255.255'),
                        array('194.200.0.0', '194.203.255.255'),
                        array('213.1.0.0', '213.2.255.255'),
                    );
                    break;
            }
        }
        else if(isset($cond['ipSegmentStart']))
        {//@deprecated
            $ipstart = isset($cond['ipSegmentStart']) && preg_match($re, $cond['ipSegmentStart']) ? ip2long($cond['ipSegmentStart']) : 16777216;//16777216 =ip2long('1.0.0.0')
            $ipend = isset($cond['ipSegmentEnd']) && preg_match($re, $cond['ipSegmentEnd']) ? ip2long($cond['ipSegmentEnd']) : 4278190079;//4278190079 =ip2long('254.254.254.254')
            $ip_long = array(array($ipstart, $ipend));
        }
        else
        {
            $codes = array('cn', 'us', 'fr', 'de', 'gb');
            return $this->_get_random_ip(array('code'=>$codes[mt_rand(0, count($codes))]));
        }
        if(($ipc = count($ip_long)) == 0) return $this->_get_random_ip(array());
        $rand_key = mt_rand(0, $ipc);
        $ip = long2ip(mt_rand(ip2long($ip_long[$rand_key][0]), ip2long($ip_long[$rand_key][1])));
        return ('0.0.0.0' == $ip) ? $this->_get_random_ip($cond) : $ip;
    }
}
?>