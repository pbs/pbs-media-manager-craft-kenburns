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
use craft\queue\BaseJob;
use craft\elements\Entry;
use craft\elements\Asset;
use craft\elements\Tag;
use craft\helpers\FileHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Assets as AssetHelper;

use papertiger\mediamanager\MediaManager;
use papertiger\mediamanager\helpers\SettingsHelper;
use papertiger\mediamanager\helpers\SynchronizeHelper;


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


    // Public Methods
    // =========================================================================

    public function execute( $queue )
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
        $showEntry = $this->fetchShowEntry( $url );

        $showAttributes = $showEntry->attributes;

        $existingEntry       = $this->findExistingShowEntry( $showEntry->id );
        $entry               = $this->chooseOrCreateShowEntry( $showAttributes->title, $existingEntry );

        // Set default field Values
        $defaultFields = [];

        // Set field values based on API Column Fields on settings
        $apiColumnFields = SettingsHelper::get( 'showApiColumnFields' );

        foreach( $apiColumnFields as $apiColumnField ) {
            
            $apiField = $apiColumnField[ 0 ];
	
		        // ensure the field to be updated from MM Settings is included in the fieldsToSync array
		        if($this->fieldsToSync !== '*' && !in_array($apiField, $this->fieldsToSync) ) {
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
                    $defaultFields[ SynchronizeHelper::getShowMediaManagerIdField() ] = $showEntry->id;
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
                    // Retain Episodes Count for existing entries
                    if( !$existingEntry ) {
                        $defaultFields[ SynchronizeHelper::getApiField( $apiField, 'showApiColumnFields' ) ] = $showAttributes->episodes_count;
                    }
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

    private function fetchShowEntry( $url )
    {
        $client   = Craft::createGuzzleClient();
        $response = $client->get( $url, $this->auth );
        $response = json_decode( $response->getBody() );

        return $response->data;
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
        $slug      = ElementHelper::createSlug( $entryTitle );
        $filename  = $slug . '-' . md5( ElementHelper::createSlug( $imageUrl ) ) . '.' . $extension;
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
