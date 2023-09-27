<?php

namespace papertiger\mediamanager\models;

use Craft;
use craft\helpers\UrlHelper;
use craft\validators\DateTimeValidator;
use DateTime;
use papertiger\mediamanager\models\BaseModel;

class ScheduledSyncModel extends BaseModel
{
	// Public Properties
	// =========================================================================
	
	/**
	 * @var int|null ID
	 */
	public $id;
	
	/**
	 * @var string Schedule Name
	 */
	public $name;
	
	/**
	 * @var string  Schedule Description
	 */
	public $description;
	
	/**
	 * @var int Show ID
	 */
	public $showId;
	
	/**
	 * @var DateTime Schedule Date
	 */
	public $scheduleDate;
	
	/**
	 * @var bool Processed
	 */
	public $processed;
	
	public function __toString()
	{
		return $this->name;
	}
	
	public function rules(): array
	{
		return [
			[['id', 'showId'], 'number', 'integerOnly' => true],
			[['name', 'description'], 'string'],
			[['name', 'showId', 'scheduleDate'], 'required'],
			[['scheduleDate'], DateTimeValidator::class],
		];
	}
	
	public function datetimeAttributes(): array
	{
		$datetimeAttributes =  parent::datetimeAttributes();
		$datetimeAttributes[] = 'scheduleDate';
		
		return $datetimeAttributes;
	}
	
	public function getCpEditUrl(): string
	{
		return UrlHelper::cpUrl('mediamanager/scheduler/' . $this->id);
	}
	
}
