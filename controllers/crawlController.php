<?php

class crawlController {

    public function googleTrendAction($params)
    {
        $res = OurmallApi::getCrawl('Googletrends')->getMultiline($params);
        return $res;

    }

    public function testAction($params)
    {
        print_r($params);
    }
}