<?php
	/**
	 * Media Manager
	 *
	 * @package       PaperTiger:MediaManager
	 * @author        Paper Tiger
	 * @copyright     Copyright (c) 2020 Paper Tiger
	 * @link          https://www.papertiger.com/
	 */
	
	namespace papertiger\mediamanager\controllers;
	
	use Craft;
	use craft\helpers\DateTimeHelper;
	use craft\web\Controller;
	
	use craft\web\Response;
	use papertiger\mediamanager\MediaManager;
	use papertiger\mediamanager\models\ScheduledSyncModel;
	use yii\web\BadRequestHttpException;
	
	class ScheduledSyncController extends Controller
	{
		
		protected array|int|bool $allowAnonymous = [];
		
		public function actionIndex(): Response
		{
			$scheduledSyncs = MediaManager::getInstance()->scheduledSync->getAllScheduledSyncs();
			
			
			/** @var Response */
			return $this->renderTemplate('mediamanager/scheduler/index', [
				'scheduledSyncs' => $scheduledSyncs
			]);
		}
		public function actionEdit(int $scheduledSyncId = null, ScheduledSyncModel $scheduledSync = null): Response
		{
			$isNew = false;
			$title = $scheduledSync->name ?? 'Create a new scheduled sync';
			$shows = MediaManager::getInstance()->show->getShow();
			
			if (!$scheduledSync && $scheduledSyncId) {
				$scheduledSync = MediaManager::getInstance()->scheduledSync->getScheduledSyncById($scheduledSyncId);
				$title = $scheduledSync->name;
			}
			
			if(!$scheduledSync) {
				$scheduledSync = new ScheduledSyncModel();
				$isNew = true;
				$title = Craft::t('mediamanager', 'Create a new scheduled sync');
			}
			
			$tabs = ['scheduledSyncSettings' => [
				'label' => Craft::t('mediamanager', 'Settings'),
				'url' => '#scheduled-sync-settings'
			]];
			
			$variables = [
				'title' => $title,
				'scheduledSyncId' => $scheduledSyncId,
				'scheduledSync' => $scheduledSync,
				'tabs' => $tabs,
				'isNew' => $isNew,
				'selectedTab' => 'scheduledSyncSettings',
				'shows' => $shows
			];
			
			/** @var craft\web\Response */
			return $this->renderTemplate('mediamanager/scheduler/_edit', $variables);
		}
		
		/**
		 * @throws BadRequestHttpException
		 * @throws \Exception
		 */
		public function actionSaveScheduledSync()
		{
			$this->requirePostRequest();
			$request = $this->request;
			
			$scheduledSyncId = $request->getBodyParam('scheduledSyncId');
			
			if($scheduledSyncId){
				$scheduledSync = MediaManager::getInstance()->scheduledSync->getScheduledSyncById($scheduledSyncId);
				if(!$scheduledSync) {
					throw new BadRequestHttpException('Invalid scheduled sync ID: ' . $scheduledSyncId);
				}
			} else {
				$scheduledSync = new ScheduledSyncModel();
			}
			
			$scheduledSync->name = $request->getBodyParam('name');
			$scheduledSync->description = $request->getBodyParam('description');
			$scheduledSync->scheduleDate = $request->getBodyParam('scheduleDate');
			$scheduledSync->processed = $request->getBodyParam('processed') ?? false;
			
			$scheduledSync->scheduleDate = DateTimeHelper::toDateTime($scheduledSync->scheduleDate);
			$scheduledSync->showId = $request->getBodyParam('showId');
			
			$mediaFieldsToSync = $request->getBodyParam('mediaFieldsToSync');
			$showFieldsToSync = $request->getBodyParam('showFieldsToSync');
			
			$scheduledSync->mediaFieldsToSync = $mediaFieldsToSync === '*' ? '*' : join(',', $mediaFieldsToSync);
			$scheduledSync->showFieldsToSync = $showFieldsToSync === '*' ? '*' : join(',', $showFieldsToSync);
			$scheduledSync->regenerateThumbnail = $request->getBodyParam('forceRegenerateThumbnail') ?? false;
			
			if(!MediaManager::getInstance()->scheduledSync->saveScheduledSync($scheduledSync)) {
				Craft::$app->getSession()->setError(Craft::t('mediamanager', 'Couldn’t save scheduled sync.'));
				Craft::$app->getUrlManager()->setRouteParams([
					'scheduledSync' => $scheduledSync
				]);
				return null;
			}
			
			Craft::$app->getSession()->setNotice(Craft::t('mediamanager', 'Scheduled sync saved.'));
			return $this->redirectToPostedUrl($scheduledSync);
		}
	}
