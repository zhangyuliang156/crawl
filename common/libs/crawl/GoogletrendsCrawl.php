<?php

class GoogletrendsCrawl extends ICrawl
{
    /**
     * 获得shopping 关键词的平均值
     */

    private $hl = 'hl=zh-CN';

    private $tz = 'tz=-480';

    private $period = array(
        '1h'  => 'now 1-H',
        '4h'  => 'now 4-H',
        '1d'  => 'now 1-d',
        '7d'  => 'now 7-d',
        '1m'  => 'today 1-m',
        '3m'  => 'today 3-m',
        '12m' => 'today 12-m',
        '5y'  => 'today 5-y',
        'all' => 'all',
    );

    public function getMultiline($params)
    {
        $commQueryParam = $this->hl.'&'.$this->tz;
        $req = '{"comparisonItem":[{"keyword":"'.$params['keywords'].'","geo":"","time":"'.$this->period['12m'].'"}],"category":0,"property":""}';
        $tokenRequestUrl = 'https://trends.google.com/trends/api/explore?'.$commQueryParam.'&req='.urlencode($req);
        $result = $this->_get_content_by_url($tokenRequestUrl);
        $jsonResult = json_decode(substr($result,4));
        $token = $jsonResult->widgets[0]->token;
        $requestObj = $jsonResult->widgets[0]->request;
        $req = urlencode(json_encode($requestObj));
        $requestUrl = 'https://trends.google.com/trends/api/widgetdata/multiline?'.$commQueryParam.'&req='.$req.'&token='.$token;
        $rtJson = $this->_get_content_by_url($requestUrl,array('method'=>'GET','header'=>array(),'body'=>array()));
        $resJson = json_decode(substr($rtJson,5),2);
        $timeLineDataArr =  $resJson['default']['timelineData'];
        return $timeLineDataArr;
    }
    
    public function compareWithShopping($params) {
        $commQueryParam = $this->hl.'&'.$this->tz;
        $cookie = $this->getCookie();
        $req = '{"comparisonItem":[{"keyword":"'.$params['keywords'].'","geo":"US","time":"'.$this->period[$params['period']].'"},{"keyword":"shopping","geo":"US","time":"'.$this->period[$params['period']].'"}],"category":0,"property":""}';
        $exploreUrl = 'https://trends.google.com/trends/api/explore?'.$commQueryParam.'&req='.urlencode($req);
        $resultArr = $this->_get_content_by_url($exploreUrl,array('getheader'=>true,'method'=>'GET','header' => array('Cookie'=>$cookie),'body'=>array()));
        $cookie = $this->getRequestCookie($resultArr['header']);
        $result = $resultArr['body'];
        $jsonResult = json_decode(substr($result,4));
        $token = $jsonResult->widgets[0]->token;
        $requestObj = $jsonResult->widgets[0]->request;
        $req = urlencode(json_encode($requestObj));
        $requestUrl = 'https://trends.google.com/trends/api/widgetdata/multiline?'.$commQueryParam.'&req='.$req.'&token='.$token;
        $rtJson =  $this->_get_content_by_url($requestUrl,array('method'=>'GET','header'=>array('Cookie'=>$cookie),'body'=> array()));
        $resJson = json_decode(substr($rtJson,5));
        $compareRes =  $resJson->default;
        $averages = $compareRes->averages;
        $returnArr['averages']['keywords'] = $averages[0];
        $returnArr['averages']['shopping'] = $averages[1];
        $returnArr['timelineData'] = $compareRes->timelineData;
        return $returnArr;
    }

    private function getRequestCookie($responseHeader)
    {
        preg_match_all('/Set-Cookie:\s(.*?);/', $responseHeader, $cookie);
        $ck = isset($cookie[1]) ? $cookie[1] : array();
        $cookie = implode('; ', $ck);
        return $cookie ? $cookie : '';
    }
    private function getCookie()
    {
        $rt = $this->_get_content_by_url('https://trends.google.com/trends/explore', array(
            'header'    => array(
                'Connection: Keep-Alive',
                'Cache-Control: no-cache',
            ),
            'getheader' => true,
            'headerout' => true,
        ));
        preg_match_all('/Set-Cookie:\s(.*?);/', $rt['header'], $cookie);
        $cookie = isset($cookie[1]) ? $cookie[1] : array();
        $cookie = implode('; ', $cookie);
        return $cookie;
    }

    public  function test()
    {
        $rt = $this->_get_content_by_url('https://www.baidu.com');
        print_r($rt);exit;
    }
}
?>