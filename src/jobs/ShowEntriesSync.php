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
use craft\db\Query;
use craft\errors\ElementNotFoundException;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\queue\BaseJob;
use craft\elements\Entry;
use craft\elements\Asset;
use craft\elements\Tag;
use craft\helpers\FileHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Assets as AssetHelper;

use DateTime;
use papertiger\mediamanager\MediaManager;
use papertiger\mediamanager\helpers\SettingsHelper;
use papertiger\mediamanager\helpers\SynchronizeHelper;
use yii\base\Exception;


class ShowEntriesSync extends BaseJob
{

    // Private Properties
    // =========================================================================

    protected $apiBaseUrl;
    protected $sectionId;
    protected $typeId;
    protected $authorId;
    protected $authorUsername;
    protected $mediaFolderId;
    protected $logProcess;
    protected $logFile;


    // Public Properties
    // =========================================================================

    public $title;
    public $auth;
    public $apiKey;

		/**
		 * @var array|string
		 */
		public $fieldsToSync = '*';


    // Private Properties
    // =========================================================================

    private $dateWithMs = 'Y-m-d\TH:i:s.uP';

		private $_availabilityProcessed = false;
		private $availabilityPassport = 0;
		private $availabilityPublic = 0;

    // Public Methods
    // =========================================================================

