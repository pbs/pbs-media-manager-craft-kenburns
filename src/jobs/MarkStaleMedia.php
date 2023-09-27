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
	use craft\helpers\Json;
	use craft\queue\BaseJob;
	use craft\elements\Entry;
	use craft\elements\Asset;
	use craft\elements\Tag;
	use craft\helpers\ElementHelper;
	use craft\helpers\Assets as AssetHelper;
	
	use DateTime;
	use GuzzleHttp\Exception\ClientException;
	use GuzzleHttp\Exception\GuzzleException;
	use GuzzleHttp\Exception\RequestException;
	use papertiger\mediamanager\MediaManager;
	use papertiger\mediamanager\helpers\SettingsHelper;
	use papertiger\mediamanager\services\Api;
	
	class MarkStaleMedia extends BaseJob
	{
		
		// Public Properties
		// =========================================================================
		
		/**
		 * @var int
		 */
		public $entryId;
		
		// Public Methods
		// =========================================================================
		
		public function execute( $queue )
		{
			$entry = Entry::find()->id($this->entryId)->one();
			
			if(!$entry) {
				return;
			}
			
			$markForDeletion = 0;
			// hit PBS API to check if there is data for this entry's api key
			$client = Craft::createGuzzleClient();
			$baseUrl = MediaManager::$plugin->api->getApiBaseUrl();
			$apiAuth = MediaManager::$plugin->api->getApiAuth();
			
			try {
				$data = $client->get($baseUrl . "assets/" . $entry->mediaManagerId . "?platform-slug=bento&platform-slug=partnerplayer", $apiAuth);
				
				$asset = Json::decode($data->getBody(), false);
				if(isset($asset->data)){
					if(!isset($asset->data->attributes->availabilities->public, $asset->data->attributes->availabilities->all_members)){
						return;
					}
					
					if($asset->data->attributes->availabilities->public->start === null && $asset->data->attributes->availabilities->all_members->start === null){
						$markForDeletion = 1;
					}
				}
			} catch (ClientException $e) {
				if ($e->getCode() === 404) {
					$markForDeletion = 1;
				}
			} catch (RequestException $e) {
				Craft::error($e->getMessage(), __METHOD__);
				return;
			}
			
			// if data object is empty, return
			Craft::warning("Marking entry ID {$entry->id} for deletion.", __METHOD__);
			$entry->setFieldValue('markedForDeletion', $markForDeletion);
			$entry->setFieldValue('lastSynced', (new DateTime()));
			Craft::$app->getElements()->saveElement($entry);
		}
		
		// Protected Methods
		// =========================================================================
		
		protected function defaultDescription(): string
		{
			return Craft::t( 'mediamanager', "Marking entry {$this->entryId} for deletion." );
		}
		
		// Private Methods
		// =========================================================================
	}
