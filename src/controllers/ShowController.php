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
use craft\web\Controller;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

use papertiger\mediamanager\MediaManager;

class ShowController extends Controller
{
    // Protected Properties
    // =========================================================================
    protected const SHOW_TEMPLATE_PATH = 'mediamanager/show';
    protected array|int|bool $allowAnonymous          = [ 'index', 'show', 'pbs' ];


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
            self::SHOW_TEMPLATE_PATH,
            [
                'activeShow' => $activeShow,
                'shows' => $shows
            ]
        );
    }

    public function actionSave()
    {
        $request = Craft::$app->getRequest();
        $name    = $request->getBodyParam( 'name' );
        $id      = ( $request->getBodyParam( 'id' ) ) ? $request->getBodyParam( 'id' ) : 0;
        $apiKey  = ( $request->getBodyParam( 'apiKey' ) ) ? $request->getBodyParam( 'apiKey' ) : '';
        $siteId  = ( $request->getBodyParam( 'siteId' ) ) ? $request->getBodyParam( 'siteId' ) : [ 1 ];
        $siteId  = json_encode( $siteId );

        if( !$name ) {

            return $this->asJson([
                'success' => false,
                'errors' => [ 'Show name is required' ],
            ]);
        }

        // Validate API Key first
        if( $id && $name && $apiKey ) {

            if( !MediaManager::getInstance()->api->validateApiKey( $apiKey, 'show' ) ) {

                return $this->asJson([
                    'success' => false,
                    'errors' => [ 'Invalid API Key' ],
                ]);
            }
        }

        $show = MediaManager::getInstance()->show->saveShow( $name, $id, $apiKey, $siteId );

        if( $show->getErrors() ) {

            return $this->asJson([
                'success' => false,
                'errors' => $show->getErrors(),
            ]);
        }

        return $this->asJson([
            'success' => true,
            'show' => [
                'id' => $show->id,
                'name' => $show->name,
                'apiKey' => $show->apiKey,
                'siteId' => $show->siteId
            ],
        ]);
    }

    public function actionDelete()
    {
        $request = Craft::$app->getRequest();
        $id      = $request->getBodyParam( 'id' );

        if( !$id ) {

            return $this->asJson([
                'success' => false,
                'errors' => [ 'Show ID is required' ],
            ]);
        }

        $show = MediaManager::getInstance()->show->deleteShow( $id );

        if( $show->getErrors() ) {

            return $this->asJson([
                'success' => false,
                'errors' => $show->getErrors(),
            ]);
        }

        return $this->asJson([
            'success' => true
        ]);
    }
}
