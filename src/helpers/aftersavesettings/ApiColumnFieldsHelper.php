<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager\helpers\aftersavesettings;

use Craft;
use yii\base\Application;
use craft\models\TagGroup;
use craft\elements\Tag;
use craft\fields\Assets;
use craft\fields\Date;
use craft\fields\Lightswitch;
use craft\fields\PlainText;
use craft\fields\Tags;
use craft\fields\Url;
use craft\redactor\Field as Redactor;
use craft\helpers\ElementHelper;

use papertiger\mediamanager\MediaManager;
use papertiger\mediamanager\base\ConstantAbstract;
use papertiger\mediamanager\helpers\SettingsHelper;

class ApiColumnFieldsHelper
{
    // Public Static Methods
    // =========================================================================

    public static function process()
    {   
        // Process API Column & Fields
        $settingName = 'apiColumnFields';
        $oldSetting  = MediaManager::getInstance()->oldsettings->get( $settingName );
        $oldValue    = [];
        $newValue    = SettingsHelper::get( $settingName );
        
        if( $oldSetting && $oldSetting->settingValue ) {
            $oldValue = $oldSetting->settingValue;
        }
        
        // Compare hash on both new and old settings
        $oldHash = md5( json_encode( $oldValue ) );
        $newHash = md5( json_encode( $newValue ) );

        if( $oldHash != $newHash ) {

            $oldValue          = ( is_string( $oldValue ) ? json_decode( $oldValue ) : $oldValue );
            $tempNewFields     = [];
            $tempUpdatedFields = [];

            foreach( $newValue as $newField ) {
                
                $fieldApi      = $newField[ ConstantAbstract::API_COLUMN_FIELD_API_INDEX ];
                $existingField = $newField[ ConstantAbstract::API_COLUMN_EXISTING_FIELD_INDEX ];
                $fieldName     = $newField[ ConstantAbstract::API_COLUMN_FIELD_NAME_INDEX ];
                $fieldHandle   = $newField[ ConstantAbstract::API_COLUMN_FIELD_HANDLE_INDEX ];
                $fieldType     = $newField[ ConstantAbstract::API_COLUMN_FIELD_TYPE_INDEX ]; 
                
                $oldSetting = self::getColumnByHandle( $oldValue, $fieldHandle );

                // If using existing field, no need to touch it any further
                if( !$existingField ) {

                    // Check if there's new field
                    if( !$oldSetting ) {
                        $tempNewFields[] = $newField;
                    } else {
                        
                        // Check if there's field being updated
                        if( $fieldName != $oldSetting[ ConstantAbstract::API_COLUMN_FIELD_NAME_INDEX ] || $fieldType != $oldSetting[ ConstantAbstract::API_COLUMN_FIELD_TYPE_INDEX ] ) {
                            $tempUpdatedFields[] = $newField;
                        }
                    }
                }
            }

            // Add new fields
            if( count( $tempNewFields ) ) {
                foreach( $tempNewFields as $tempNewField ) {
                    self::createCraftField( $tempNewField );
                }
            }

            // Update fields
            if( count( $tempUpdatedFields ) ) {
                foreach( $tempUpdatedFields as $tempUpdatedField ) {
                    self::updateCraftField( $tempUpdatedField );
                }
            }
        }
    }


    // Private Static Methods
    // =========================================================================

    private static function getColumnByHandle( $fieldsToSearch, $fieldHandle )
    {
        $fieldHandles = array_column( $fieldsToSearch, ConstantAbstract::API_COLUMN_FIELD_HANDLE_INDEX );
        $foundIndex   = array_search( $fieldHandle, $fieldHandles );

        if( $foundIndex !== false ) {
            return $fieldsToSearch[ $foundIndex ];
        }

        return false;
    }

    private static function findCraftFieldByHandle( $field )
    {
        return Craft::$app->fields->getFieldByHandle( $field[ ConstantAbstract::API_COLUMN_FIELD_HANDLE_INDEX ] );
    }

    private static function createCraftField( $field )
    {   
        // Only create if not exists
        if( !self::findCraftFieldByHandle( $field ) ) {

            $fieldInformation = self::craftFieldInformation( $field );
            $fieldModel       = Craft::$app->getFields()->createField( $fieldInformation );

            Craft::$app->getFields()->saveField( $fieldModel );
        }
    }

