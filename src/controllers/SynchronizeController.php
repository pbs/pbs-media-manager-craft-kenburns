<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager\controllers;

use Craft;
use craft\base\Element;
use craft\helpers\UrlHelper;
use craft\elements\Entry;
use craft\elements\db\ElementQuery;
use craft\web\Controller;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

use papertiger\mediamanager\MediaManager;

class SynchronizeController extends Controller
{
    // Protected Properties
    // =========================================================================
    
    protected const SYNCHRONIZE_TEMPLATE_PATH = 'mediamanager/synchronize';
    protected const CLEAN_TEMPLATE_PATH       = 'mediamanager/clean';
    protected array|int|bool $allowAnonymous                 = [ 'index', 'all', 'single', 'synchronize' ];


    // Public Methods
    // =========================================================================

    public function actionIndex( $entryId = null )
    {

        $shows = MediaManager::getInstance()->show->getShow();
        $activeShow = ( $entryId ) ? MediaManager::getInstance()->show->getShow( $entryId ) : null;

        if( !$activeShow ) {
            $activeShow = MediaManager::getInstance()->show->getShow( false, true );
        }

        return $this->renderTemplate(
            self::SYNCHRONIZE_TEMPLATE_PATH,
            [
                'activeShow' => $activeShow,
                'shows' => $shows
            ]
        );
    }

    public function actionAll()
    {

        $shows = MediaManager::getInstance()->show->getShow();

        return $this->renderTemplate(
            self::SYNCHRONIZE_TEMPLATE_PATH,
            [
                'activeShow' => [
                    'id' => 'all',
                    'name' => 'All',
                    'apiKey' => null,
                    'siteId' => 1,
                ],
                'shows' => $shows
            ]
        );
    }

    public function actionSingle()
    {

        $shows = MediaManager::getInstance()->show->getShow();

        return $this->renderTemplate(
            self::SYNCHRONIZE_TEMPLATE_PATH,
            [
                'activeShow' => [
                    'id' => 'single',
                    'name' => 'Single',
                    'apiKey' => null,
                    'siteId' => 1,
                ],
                'shows' => $shows
            ]
        );
    }

    public function actionShowEntries()
    {

        $shows = MediaManager::getInstance()->show->getShow();

        return $this->renderTemplate(
            self::SYNCHRONIZE_TEMPLATE_PATH,
            [
                'activeShow' => [
                    'id' => 'show-entries',
                    'name' => 'Show Entries',
                    'apiKey' => null,
                    'siteId' => 1,
                ],
                'shows' => $shows
            ]
        );
    }


    public function actionSynchronizeShow()
    {
        $request = Craft::$app->getRequest();
        $showId  = $request->getBodyParam( 'showId' );
        $siteId  = $request->getBodyParam( 'siteId' );
        $forceRegenerateThumbnail  = $request->getBodyParam( 'forceRegenerateThumbnail' );
				$fieldsToSync = $request->getBodyParam('fieldsToSync');
				
        if( !$showId ) {

            return $this->asJson([
                'success' => false,
                'errors' => [ 'Show ID is required' ],
            ]);
        }

        $show = MediaManager::getInstance()->show->getShow( $showId );

        if( !is_object( $show ) ) {
            $show = ( object ) $show;
        }

        if( property_exists( $show, 'getErrors' ) && $show->getErrors() ) {

            return $this->asJson([
                'success' => false,
                'errors' => $show->getErrors(),
            ]);
        }

        $synchronize = MediaManager::getInstance()->api->synchronizeShow( $show, $forceRegenerateThumbnail, $fieldsToSync );

        return $this->asJson([
            'success' => true
        ]);
    }


    public function actionSynchronizeSingle()
    {
        $request = Craft::$app->getRequest();
        $apiKey  = $request->getBodyParam( 'apiKey' );
        $siteId  = $request->getBodyParam( 'siteId' );
        $forceRegenerateThumbnail = $request->getBodyParam( 'forceRegenerateThumbnail' );
				$fieldsToSync = $request->getBodyParam('fieldsToSync');

        if( !$apiKey || !$siteId ) {

            return $this->asJson([
                'success' => false,
                'errors' => [ 'API Key and Site ID are required' ],
            ]);
        }

        // Validate API Key first
        if( $apiKey && $siteId ) {

            if( !MediaManager::getInstance()->api->validateApiKey( $apiKey, 'asset' ) ) {

                return $this->asJson([
                    'success' => false,
                    'errors' => [ 'Invalid API Key for Media Asset' ],
                ]);
            }
        }

        $synchronize = MediaManager::getInstance()->api->synchronizeSingle( $apiKey, $siteId, $forceRegenerateThumbnail, $fieldsToSync );

        return $this->asJson([
            'success' => true
        ]);
    }

    public function actionSynchronizeAll()
    {
        $shows = MediaManager::getInstance()->show->getShow();
        $validatedShows = [];
        $request = Craft::$app->getRequest();
	      $forceRegenerateThumbnail = $request->getBodyParam( 'forceRegenerateThumbnail' );
				$fieldsToSync = $request->getBodyParam('fieldsToSync');
	      
        foreach( $shows as $show ) {
            
            if( $show->apiKey && $show->name ) {
                
                $show[ 'siteId' ] = json_decode( $show[ 'siteId' ] );
                array_push( $validatedShows, $show );
            }
        }

        if( !count( $validatedShows ) ) {

            return $this->asJson([
                'success' => false,
                'errors' => [ 'No valid Show API Key registered, please register one.' ],
            ]);
        }

        $synchronize = MediaManager::getInstance()->api->synchronizeAll( $validatedShows, $forceRegenerateThumbnail, $fieldsToSync );

        return $this->asJson([
            'success' => true
        ]);
    }

    public function actionSynchronizeShowEntries()
    {
        $shows = MediaManager::getInstance()->show->getShow();
        $validatedShows = [];
        $request = Craft::$app->getRequest();

        foreach( $shows as $show ) {
            
            if( $show->apiKey && $show->name ) {
                
                $show[ 'siteId' ] = json_decode( $show[ 'siteId' ] );
                array_push( $validatedShows, $show );
            }
        }

        if( !count( $validatedShows ) ) {

            return $this->asJson([
                'success' => false,
                'errors' => [ 'No valid Show API Key registered, please register one.' ],
            ]);
        }
	
	      $fieldsToSync = $request->getBodyParam('fieldsToSync', []);
				
        $synchronize = MediaManager::getInstance()->api->synchronizeShowEntries( $validatedShows, $fieldsToSync);

        return $this->asJson([
            'success' => true
        ]);
    }


    public function actionClean()
    {

        $shows = MediaManager::getInstance()->show->getShow();

        return $this->renderTemplate(
            self::CLEAN_TEMPLATE_PATH,
            []
        );
    }


    public function actionRunClean()
    {

        $clean = MediaManager::getInstance()->api->runClean();

        if( !$clean ) {

            return $this->asJson([
                'success' => false,
                'errors' => [ 'Something wrong when cleaning entries.', $clean ],
            ]);
        }

        return $this->asJson([
            'total'   => $clean,
            'success' => true
        ]);
    }
}
