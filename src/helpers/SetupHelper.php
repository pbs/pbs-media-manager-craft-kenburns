<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager\helpers;

use Craft;
use Exception;
use craft\elements\User;

use papertiger\mediamanager\base\ConstantAbstract;

class SetupHelper
{
    // Public Static Methods
    // =========================================================================
    
    public static function registerRequiredComponents()
    {
        // Craft CMS API User is now using plugin settings
    }

    public static function unregisterRequiredComponents()
    {
        // No Craft CMS API User removal
    }
    
}