    private static function updateCraftField( $field )
    {
        $existingField = self::findCraftFieldByHandle( $field );

        if( $existingField ) {

            $fieldInformation = self::craftFieldInformation( $field );
            $fieldInformation[ 'id' ] = $existingField->id;

            $field = Craft::$app->getFields()->createField( $fieldInformation );
            Craft::$app->getFields()->saveField( $field );
        }
    }

    private static function craftFieldInformation( $field )
    {
        $mediaFieldGroup  = SettingsHelper::get( 'mediaFieldGroup' );
        $mediaAssetVolume = SettingsHelper::get( 'mediaAssetVolume' );

        // Prepare basic information of the field
        $fieldInformation = [
            'name'    => $field[ ConstantAbstract::API_COLUMN_FIELD_NAME_INDEX ],
            'handle'  => $field[ ConstantAbstract::API_COLUMN_FIELD_HANDLE_INDEX ],
            'groupId' => $mediaFieldGroup
        ];

        // Prepare asset volume for craft\fields\Assets;
        $assetVolume = Craft::$app->volumes->getVolumeByHandle( $mediaAssetVolume );

        // Prepare specific field type information
        switch( $field[ ConstantAbstract::API_COLUMN_FIELD_TYPE_INDEX ] ) {

            case 'craft\fields\Assets':
                $fieldInformation[ 'type' ]                         = Assets::class;
                $fieldInformation[ 'defaultUploadLocationSource' ]  = 'folder:' . $assetVolume->uid;
                $fieldInformation[ 'defaultUploadLocationSubpath' ] = '';
                $fieldInformation[ 'allowLimit' ]                   = false;
                $fieldInformation[ 'sources' ]                      = '*';
                $fieldInformation[ 'viewMode' ]                     = 'large';
                $fieldInformation[ 'selectionLabel' ]               = 'Add a ' . $field[ ConstantAbstract::API_COLUMN_FIELD_NAME_INDEX ];
            break;

            case 'craft\fields\Date':
                $fieldInformation[ 'type' ]            = Date::class;
                $fieldInformation[ 'dateTime' ]        = 'showBoth';
                $fieldInformation[ 'minuteIncrement' ] = '30';
            break;

            case 'craft\fields\Lightswitch':
                $fieldInformation[ 'type' ]    = Lightswitch::class;
                $fieldInformation[ 'default' ] = '';
            break;

            case 'craft\fields\PlainText':
                $fieldInformation[ 'type' ]  = PlainText::class;
            break;

            case 'craft\fields\Url':
                $fieldInformation[ 'type' ]  = Url::class;
                $fieldInformation[ 'types' ] = [ 'url' ];
            break;

            case 'craft\redactor\Field':
                $fieldInformation[ 'type' ] = Redactor::class;
                $fieldInformation[ 'availableVolumes' ] = '*';
            break;

            case 'craft\fields\Tags':

                // Generate tag group handle from tag name first
                if( method_exists( 'ElementHelper', 'generateSlug' ) ) {
                    $tagGroupHandle = ElementHelper::generateSlug( $field[ ConstantAbstract::API_COLUMN_FIELD_NAME_INDEX ] );
                } else {
                    $tagGroupHandle = ElementHelper::createSlug( $field[ ConstantAbstract::API_COLUMN_FIELD_NAME_INDEX ] );
                }

                // Find tag group first
                $existingTagGroup = Craft::$app->tags->getTagGroupByHandle( $tagGroupHandle );
                $tagGroupUid      = null;

                if( !$existingTagGroup ) {

                    $group             = new TagGroup();
                    $group->name       = $field[ ConstantAbstract::API_COLUMN_FIELD_NAME_INDEX ];
                    $group->handle     = $tagGroupHandle;
                    $fieldLayout       = Craft::$app->getFields()->assembleLayoutFromPost();
                    $fieldLayout->type = Tag::class;
                    $group->setFieldLayout( $fieldLayout );

                    Craft::$app->getTags()->saveTagGroup( $group );

                    $tagGroupUid = $group->uid;

                } else {
                    $tagGroupUid = $existingTagGroup->uid;
                }

                $fieldInformation[ 'type' ]   = Tags::class;
                $fieldInformation[ 'source' ] = 'taggroup:' . $tagGroupUid;
            break;
        }

        return $fieldInformation;
    }

}
