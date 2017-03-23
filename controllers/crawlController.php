<?php

class crawlController {

    public function googleTrendAction($params)
    {
        OurmallApi::getCrawl('Googletrends')->getShoppingAverageValue($params);
    }

    public function testAction($params)
    {
        print_r($params);
    }
}