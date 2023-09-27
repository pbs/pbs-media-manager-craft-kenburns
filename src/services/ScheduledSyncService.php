<?php
	/**
	 * Media Manager
	 *
	 * @package       PaperTiger:MediaManager
	 * @author        Paper Tiger
	 * @copyright     Copyright (c) 2020 Paper Tiger
	 * @link          https://www.papertiger.com/
	 */
	
	namespace papertiger\mediamanager\services;
	
	use Craft;
	use craft\base\Component;
	use craft\helpers\DateTimeHelper;
	use craft\helpers\StringHelper;
	use papertiger\mediamanager\events\ScheduledSyncModelEvent;
	use papertiger\mediamanager\models\ScheduledSyncModel;
	use papertiger\mediamanager\records\ScheduledSyncRecord;
	use yii\db\StaleObjectException;
	use yii\web\NotFoundHttpException;
	
	class ScheduledSyncService extends Component
	{
		// Public Methods
		// =========================================================================
		const EVENT_BEFORE_SAVE_SCHEDULED_SYNC = 'beforeSaveScheduledSync';
		const EVENT_AFTER_SAVE_SCHEDULED_SYNC = 'afterSaveScheduledSync';
		
		public function getAllScheduledSyncs(): array
		{
			$currentDateTime = new \DateTime();
			$scheduledSyncRecords = ScheduledSyncRecord::find()
				->where(['>=', 'scheduleDate', $currentDateTime->format('Y-m-d H:i:s')])
				->orderBy(['scheduleDate' => SORT_ASC])
				->all();
			
			return ScheduledSyncModel::populateModels($scheduledSyncRecords, false);
		}
		
		public function getPastScheduledSyncs(): array
		{
			$currentDateTime = new \DateTime();
			$scheduledSyncRecords = ScheduledSyncRecord::find()
				->where(['<', 'scheduleDate', $currentDateTime->format('Y-m-d H:i:s')])
				->orderBy(['scheduleDate' => SORT_DESC])
				->all();
			
			return ScheduledSyncModel::populateModels($scheduledSyncRecords, false);
		}
		
		public function getPushableSyncs(): array
		{
			$scheduledSyncRecords = ScheduledSyncRecord::find()
				->where(['processed' => false])
				->orderBy(['scheduleDate' => SORT_DESC])
				->all();
			
			$filteredScheduledSyncRecords = [];
			
			foreach($scheduledSyncRecords as $scheduledSyncRecord){
				if(DateTimeHelper::isInThePast($scheduledSyncRecord->scheduleDate)){
					$filteredScheduledSyncRecords[] = $scheduledSyncRecord;
				}
			}
			
			return ScheduledSyncModel::populateModels($filteredScheduledSyncRecords, false);
		}
		
		/**
		 * Returns a scheduled sync by its ID.
		 */
		public function getScheduledSyncById(int $scheduledSyncId): ?ScheduledSyncModel
		{
			$scheduledSyncRecord = ScheduledSyncRecord::findOne($scheduledSyncId);
			
			if (!$scheduledSyncRecord) {
				return null;
			}
			
			/** @var ScheduledSyncModel */
			return ScheduledSyncModel::populateModel($scheduledSyncRecord, false);
		}
		
		/**
		 * @param ScheduledSyncModel $scheduledSyncModel
		 * @return bool
		 */
		public function saveScheduledSync(ScheduledSyncModel $scheduledSyncModel): bool
		{
			
			if($scheduledSyncModel->validate() === false) {
				return false;
			}
			
			$isNew = $scheduledSyncModel-> id === null;
			
			if(!$isNew){
				$scheduledSyncRecord = ScheduledSyncRecord::findOne($scheduledSyncModel->id);
				
				if(!$scheduledSyncRecord){
					return false;
				}
				
			} else {
				$scheduledSyncRecord = new ScheduledSyncRecord();
			}
			
			$scheduledSyncRecord->setAttributes($scheduledSyncModel->getAttributes(), false);
			
			$this->trigger(self::EVENT_BEFORE_SAVE_SCHEDULED_SYNC, new ScheduledSyncModelEvent([
				'scheduledSync' => $scheduledSyncRecord,
				'isNew' => $isNew,
			]));
			
			if(!$scheduledSyncRecord->save(false)){
				return false;
			}
			
			$this->trigger(self::EVENT_AFTER_SAVE_SCHEDULED_SYNC, new ScheduledSyncModelEvent([
				'scheduledSync' => $scheduledSyncRecord,
				'isNew' => $isNew,
			]));
			
			$scheduledSyncModel->id = $scheduledSyncRecord->id;
			
			return true;
		}
		
		/**
		 * @throws StaleObjectException
		 */
		public function deleteScheduledSyncById(int $scheduledSyncId): bool
		{
			$scheduledSyncRecord = ScheduledSyncRecord::findOne($scheduledSyncId);
			
			if(!$scheduledSyncRecord){
				return false;
			}
			
			return (bool)$scheduledSyncRecord->delete();
		}
	}
