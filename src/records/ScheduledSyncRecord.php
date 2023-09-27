<?php
	/**
	 * Media Manager
	 *
	 * @package       PaperTiger:MediaManager
	 * @author        Paper Tiger
	 * @copyright     Copyright (c) 2020 Paper Tiger
	 * @link          https://www.papertiger.com/
	 */
	
	namespace papertiger\mediamanager\records;
	
	use Craft;
	use craft\db\ActiveRecord;
	
	use DateTime;
	use papertiger\mediamanager\MediaManager;
	use papertiger\mediamanager\base\ConstantAbstract;
	
	/**
	 * ScheduledSyncRecord
	 * @return string the table name
	 * @property string|mixed $name Schedule Name
	 * @property string $description Schedule Description
	 * @property int $showId Show ID
	 * @property int $id ID
	 * @property DateTime $scheduleDate Schedule Date
	 * @property bool $processed Processed
	 */
	class ScheduledSyncRecord extends ActiveRecord
	{
		// Private Properties
		// =========================================================================
		
		private static $mediaManagerScheduledSyncTable = ConstantAbstract::MEDIAMANAGER_SCHEDULED_SYNC_TABLE;
		
		
		// Public Static Methods
		// =========================================================================
		
		public static function tableName(): string
		{
			return self::$mediaManagerScheduledSyncTable;
		}
		
		
		// Public Methods
		// =========================================================================
		
		public function rules()
		{
			return [
				[ ['showId', 'scheduleDate'], 'required' ]
			];
		}
	}
