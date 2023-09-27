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

use papertiger\mediamanager\MediaManager;
use papertiger\mediamanager\helpers\SettingsHelper;

class CancelStaleMedia extends BaseJob
{

    // Public Properties
    // =========================================================================


    // Public Methods
    // =========================================================================

    public function execute( $queue )
    {

        $relatedMediaObjects = Entry::find()->markedForDeletion(1);

        foreach(Db::each($relatedMediaObjects) as $media) {
            $media->setFieldValue('markedForDeletion', 0);
            Craft::$app->getElements()->saveElement($media);
        }
    }

    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): string
    {
        return Craft::t( 'mediamanager', "Unmarking entries for deletion." );
    }

    // Private Methods
    // =========================================================================
}
