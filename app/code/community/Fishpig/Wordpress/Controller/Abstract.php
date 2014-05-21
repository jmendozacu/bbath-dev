<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

abstract class Fishpig_Wordpress_Controller_Abstract extends Mage_Core_Controller_Front_Action
{
	/**
	 * Root templates to be used
	 *
	 * @var array
	 */
	protected $_rootTemplates = array('template_default');
	
	/**
	 * Used to do things en-masse
	 * eg. include canonical URL
	 *
	 * If null, means no entity required
	 * If false, means entity required but not set
	 *
	 * @return null|false|Mage_Core_Model_Abstract
	 */
	public function getEntityObject()
	{
		return null;
	}
	
	/**
	 * Ensure that the a database connection exists
	 * If not, do load the route
	 *
	 * @return $this
	 */
    public function preDispatch()
    {
    	parent::preDispatch();

		try {
			if (!$this->_canRunUsingConfig()) {
				$this->_forceForwardViaException('noRoute');
				return;
			}
			
			if ($this->getRequest()->getParam('feed')) {
				if ($this->getRequest()->getActionName() !== 'feed') {
					if ($this->hasAction('feed')) {
						$this->_forceForwardViaException('feed');
						return;
					}

					$this->_forceForwardViaException('noRoute');
					return;
				}
			}
		}
		catch (Mage_Core_Controller_Varien_Exception $e) {
			throw $e;
		}
		catch (Exception $e) {
			Mage::helper('wordpress')->log($e->getMessage());

			$this->_forceForwardViaException('noRoute');
			return;
		}

		return $this;
    }

	/**
	 * Determine whether the extension can run using the current config settings for this scope
	 * This will attempt to connect to the DB
	 *
	 * @return bool
	 */
	protected function _canRunUsingConfig()
	{
		if (!$this->isEnabledForStore()) {
			return false;
		}

		$helper = Mage::helper('wordpress/database');
		
		if (!$helper->isConnected() || !$helper->isQueryable()) {
			return false;
		}
		
		$helper->getReadAdapter()->query('SET NAMES UTF8');

		if ($this->getEntityObject() === false) {
			return false;
		}

		return true;
	}
	
	/**
	 * Before rendering layout, apply root template (if set)
	 * and add various META items
	 *
	 * @param string $output = ''
	 * @return $this
	 */
    public function renderLayout($output='')
    {
		if (($headBlock = $this->getLayout()->getBlock('head')) !== false) {
			if (Mage::helper('wordpress')->getWpOption('blog_public') !== '1') {
				$headBlock->setRobots('noindex,nofollow');
			}

			if ($entity = $this->getEntityObject()) {
				$headBlock->addItem('link_rel', $entity->getUrl(), 'rel="canonical"');
			}
		}

		$rootTemplates = array_reverse($this->_rootTemplates);
		
		foreach($rootTemplates as $rootTemplate) {
			if ($template = Mage::getStoreConfig('wordpress_blog/layout/' . $rootTemplate)) {
				$this->getLayout()->helper('page/layout')->applyTemplate($template);
				break;
			}
		}

		return parent::renderLayout($output);
	}

	/**
	 * Loads layout and performs initialising tasls
	 *
	 */
	protected function _initializeBlogLayout()
	{
		if (!$this->_isLayoutLoaded) {
			$this->loadLayout();
		}
		
		if ($this->getSeoPlugin()->isEnabled()) {
			if ($headBlock = $this->getLayout()->getBlock('head')) {
				foreach($this->getSeoPlugin()->getMetaFields() as $field) {
					if ($value = $this->getSeoPlugin()->getData('home_'.$field)) {
						$headBlock->setData($field, trim($value));
					}
				}
			}
		}
		
		$this->_title()->_title(Mage::helper('wordpress')->getWpOption('blogname'));

		$this->_addCrumb('home', array('link' => Mage::getUrl(), 'label' => $this->__('Home')))
			->_addCrumb('blog', array('link' => Mage::helper('wordpress')->getUrl(), 'label' => $this->__(Mage::helper('wordpress')->getTopLinkLabel())));
		
		if ($rootBlock = $this->getLayout()->getBlock('root')) {
			$rootBlock->addBodyClass('is-blog');
		}
		
		return $this;
	}

	/**
	 * Quickly set meta values
	 *
	 * @param array $data
	 * @return $this
	 */
	protected function _setMeta(array $data)
	{
		if (($headBlock = $this->getLayout()->getBlock('head')) !== false) {
			foreach($data as $key => $value) {
				if (($value = trim($value)) !== '') {
					$headBlock->setData($key, $value);
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Adds a crumb to the breadcrumb trail
	 *
	 * @param string $crumbName
	 * @param array $crumbInfo
	 * @param string $after
	 */
	protected function _addCrumb($crumbName, array $crumbInfo, $after = false)
	{
		if ($breadcrumbs = $this->getLayout()->getBlock('breadcrumbs')) {
			if (!isset($crumbInfo['title'])) {
				$crumbInfo['title'] = $crumbInfo['label'];
			}
		
			$breadcrumbs->addCrumb($crumbName, $crumbInfo, $after);
		}
		
		return $this;
	}

	/**
	 * Adds custom layout handles
	 *
	 * @param array $handles = array()
	 */
	protected function _addCustomLayoutHandles(array $handles = array())
	{
		array_unshift($handles, array('default'));
		$update = $this->getLayout()->getUpdate();
		
		foreach($handles as $handle) {
			$update->addHandle($handle);
		}
		
		$this->addActionLayoutHandles();
		$this->loadLayoutUpdates();
		$this->generateLayoutXml()->generateLayoutBlocks();
		$this->_isLayoutLoaded = true;
		
		return $this;
	}
	
	/**
	 * Retrieve the helper for the All-In-One SEO plugin
	 *
	 * @return Fishpig_Wordpress_Helper_Abstract
	 */
	public function getSeoPlugin()
	{
		return Mage::helper('wordpress/plugin_allInOneSeo');
	}
	
	/**
	 * Retrieve the router helper object
	 *
	 * @return Fishpig_Wordpress_Helper_Router
	 */
	public function getRouterHelper()
	{
		return Mage::helper('wordpress/router');
	}
	
	/**
	 * Determine whether the extension has been enabled for the current store
	 *
	 * @return bool
	 */
	public function isEnabledForStore()
	{
		return !Mage::getStoreConfigFlag('advanced/modules_disable_output/Fishpig_Wordpress');
	}

	/**
	 * Force Magento ro redirect to a different route
	 * This will happen without changing the current URL
	 *
	 * @param string $action
	 * @param string $controller = ''
	 * @param string $module = ''
	 * @param array $params = array
	 * @return void
	 */
	protected function _forceForwardViaException($action, $controller = '', $module = '', $params = array())
	{
		if ($action === 'noRoute') {
			$controller = 'index';
			$module = 'cms';
		}
		else {
			if ($controller === '') {
				$controller = $this->getRequest()->getControllerName();
			}
			
			if ($module === '') {
				$module = $this->getRequest()->getModuleName();
			}
		}
				
		$this->setFlag('', self::FLAG_NO_DISPATCH, true);
		
		$e = new Mage_Core_Controller_Varien_Exception();
	
		throw $e->prepareForward($action, $controller, $module, $params);
	}
}
