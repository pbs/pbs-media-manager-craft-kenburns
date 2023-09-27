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
use craft\helpers\Db;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\elements\Entry;
use craft\web\Controller;
use papertiger\mediamanager\jobs\CancelStaleMedia;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

use papertiger\mediamanager\MediaManager;

class MainController extends Controller
{

    // Protected Properties
    // =========================================================================
    protected const INDEX_TEMPLATE_PATH          = 'mediamanager/index';
    protected const ENTRIES_TEMPLATE_PATH        = 'mediamanager/entries';
    protected $allowAnonymous                    = [ 'index', 'entries' ];

    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        return $this->renderTemplate( self::INDEX_TEMPLATE_PATH );
    }

    public function actionEntries()
    {
        return $this->renderTemplate( self::ENTRIES_TEMPLATE_PATH );
    }

    public function actionCancelMarkedForDeletion()
    {
				$this->requireLogin();
        Queue::push((new CancelStaleMedia()));
				
				return $this->asJson('Unchecking items marked for deletion.');
    }
}
