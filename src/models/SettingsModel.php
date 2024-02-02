<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager\models;

use Craft;
use craft\base\Model;

use papertiger\mediamanager\base\ConstantAbstract;
use papertiger\mediamanager\validators\BasicAuthValidator;
use papertiger\mediamanager\validators\CronExpressionValidator;
use papertiger\mediamanager\validators\ApiColumnFieldsValidator;
use papertiger\mediamanager\validators\ShowApiColumnFieldsValidator;

class SettingsModel extends Model
{
    // Public Properties
    // =========================================================================

    public $mediaSection;
    public $mediaUsedBySection;
    public $mediaAssetVolume;
    public $mediaFieldGroup;
    public $showSection;

    public $apiCraftUser        = '';
    public $apiBaseUrl          = '';

    public $apiColumnFields     = ConstantAbstract::API_COLUMN_FIELDS;
    public $showApiColumnFields = ConstantAbstract::SHOW_API_COLUMN_FIELDS;

    public $fieldLayout         = ConstantAbstract::DEFAULT_FIELD_LAYOUT;
    public $showFieldLayout     = ConstantAbstract::DEFAULT_SHOW_FIELD_LAYOUT;

    public $syncSchedule        = ConstantAbstract::SYNC_SCHEDULE;
    public $syncCustomSchedule  = ConstantAbstract::SYNC_CUSTOM_SCHEDULE;
    public $syncPingChangelog   = ConstantAbstract::SYNC_PING_CHANGELOG;


    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            [
                ConstantAbstract::REQUIRED_SETTINGS, 
                'required' 
            ],
            [
                [ 'apiCraftUser' ], 
                'required' 
            ],
            [
                [ 'apiBaseUrl' ],
                BasicAuthValidator::class
            ],
            [
                [ 'apiColumnFields' ],
                ApiColumnFieldsValidator::class
            ],
            [
                [ 'apiColumnFields' ],
                ApiColumnFieldsValidator::class
            ],
            [
                [ 'showApiColumnFields' ],
                ShowApiColumnFieldsValidator::class
            ],
            [
                [ 'syncCustomSchedule' ],
                CronExpressionValidator::class
            ]
        ];
    }
}