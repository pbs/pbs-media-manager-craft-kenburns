<?php

namespace papertiger\mediamanager\variables;

use Craft;
use papertiger\mediamanager\MediaManager;
use papertiger\mediamanager\records\Show as ShowRecord;

class MediaManagerVariable
{
		public function getShow($showId): ShowRecord
		{
				return ShowRecord::findOne($showId);
		}

}
