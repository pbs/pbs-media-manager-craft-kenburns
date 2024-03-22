<?php
	/**
	 * Media Manager
	 *
	 * @package       PaperTiger:MediaManager
	 * @author        Paper Tiger
	 * @copyright     Copyright (c) 2020 Paper Tiger
	 * @link          https://www.papertiger.com/
	 */
	
	namespace papertiger\mediamanager\jobs;
	
	use Craft;
	use craft\helpers\Db;
	use craft\queue\BaseJob;
	use craft\elements\Entry;
	use craft\elements\Asset;
	use craft\elements\Tag;
	use craft\helpers\ElementHelper;
	use craft\helpers\Assets as AssetHelper;
	
	use DateTime;
	use papertiger\mediamanager\MediaManager;
	use papertiger\mediamanager\helpers\SettingsHelper;
	
	class ShowSync extends BaseJob
	{
		
		// Public Properties
		// =========================================================================
		
		/**
		 * @var int
		 */
		public $showId;
		
		/**
		 * @var int
		 */
		public $scheduledSync;
		
		/**
		 * @var bool
		 */
		public $regenerateThumbnails;
		
		public $_show;
		
		// Public Methods
		// =========================================================================
		
		public function execute( $queue ): void
		{
			$show = $this->_getShow();
			$scheduledSync = MediaManager::getInstance()->scheduledSync->getScheduledSyncById($this->scheduledSync);
			$mediaFieldsToSync = $scheduledSync->getMediaFieldsToSync();
			$showFieldsToSync = $scheduledSync->getShowFieldsToSync();
			
			MediaManager::$plugin->api->synchronizeShow($show, $this->regenerateThumbnails, $mediaFieldsToSync);
			MediaManager::$plugin->api->synchronizeShowEntries([$show], $showFieldsToSync);
		}
		
		// Protected Methods
		// =========================================================================
		
		protected function defaultDescription(): string
		{
			$show = $this->_getShow();
			return Craft::t( 'mediamanager', "Queuing sync for {$show->name}" );
		}
		
		private function _getShow(): object
		{
			if($this->_show){
				return $this->_show;
			}
			
			$this->_show = (object)MediaManager::getInstance()->show->getShow( $this->showId );
			
			return $this->_show;
		}
	}
