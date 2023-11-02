<?php

namespace papertiger\mediamanager\events;

use craft\events\CancelableEvent;
use papertiger\mediamanager\models\ScheduledSyncModel;

class ScheduledSyncModelEvent extends CancelableEvent
{
	// Properties
	// =========================================================================
	
	/**
	 * @var ScheduledSyncModel|null
	 */
	public $scheduledSync;
	
	/**
	 * @var bool
	 */
	public $isNew = false;
}
