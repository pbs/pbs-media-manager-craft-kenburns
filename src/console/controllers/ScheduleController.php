<?php
	/**
	 * Media Manager
	 *
	 * @package       PaperTiger:MediaManager
	 * @author        Paper Tiger
	 * @copyright     Copyright (c) 2020 Paper Tiger
	 * @link          https://www.papertiger.com/
	 */
	
	namespace papertiger\mediamanager\console\controllers;
	
	use Craft;
	use papertiger\mediamanager\jobs\ShowSync;
	use papertiger\mediamanager\MediaManager;
	use yii\console\Controller;
	use yii\console\ExitCode;
	use yii\helpers\Console;
	
	use papertiger\mediamanager\helpers\SettingsHelper;
	
	
	class ScheduleController extends Controller
	{
		public $defaultAction = 'index';
		protected $allowAnonymous = ['index', 'run'];
		
		public function actionIndex(): int
		{
			$scheduledSyncService = MediaManager::$plugin->scheduledSync;
			$pushableSyncs = $scheduledSyncService->getPushableSyncs();
			echo "Checking for scheduled syncs... \n";
			
			if(empty($pushableSyncs))
			{
				echo "No scheduled syncs found. \n";
				return ExitCode::OK;
			}
			
			foreach ($pushableSyncs as $pushableSync) {
				Craft::$app->getQueue()->push(new ShowSync([
					'showId' => $pushableSync->showId,
				]));
				
				$pushableSync->processed = 1;
				$scheduledSyncService->saveScheduledSync($pushableSync);
				echo "Pushed sync for show ' . $pushableSync->showId . ' to queue. \n";
			}
			
			return ExitCode::OK;
		}
		
		public function actionRun(): int
		{
			return $this->actionIndex();
		}
	}