	/**
	 * @throws Exception
	 * @throws \Throwable
	 * @throws ElementNotFoundException
	 */
	public function execute( $queue ): void
    {
        $this->apiBaseUrl     = SettingsHelper::get( 'apiBaseUrl' );
        $this->sectionId      = SynchronizeHelper::getShowSectionId(); // SECTION_ID
        $this->typeId         = SynchronizeHelper::getShowSectionTypeId(); // TYPE_ID
        $this->authorId       = SynchronizeHelper::getAuthorId(); // AUTHOR_ID
        $this->authorUsername = SynchronizeHelper::getAuthorUsername(); // AUTHOR_USERNAME
        $this->mediaFolderId  = SynchronizeHelper::getAssetFolderId(); // MEDIA_FOLDER_ID
        $this->logProcess     = 1; // LOG_PROCESS
        $this->logFile        = '@storage/logs/sync.log'; // LOG_FILE

        $url      = $this->generateAPIUrl( $this->apiKey );
        $showEntry = $this->fetchShowEntry($url, '');

        $showAttributes = $showEntry->data->attributes;

        $existingEntry       = $this->findExistingShowEntry( $showEntry->data->id );
        $isNew = !$existingEntry;
        $entry               = $this->chooseOrCreateShowEntry( $showAttributes->title, $existingEntry );

        // Set default field Values
        $defaultFields = [];

        // Set field values based on API Column Fields on settings
        $apiColumnFields = SettingsHelper::get( 'showApiColumnFields' );

        foreach( $apiColumnFields as $apiColumnField ) {

            $apiField = $apiColumnField[ 0 ];

		        // ensure the field to be updated from MM Settings is included in the fieldsToSync array
		        if(!$isNew && ($this->fieldsToSync !== '*' && !in_array($apiField, $this->fieldsToSync))) {
			        continue;
		        }

            switch( $apiField ) {
                case 'show_images':

                    $imagesHandle = SynchronizeHelper::getShowImagesField();
                    $fieldRule    = SynchronizeHelper::getApiFieldRule( $apiField, 'showApiColumnFields' );

                    if( isset( $showAttributes->images ) && is_array( $showAttributes->images ) ) {

                        $assets = [];

                        foreach( $showAttributes->images as $image ) {

                            if( $fieldRule ) {

                                preg_match( '/'. $fieldRule .'/', $image->profile, $matches );

                                if( count( $matches ) ) {

                                    $asset = $this->createOrUpdateImage( $showAttributes->title, $image,  $image->profile );

                                    if( $asset && isset( $asset->id ) ) {
                                        $assets[] = $asset->id;
                                    }
                                }

                                continue;
                            }

                            $asset = $this->createOrUpdateImage( $showAttributes->title, $image, $image->profile );

                            if( $asset && isset( $asset->id ) ) {
                                $assets[] = $asset->id;
                            }
                        }

                        if( $assets ) {
                            $defaultFields[ $imagesHandle ] = $assets;
                        }
                    }

                break;
                case 'show_address':
                    if( isset( $showAttributes->slug ) ) {
                        $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = 'https://pbs.org/show/' . $showAttributes->slug;
                    }
                break;
                case 'show_last_synced':
                    $defaultFields[ SynchronizeHelper::getShowLastSyncedField() ] = new \DateTime( 'now' );
                break;
                case 'show_media_manager_id':
                    $defaultFields[ SynchronizeHelper::getShowMediaManagerIdField() ] = $showEntry->data->id;
                break;
                  case 'show_site_url':
                    if(isset( $showAttributes->links) && is_array($showAttributes->links)){
                        foreach($showAttributes->links as $link) {
                            if($link->profile == 'producer') {
                                $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $link->value;
                            }
                        }
                    }
                    break;
                  case 'available_for_purchase':
                    $availableForPurchase = 0;
                    $purchasablePlatforms = ['itunes', 'amazon', 'buy-dvd', 'roku', 'apple-tv', 'ios'];
                    if(isset( $showAttributes->links) && is_array($showAttributes->links)){
                        foreach($showAttributes->links as $link) {
                            if($availableForPurchase || !in_array($link->profile, $purchasablePlatforms)){
                                continue;
                            }
                            if(in_array($link->profile, $purchasablePlatforms)){
                                $availableForPurchase = 1;
                            }
                        }
                    $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $availableForPurchase;
                    }
                break;
                case 'description_long':
                    // Only if new entry add description
                    if( !$existingEntry ) {
                        $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $showAttributes->description_long;
                    }
                break;
                case 'description_short':
                    // Only if new entry add description
                    if( !$existingEntry ) {
                        $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $showAttributes->description_short;
                    }
                break;
                case 'premiered_on':
                    if( $showAttributes->premiered_on != null) {
                        $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = new \DateTime( $showAttributes->premiered_on );
                    }
                break;
                case 'episodes_count':

                    // legacy count
                    $episodesCount = $showAttributes->episodes_count;
                    // see here for context: https://github.com/pbs/pbs-media-manager-craft-plugin/issues/11#issuecomment-1850786717

                    $assetsData = $this->fetchShowEntry($this->apiBaseUrl . 'assets', 'data',
                        ['show-id' => $this->apiKey, 'type' => 'full_length', 'parent-type' => 'episode']);

                    if($assetsData){
                        $episodesCount = count($assetsData);
                    }

                    $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $episodesCount;

                break;
                case 'featured_preview':

                    $mediaManagerEntries = [];

                    $mediaManagerEntry = Entry::find()->section('media')->mediaManagerId($showAttributes->featured_preview)->one();

                    if( $mediaManagerEntry ){
                        $mediaManagerEntries[] = $mediaManagerEntry->id;
                        $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $mediaManagerEntries;
                    }

                break;
                case 'links':

                    if( isset( $showAttributes->links ) && is_array( $showAttributes->links ) ) {

                        $createTable = [];
                        $count = 0;

                        foreach( $showAttributes->links as $link ) {
                            $createTable[$count]['linkValue'] = $link->value;
                            $createTable[$count]['linkProfile'] = $link->profile;
                            $createTable[$count]['linkUpdatedAt'] = new \DateTime( $link->updated_at );
                            $count++;
                        }

                        $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $createTable;

                    }

                break;
	              case 'stream_with_passport':
                    $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $this->getShowAvailability('availabilityPassport', $showEntry);
                    break;

		            case 'available_to_public':
                    $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $this->getShowAvailability('availabilityPublic', $showEntry);
                    break;

                default:
                    $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $showAttributes->{ $apiField };
                break;
            }
        }

        // Set field values and properties
        $entry->setFieldValues( $defaultFields );
        $entry->enabled = true;

        Craft::$app->getElements()->saveElement( $entry );
        $this->setProgress( $queue, 1 );
    }

    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): string
    {
        return Craft::t( 'mediamanager', 'Syncing show entry for ' . $this->title );
    }

    // Private Methods
    // =========================================================================

    private function log( $message )
    {
        if( $this->logProcess ) {
            $log = date( 'Y-m-d H:i:s' ) .' '. $message . "\n";
            FileHelper::writeToFile( Craft::getAlias( $this->logFile ), $log, [ 'append' => true ] );
        }
    }

    private function generateAPIUrl( $apiKey )
    {
        return $this->apiBaseUrl . 'shows/'. $apiKey . '/?platform-slug=bento&platform-slug=partnerplayer';
    }

    private function fetchShowEntry($url, $attribute = 'data', $params = [])
    {
        $client   = Craft::createGuzzleClient();
        $options = $this->auth;

        if($params){
          $options = array_merge($options, ['query' => $params]);
        }

        $response = $client->get($url, $options);
        $response = Json::decode($response->getBody(), false);

        if($attribute){
            return $response->{$attribute};
        }

        return $response;
    }

	/**
	 * @throws \Exception
	 */
	private function getShowAvailability(string $attribute, $showEntry): int
		{
			// Don't run this twice
			if($this->_availabilityProcessed){
				return $this->$attribute;
			}

			// There is probably a much cleaner / more straightforward way of doing this
			// we need to loops through all assets of the show's first season to see if any of them are available for streaming
			// If any episode in season 1 is available to passport members, then we can say the show is in Passport. If any episode within Season 1 is available to the Public, then we can also say it is "available to everyone".
			// logic per https://github.com/pbs/pbs-media-manager-craft-plugin/issues/10#issuecomment-1791521258

			$availableOnPassport = 0;
			$availableToPublic = 0;

      // get show's seasons
      $seasonsUrl = $showEntry->links->seasons;
      $seasonData = $this->fetchShowEntry($seasonsUrl);

			if(!$seasonData){
				 Craft::error('No seasons found for show ' . $showEntry->data->id, __METHOD__);
				return 0;
			}

			// get first season's episodes
			$firstSeasonIndex = count($seasonData) - 1;
			$episodesUrl = $seasonData[$firstSeasonIndex]->links->episodes;
			$episodesData = $this->fetchShowEntry($episodesUrl);

			if(!$episodesData){
				Craft::error('No episodes found for season ' . $seasonData[$firstSeasonIndex]->id, __METHOD__);
				return 0;
			}

			foreach($episodesData as $episode){
				// if we've determined that both are true, we can stop looping
				if($availableOnPassport && $availableToPublic){
					continue;
				}

				$episodeAssetsUrl = $episode->links->assets;
				$episodeAssets = $this->fetchShowEntry($episodeAssetsUrl, 'data', ['type' => 'full_length']);

				if(!$episodeAssets) {
					continue;
				}

				foreach($episodeAssets as $asset){
					if($availableOnPassport && $availableToPublic){
						continue;
					}

					$publicEndDate = new DateTime($asset->attributes->availabilities->public->end) ?? null;
					$passportEndDate = new DateTime($asset->attributes->availabilities->all_members->end) ?? null;

          if($publicEndDate instanceof DateTime){
						$availableToPublic = DateTimeHelper::isInThePast($publicEndDate) ? 0 : 1;
					}

					if($passportEndDate instanceof DateTime){
						$availableOnPassport = DateTimeHelper::isInThePast($passportEndDate) ? 0 : 1;
					}
				}
			}

			$this->availabilityPassport = $availableOnPassport;
			$this->availabilityPublic = $availableToPublic;
			$this->_availabilityProcessed = true;

			return $this->$attribute;
		}

    private function findExistingShowEntry( $mediaManagerId )
    {
        // Find existing media
        $entry = Entry::find()
                    ->{ SynchronizeHelper::getShowMediaManagerIdField() }( $mediaManagerId )
                    ->sectionId( $this->sectionId )
                    ->status( null )
                    ->one();

        return ( $entry ) ? $entry : false;
    }

    private function chooseOrCreateShowEntry( $title, $entry )
    {

        if( !$entry ) {

            $apiUserID = $this->authorId;

            if( $this->authorUsername ) {
                $user = Craft::$app->users->getUserByUsernameOrEmail( $this->authorUsername );

                if( $user ) {
                    $apiUserID = $user->id;
                }
            }

            $entry            = new Entry();
            $entry->sectionId = $this->sectionId;
            $entry->typeId    = $this->typeId;
            $entry->authorId  = $apiUserID;
            $entry->title     = $title;
        }

        return $entry;
    }

    private function getMediaFolder()
    {
        $assets = Craft::$app->getAssets();

        return $assets->findFolder( [ 'id' => $this->mediaFolderId ] );
    }

    private function copyImageToServer( $url )
    {
        $image     = file_get_contents( $url );
        $extension = pathinfo( $url )[ 'extension' ];
        $localPath = AssetHelper::tempFilePath( $extension );

        file_put_contents( $localPath, $image );

        return $localPath;
    }

    private function createOrUpdateImage( $entryTitle, $imageInfo, $profile )
    {
        $imageUrl  = $imageInfo->image;
        $extension = pathinfo( $imageUrl )[ 'extension' ];
        $slug      = ElementHelper::normalizeSlug( $entryTitle );
        $filename  = $slug . '-' . md5( ElementHelper::normalizeSlug( $imageUrl ) ) . '.' . $extension;
        $asset     = Asset::findOne( [ 'filename' => $filename ] );

        if( $asset ) {


            Craft::$app->elements->deleteElement($asset);

            /*
            if( $asset->mmAssetProfile ) {

                return $asset;

            } else {

                $asset->setFieldValue( 'mmAssetProfile', $profile);
                Craft::$app->getElements()->saveElement( $asset );

                return $asset;

            }
            */

        }

        return $this->createImageAsset( $imageUrl, $filename, $profile );
    }

    private function createImageAsset( $imageUrl, $filename, $profile )
    {
        $folder    = $this->getMediaFolder();
        $localPath = $this->copyImageToServer( $imageUrl );

        $asset               = new Asset();
        $asset->tempFilePath = $localPath;
        $asset->filename     = $filename;
        $asset->newFolderId  = $folder->id;
        $asset->volumeId     = $folder->volumeId;
        $asset->avoidFilenameConflicts = true;

        $asset->setScenario( Asset::SCENARIO_CREATE );

        // HINT: May no longer required - Plz double check
        //$asset->setFieldValues( $defaultFields );

        if( $profile ) {

            if( Craft::$app->getFields()->getFieldByHandle( 'mmAssetProfile' ) ) {
                $asset->setFieldValue( 'mmAssetProfile', $profile);
            }
        }

        Craft::$app->getElements()->saveElement( $asset );

        return $asset;
    }
}
