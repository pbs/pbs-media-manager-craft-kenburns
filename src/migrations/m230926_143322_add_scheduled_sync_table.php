<?php

namespace papertiger\mediamanager\migrations;

use Craft;
use craft\db\Migration;
use papertiger\mediamanager\base\ConstantAbstract;

/**
 * m230926_143321_add_scheduled_sync_table migration.
 */
class m230926_143322_add_scheduled_sync_table extends Migration
{
		private $showTable        = ConstantAbstract::MEDIAMANAGER_SHOW_TABLE;
		private $scheduledSyncTable = ConstantAbstract::MEDIAMANAGER_SCHEDULED_SYNC_TABLE;
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
	    $scheduledSyncTable = Craft::$app->db->schema->getTableSchema($this->scheduledSyncTable);
			
			if(!$scheduledSyncTable) {
				$this->createTable($this->scheduledSyncTable, [
					'id' => $this->primaryKey(),
					'name' => $this->text(),
					'description' => $this->text(),
					'showId' => $this->integer()->notNull(),
					'scheduleDate' => $this->dateTime()->notNull(),
					'processed' => $this->boolean()->defaultValue(false),
					'dateCreated' => $this->dateTime()->notNull(),
					'dateUpdated' => $this->dateTime()->notNull(),
					'uid' => $this->uid(),
				]);
				
				$this->addForeignKey(
					$this->db->getForeignKeyName($this->scheduledSyncTable, 'showId'),
					$this->scheduledSyncTable,
					'showId',
					ConstantAbstract::MEDIAMANAGER_SHOW_TABLE,
					'id',
					'CASCADE',
					'CASCADE'
				);
			}
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "Dropping {$this->scheduledSyncTable} table.\n";
				
				$this->dropTableIfExists($this->scheduledSyncTable);
				
				echo "Dropped {$this->scheduledSyncTable} table.\n";
				
        return true;
    }
}
