<?php

namespace papertiger\mediamanager\migrations;

use Craft;
use craft\db\Migration;
use papertiger\mediamanager\base\ConstantAbstract;

/**
 * m231102_150244_update_scheduled_sync_table migration.
 */

class m231102_150246_update_scheduled_sync_table extends Migration
{
    /**
     * @inheritdoc
     */
		
		private $scheduledSyncTable = ConstantAbstract::MEDIAMANAGER_SCHEDULED_SYNC_TABLE;
    public function safeUp()
    {
        $scheduledSyncTable = Craft::$app->db->schema->getTableSchema($this->scheduledSyncTable);
				
				if(!$scheduledSyncTable){
					echo "No scheduled sync table found.\n";
					return false;
				}
				
				echo "Updating scheduled sync table…\n";
				
				if(!$scheduledSyncTable->getColumn('mediaFieldsToSync')){
					$this->addColumn($this->scheduledSyncTable, 'mediaFieldsToSync', $this->text());
				}
				if(!$scheduledSyncTable->getColumn('showFieldsToSync')){
					$this->addColumn($this->scheduledSyncTable, 'showFieldsToSync', $this->text());
				}
				
				if(!$scheduledSyncTable->getColumn('regenerateThumbnail')){
					$this->addColumn($this->scheduledSyncTable, 'regenerateThumbnail', $this->boolean());
				}
				
				echo "Finished!\n";
				
				return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m231102_150244_update_scheduled_sync_table cannot be reverted.\n";
        return false;
    }
}
