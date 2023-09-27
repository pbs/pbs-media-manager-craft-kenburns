<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager\services;

use Craft;
use craft\helpers\App;
use craft\base\Component;
use craft\base\Element;
use craft\helpers\UrlHelper;
use craft\elements\Entry;
use craft\elements\db\ElementQuery;
use GuzzleHttp\Exception\RequestException;
use yii\base\Exception;

use papertiger\mediamanager\MediaManager;
use papertiger\mediamanager\jobs\MediaSync;
use papertiger\mediamanager\jobs\MediaClean;
use papertiger\mediamanager\jobs\ShowEntriesSync;
use papertiger\mediamanager\helpers\SettingsHelper;
use papertiger\mediamanager\helpers\SynchronizeHelper;

class Api extends Component
{
    // Private Properties
    // =========================================================================
    
    protected static $sectionMediaHandle;
    protected static $sectionUsedMediaHandle;
    protected static $apiBaseUrl;
    protected static $apiAuth;


    // Public Methods
    // =========================================================================

    public function __construct()
    {
        self::$sectionMediaHandle     = SettingsHelper::get( 'mediaSection' );
        self::$sectionUsedMediaHandle = SettingsHelper::get( 'mediaUsedBySection' );

        $pbsApiUsername = '';
        $pbsApiPassword = '';

        if( method_exists( 'Craft', 'parseEnv' ) ) {

            $pbsApiUsername = Craft::parseEnv( '$PBS_API_BASIC_AUTH_USERNAME' );
            $pbsApiPassword = Craft::parseEnv( '$PBS_API_BASIC_AUTH_PASSWORD' );
        }

        if( method_exists( 'App', 'parseEnv' ) ) {

            $pbsApiUsername = App::parseEnv( '$PBS_API_BASIC_AUTH_USERNAME' );
            $pbsApiPassword = App::parseEnv( '$PBS_API_BASIC_AUTH_PASSWORD' );
        }

        self::$apiBaseUrl = SettingsHelper::get( 'apiBaseUrl' );
        self::$apiAuth    = [
            'auth' => [
                $pbsApiUsername,
                $pbsApiPassword,
            ]
        ];
    }

    public function validateApiKey( $apiKey, $apiType )
    {

        if( !$apiKey ) {
            return false;
        }

        $httpClient = Craft::createGuzzleClient();

        switch( $apiType ) {
            case 'show':
                $apiUrl = self::$apiBaseUrl . 'shows/' . $apiKey . '?platform-slug=bento&platform-slug=partnerplayer';
            break;
            case 'asset':
                $apiUrl = self::$apiBaseUrl . 'assets/' . $apiKey . '?platform-slug=bento&platform-slug=partnerplayer';
            break;
        }

        try {

            $response = $httpClient->get( $apiUrl, self::$apiAuth );
            return true;

        } catch( RequestException $e ) {
            return false;
        }

        return false;
    }

    public function synchronizeShow( $show, $siteId, $forceRegenerateThumbnail )
    {

        if( !$show->apiKey ) {
            return false;
        }

        $this->runSynchronizeShow( $show, $forceRegenerateThumbnail );

        return true;
    }

    public function synchronizeSingle( $apiKey, $siteId, $forceRegenerateThumbnail )
    {

        if( !$apiKey || !$siteId ) {
            return false;
        }

        Craft::$app->queue->push( new MediaSync([
            'siteId'         => $siteId,
            'singleAsset'    => true,
            'singleAssetKey' => $apiKey,
            'title'          => 'Single media asset',
            'auth'           => self::$apiAuth,
            'forceRegenerateThumbnail' => $forceRegenerateThumbnail
        ]));

        return true;
    }

    public function synchronizeAll( $shows, $forceRegenerateThumbnail )
    {
        foreach( $shows as $show ) {
            
            if( $show->apiKey ) {
                $this->runSynchronizeShow( $show, $forceRegenerateThumbnail );
            }
        }

        return true;
    }

