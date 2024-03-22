<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager\validators;

use Craft;
use yii\validators\Validator;

use papertiger\mediamanager\validators\CustomHandleValidator;
use papertiger\mediamanager\base\ConstantAbstract;

class ApiColumnFieldsValidator extends Validator
{
    // Public Methods
    // =========================================================================
    
    public function validateAttribute( $model, $attribute )
    {
        $fields         = $model->{ $attribute }; 
        $fieldApis      = array_column( $fields, 0 );
        $existingField  = array_column( $fields, 1 );
        $fieldNames     = array_column( $fields, 2 );
        $fieldHandles   = array_column( $fields, 3 );
        $fieldTypes     = array_column( $fields, 4 );
        $requiredFields = ConstantAbstract::REQUIRED_FIELDS;

        if( array_diff( $requiredFields, $fieldApis ) ) {
            $this->addError( $model, $attribute, 'Thumbnail, Display Passport Icon?, Last Synced, Site Tags, Expiration Status, Media Manager ID are required.' );
        }

        // Check if required fields exists
        foreach( $fields as $field ) {
            if( strlen( $field[ 1 ] ) < 1 && ( strlen( $field[ 2 ] ) < 1 || strlen( $field[ 3 ] ) < 1 ) ) {
                $this->addError( $model, $attribute, 'Craft Field Name, Craft Field Handle & Craft Field Type are required if Use existing Craft Field is not being used.' );
            }
        }

        // Check if any duplication on fieldApis
        $checkFieldApis = [];

        foreach( $fieldApis as $fieldApi ) {
            if( in_array( $fieldApi, ConstantAbstract::SPECIAL_FIELDS ) ) {
                $checkFieldApis[] = $fieldApi;
            }
        }

        // if( count( $checkFieldApis ) != count( array_unique( $checkFieldApis ) ) ) {
        //     $this->addError( $model, $attribute, 'Special Field on API Field need only to be used once.' );
        // }

        // Check if any duplication on fieldHandle
        if( count( $fieldHandles ) != count( array_unique( $fieldHandles ) ) ) {
            $this->addError( $model, $attribute, 'Craft Field Handle must be unique.' );
        }

        $handleValidator = new CustomHandleValidator;

        foreach( $fieldHandles as $fieldHandle ) {
            $handleValidator->validateSingle( $model, $attribute, $fieldHandle );
        }

        return;
    }
}
