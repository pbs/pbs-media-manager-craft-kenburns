<?php

namespace papertiger\mediamanager\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\ArrayHelper;
use papertiger\mediamanager\helpers\SettingsHelper;

/**
 * m230705_151019_add_marked_for_deletion_field migration.
 */
class m230705_151024_add_marked_for_deletion_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
	    $schemaVersion = Craft::$app->projectConfig->get(
		    'plugins.mediamanager.schemaVersion',
		    true
	    );
	
	    if (version_compare($schemaVersion, '1.0.1', '<')) {
		    $fieldService = Craft::$app->getFields();
		    $field = $fieldService->getFieldByHandle('markedForDeletion');
		
		    if(!$field) {
			    // we know this field will exist
			    $groupToUse = $fieldService->getFieldByHandle('lastSynced')->groupId;
			
			    $field = Craft::$app->getFields()->createField([
				    'type' => 'craft\fields\Lightswitch',
				    'name' => 'Marked for Deletion',
				    'handle' => 'markedForDeletion',
				    'groupId' => $groupToUse,
				    'searchable' => true,
			    ]);
			
			    Craft::$app->getFields()->saveField($field);
		    }
		
		    $mediaSection = Craft::$app->getSections()->getSectionByHandle(SettingsHelper::get( 'mediaSection' ));
		
		    if($mediaSection) {
			    $entryType = $mediaSection->getEntryTypes()[0];
			    $fieldLayout = $entryType->getFieldLayout();
			    $allFields = $fieldLayout->getFields();
			    $fieldLayout->setFields(ArrayHelper::merge([$field], $allFields));
			    $tabs = $fieldLayout->getTabs();
			
			    foreach($tabs as $tab) {
				    if ($tab->name === 'API'){
					    $existingFields = $tab->getFields();
					    $tab->setFields(ArrayHelper::merge([$field], $existingFields));
				    }
			    }
			
			    $entryType->setFieldLayout($fieldLayout);
			    Craft::$app->getSections()->saveEntryType($entryType);
		    }
	    }
			
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m230705_151021_add_marked_for_deletion_field cannot be reverted.\n";
        return false;
    }
}
