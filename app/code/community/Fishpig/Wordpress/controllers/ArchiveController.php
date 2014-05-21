<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_ArchiveController extends Fishpig_Wordpress_Controller_Abstract
{
	/**
	 * Used to do things en-masse
	 * eg. include canonical URL
	 *
	 * @return false|Fishpig_Wordpress_Model_Archive
	 */
	public function getEntityObject()
	{
		return $this->_initArchive();
	}
	
	/**
	  * Display the archive and list the posts
	  *
	  */
	public function viewAction()
	{
		$archive = Mage::registry('wordpress_archive');
		
		$this->_addCustomLayoutHandles(array(
			'wordpress_archive_view_index', 
		));
			
		$this->_initializeBlogLayout();
		
		$this->_rootTemplates[] = 'template_post_list';

		$this->_title($archive->getName())
			->_addCrumb('archive', array('label' => $archive->getName()));
			
		if ($seo = $this->getSeoPlugin()) {
			if ($seo->getArchiveNoindex()) {
				if ($headBlock = $this->getLayout()->getBlock('head')) {
					$headBlock->setRobots('noindex,follow');
				}
			}
		}

		$this->renderLayout();
	}
	
	/**
	 * Loads an archive model based on the URI
	 *
	 * @return false|Fishpig_Wordpress_Model_Archive
	 */
	protected function _initArchive()
	{
		if (($archive = Mage::registry('wordpress_archive')) !== null) {
			return $archive;
		}
		
		if ($archive = Mage::getModel('wordpress/archive')->load(Mage::helper('wordpress/router')->getBlogUri())) {
			if ($archive->hasPosts()) {
				Mage::register('wordpress_archive', $archive);
				return $archive;
			}
		}

		return false;
	}
}
