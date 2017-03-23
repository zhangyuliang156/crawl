<?
if(defined('ZBA_ROOT_PATH')) require_once(ZBA_ROOT_PATH . 'lib/Crawl.php');
abstract class ICrawl extends Crawl
{	
	/**
	 * 通过关键字获取商品
	 * @param $params array 请求参数
	 */
	//abstract public function getProductsByKeywords($params);
	
	/**
	 * 通过分类获取商品
	 * @param $params array 请求参数
	 */
	//abstract public function getProductsByCategory($params);
	
	/**
	 * 通过店铺获取商品
	 * @param $params array 请求参数
	 */
	//abstract public function getProductsByStore($params);
	
	/**
	 * 获取商品信息
	 * @param $params array 请求参数
	 */
	//abstract public function getProduct($params);
	
	/**
	 * 获取评论
	 * @param $params array 请求参数
	 */
	//abstract public function getFeedback($params);
	
	/**
	 * 获取店铺信息
	 * @param $params array 请求参数
	 */
	//abstract public function getStore($params);
}

/**
	implements example:
	class XXXCrawl extends ICrawl
	{
		public function xxx($params)
		{
		}
	}
 */
?>