    public function synchronizeShowEntries( $shows )
    {
        foreach( $shows as $show ) {
            
            if( $show->apiKey ) {

                Craft::$app->queue->push( new ShowEntriesSync([

                    'apiKey'      => $show->apiKey,
                    'title'       => $show->name . ' (Show)',
                    'auth'        => self::$apiAuth
                ]));
            }
        }

        return true;
    }
		
		public function getApiBaseUrl()
		{
			return self::$apiBaseUrl;
		}
		
		public function getApiAuth()
		{
			return self::$apiAuth;
		}

    public function runClean()
    {

        try {
            
            // Check used media entries from pages
            $usedMedia = [];
            $sites = Craft::$app->sites->getAllSites();

            foreach( $sites as $site ) {

                $pages = Entry::find()
                            ->section( self::$sectionUsedMediaHandle )
                            ->limit( null )
                            ->site( $site[ 'handle' ] )
                            ->anyStatus()
                            ->all();

                foreach( $pages as $page ) {

                    foreach( $page[ 'pageBuilder' ]->all() as $parentBlock ) {
                        
                        foreach( $parentBlock[ 'row' ]->all() as $component ) {

                            switch( $component[ 'type' ] ) {

                                case 'videoPlayer':

                                    foreach( $component[ 'relatedMedia' ]->anyStatus()->all() as $entry  ) {

                                        array_push( $usedMedia, [
                                            'id' => $entry[ 'id' ],
                                            'title' => $entry[ 'title' ],
                                            'entry' => $page[ 'title' ],
                                            'site' => $site[ 'name' ]
                                        ]);
                                    }

                                    foreach( $component[ 'selectedMedia' ]->anyStatus()->all() as $entry ) {
                                        
                                        array_push( $usedMedia, [
                                            'id' => $entry[ 'id' ],
                                            'title' => $entry[ 'title' ],
                                            'entry' => $page[ 'title' ],
                                            'site' => $site[ 'name' ]
                                        ]);
                                    }
                                break;

                                case 'mediaCallout':

                                    foreach( $component[ 'media' ]->anyStatus()->all() as $entry  ) {

                                        array_push( $usedMedia, [
                                            'id' => $entry[ 'id' ],
                                            'title' => $entry[ 'title' ],
                                            'entry' => $page[ 'title' ],
                                            'site' => $site[ 'name' ]
                                        ]);
                                    }
                                break;
                            }
                        }
                    }
                }
            }

            // Check duplicate media entries
            $duplicateCounter = [];
            $entries = Entry::find()
                            ->section( self::$sectionMediaHandle )
                            ->limit( null )
                            ->anyStatus()
                            ->all();

            foreach( $entries as $entry ) {

                $mediaManagerId = $entry[ 'mediaManagerId' ];
                
                if( array_key_exists( $mediaManagerId, $duplicateCounter ) ) {
                    $duplicateCounter[ $mediaManagerId ]++;
                } else {
                    $duplicateCounter[ $mediaManagerId ] = 1;
                }
            }

            // Filter unused and duplicates
            $deleteEntries = [];
            $keepEntries   = [];

            foreach( $entries as $entry ) {

                if( $entry[ 'id' ] ) {
                    $used = [];

                    foreach( $usedMedia as $medium ) {
                        if( $medium[ 'id' ] == $entry[ 'id' ] ) {
                            array_push( $used, $medium );
                        }
                    }

                    $mediaManagerId = $entry[ 'mediaManagerId' ];

                    if( !count( $used ) && $duplicateCounter[ $mediaManagerId ] > 1 ) {
                        array_push( $deleteEntries, [
                            'id' => $entry[ 'id' ],
                            'title' => $entry[ 'title' ],
                            'mediaManagerId' => $mediaManagerId
                        ]);
                    } else {
                        array_push( $keepEntries, [
                            'id' => $entry[ 'id' ],
                            'title' => $entry[ 'title' ],
                            'mediaManagerId' => $mediaManagerId
                        ]);
                    }
                }
            }

            // Validate once more
            foreach( $deleteEntries as $key => $deleteEntry ) {

                if( array_search( $deleteEntry[ 'mediaManagerId' ], array_column( $keepEntries, 'mediaManagerId' ) ) === FALSE ) {

                    array_push( $keepEntries, $deleteEntries[ $key ] );
                    unset( $deleteEntries[ $key ] );
                }
            }

            // Clean it in the background
            $count = 0;
            $total = count( $deleteEntries );

            foreach( $deleteEntries as $deleteEntry ) {

                $count++;

                Craft::$app->queue->push( new MediaClean([

                    'title'   => $deleteEntry[ 'title' ],
                    'entryId' => $deleteEntry[ 'id' ],
                    'count'   => $count,
                    'total'   => $total
                ]));
            }

            return $total;
            

        } catch( Exception $e ) {
            return false;
        }
    }

    // Private Methods
    // =========================================================================
    
    private function runSynchronizeShow( $show, $forceRegenerateThumbnail )
    {
        Craft::$app->queue->push( new MediaSync([

            'siteId'      => $show->siteId,
            'singleAsset' => false,
            'assetType'   => 'show',
            'apiKey'      => $show->apiKey,
            'title'       => $show->name . ' (Show)',
            'auth'        => self::$apiAuth,
            'forceRegenerateThumbnail' => $forceRegenerateThumbnail
        ]));

        $seasons = $this->getSeasonsOfShow( $show->apiKey );

        foreach( $seasons->data as $season ) {

            sleep( 3 );

            Craft::$app->queue->push( new MediaSync([

                'siteId'      => $show->siteId,
                'singleAsset' => false,
                'assetType'   => 'season',
                'apiKey'      => $season->id,
                'title'       => $show->name . ' (Season ' . $season->attributes->ordinal . ')',
                'auth'        => self::$apiAuth,
                'forceRegenerateThumbnail' => $forceRegenerateThumbnail
            ]));

            $episodes = $this->getEpisodesOfShow( $season->id );

            foreach( $episodes->data as $episode ) {

                sleep( 3 );

                Craft::$app->queue->push( new MediaSync([

                    'siteId'      => $show->siteId,
                    'singleAsset' => false,
                    'assetType'   => 'episode',
                    'apiKey'      => $episode->id,
                    'title'       => $episode->attributes->title . ' (Episode)',
                    'auth'        => self::$apiAuth,
                    'forceRegenerateThumbnail' => $forceRegenerateThumbnail
                ]));
            }
        }

        $specials = $this->getSpecialsOfShow( $show->apiKey );

        foreach( $specials->data as $special ) {

            sleep( 3 );

            Craft::$app->queue->push( new MediaSync([

                'siteId'      => $show->siteId,
                'singleAsset' => false,
                'assetType'   => 'special',
                'apiKey'      => $special->id,
                'title'       => $special->attributes->title . ' (Special)',
                'auth'        => self::$apiAuth,
                'forceRegenerateThumbnail' => $forceRegenerateThumbnail
            ]));
        }
    }

    private function getSeasonsOfShow( $id )
    {
        $client = Craft::createGuzzleClient();

        $response = $client->get( self::$apiBaseUrl . 'shows/' . $id . '/seasons?platform-slug=bento&platform-slug=partnerplayer', self::$apiAuth );

        return json_decode( $response->getBody() );
    }

    private function getEpisodesOfShow( $id )
    {
        $client = Craft::createGuzzleClient();

        $response = $client->get( self::$apiBaseUrl . 'seasons/' . $id . '/episodes?platform-slug=bento&platform-slug=partnerplayer', self::$apiAuth );

        return json_decode( $response->getBody() );
    }

    private function getSpecialsOfShow( $id )
    {
        $client = Craft::createGuzzleClient();

        $response = $client->get(self::$apiBaseUrl . 'shows/' . $id . '/specials?platform-slug=bento&platform-slug=partnerplayer', self::$apiAuth );

        return json_decode( $response->getBody() );
    }
}
