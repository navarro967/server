<?php

/**
 * @package plugins.reach
 */
class kReachManager implements kObjectChangedEventConsumer, kObjectCreatedEventConsumer, kObjectAddedEventConsumer, kGenericEventConsumer
{
	/**
	 * @var array<booleanNotificationTemplate>
	 */
	protected static $booleanNotificationTemplatesFulfilled;
	protected static $booleanNotificationTemplatesFromReachProfiles;
	protected static $reachProfilesFilteredWithoutBooleanEventNotifications;
	protected static $isInit = false;

	CONST PROFILE_ID = 0;
	CONST ACTION = 1;

	protected function getObjectType($eventObjectClassName)
	{
		$mapObjectType = array("entry" => objectType::ENTRY,
			"category" => objectType::CATEGORY,
			"asset" => objectType::ASSET,
			"flavorAsset" => objectType::FLAVORASSET,
			"thumbAsset" => objectType::THUMBASSET,
			"uiconf" => objectType::UICONF,
			"conversionProfile2" => objectType::CONVERSIONPROFILE2,
			"kuser" => objectType::KUSER,
			"permission" => objectType::PERMISSION,
			"permissionItem" => objectType::PERMISSIONITEM,
			"userRole" => objectType::USERROLE );

		if (isset($mapObjectType[$eventObjectClassName]))
		{
			return $mapObjectType[$eventObjectClassName];
		}
		return null;
	}

	/* (non-PHPdoc)
	 * @see kGenericEventConsumer::consumeEvent()
	 */
	public function consumeEvent(KalturaEvent $event)
	{
		$scope = $event->getScope();
		$partnerId = $scope->getPartnerId();
		$object = $scope->getObject();
		$entryId = $object->getEntryId();
		foreach (self::$booleanNotificationTemplatesFulfilled as $booleanNotificationTemplate)
		{
			$profileId = $booleanNotificationTemplate[self::PROFILE_ID];
			$booleanNotificationTemaplateObjects = $booleanNotificationTemplate[self::ACTION];
			$fullFieldCatalogItemIds = $booleanNotificationTemaplateObjects->getCatalogItemIds();
			$allowedCatalogItemIds = PartnerCatalogItemPeer::retrieveActiveCatalogItemIds($fullFieldCatalogItemIds, $partnerId);
			if(!count($allowedCatalogItemIds))
			{
				KalturaLog::debug("None of the fullfield catalog item ids are active on partner, [" . implode(",", $fullFieldCatalogItemIds) . "]");
				continue;
			}
			$this->addingEntryVendorTaskByObjectIds($entryId, $allowedCatalogItemIds, $profileId, $object);
		}
		return true;
	}

	protected static function initReachProfileForPartner($partnerId)
	{
		if (self::$isInit)
		{
			return;
		}
		self::$isInit = true;
		//will hold array of: array(profileId,action) where there are boolean event notification ids.
		self::$booleanNotificationTemplatesFromReachProfiles = array();

		//will hold the reach profiles without boolean event notification ids.
		self::$reachProfilesFilteredWithoutBooleanEventNotifications = array();

		$reachProfiles = ReachProfilePeer::retrieveByPartnerId($partnerId);
		foreach ($reachProfiles as $profile)
		{
			$profileWasAdded = 0;
			$rules = $profile->getRulesArray();
			foreach ($rules as $rule)
			{
				foreach ($rule->getActions() as $action)
				{
					if ($action->getbooleanEventNotificationIds() && $action->getbooleanEventNotificationIds() != "N/A")
					{
						self::$booleanNotificationTemplatesFromReachProfiles[] = array($profile->getId(), $action);
						$profileWasAdded++;
					}
				}
			}

			if ($profileWasAdded == 0)
			{
				self::$reachProfilesFilteredWithoutBooleanEventNotifications[] = $profile;
			}
		}
	}

	/* (non-PHPdoc)
	 * @see kGenericEventConsumer::shouldConsumeEvent()
	 * side effect: while checking if the rules are fulfilled, building an array:
	 * $booleanNotificationTemplatesFulfilled - contain array of (profileId, actions with boolean event notification that were fulfilled)
	 */
	public function shouldConsumeEvent(KalturaEvent $event)
	{
		self::$booleanNotificationTemplatesFulfilled = array();
		$fulfilled = 0;

		$scope = $event->getScope();
		$partnerId = $scope->getPartnerId();
		if (!ReachPlugin::isAllowedPartner($partnerId))
		{
			return false;
		}
		$eventType = kEventNotificationFlowManager::getEventType($event);
		$eventObjectClassName = kEventNotificationFlowManager::getEventObjectType($event);
		$objectType = self::getObjectType($eventObjectClassName);
		if ($objectType)
		{
			$this->initReachProfileForPartner($partnerId);
			if (self::$booleanNotificationTemplatesFromReachProfiles)
			{
				foreach (self::$booleanNotificationTemplatesFromReachProfiles as $profileAction)
				{
					$booleanEventNotificationIdArray = explode(',', $profileAction[self::ACTION]->getbooleanEventNotificationIds());
					$boolEventNotificationObjectList = EventNotificationTemplatePeer::retrieveByEventTypeObjectTypeAndPKS($eventType, $objectType, $partnerId, $booleanEventNotificationIdArray);
					foreach ($boolEventNotificationObjectList as $boolEventNotificationObject)
					{
						$fulfilled = $boolEventNotificationObject->fulfilled($scope);
						if ($fulfilled)
						{
							self::$booleanNotificationTemplatesFulfilled[] = array($profileAction[self::PROFILE_ID], $profileAction[self::ACTION]);
							break;
						}
					}
				}
			}
		}
		return count(self::$booleanNotificationTemplatesFulfilled);
	}

	/**
	 * @param BaseObject $object
	 * @param BatchJob $raisedJob
	 * @return bool true if the consumer should handle the event
	 */
	public function shouldConsumeAddedEvent(BaseObject $object)
	{
		if ($object instanceof categoryEntry)
			return true;
		return false;
	}

	/* (non-PHPdoc)
	 * @see kObjectAddedEventConsumer::shouldConsumeAddedEvent()
	 */
	public function shouldConsumeCreatedEvent(BaseObject $object)
	{
		if ($object instanceof EntryVendorTask && $object->getStatus() == EntryVendorTaskStatus::PENDING)
			return true;

		return false;
	}

	/* (non-PHPdoc)
	 * @see kObjectChangedEventConsumer::shouldConsumeChangedEvent()
	*/
	public function shouldConsumeChangedEvent(BaseObject $object, array $modifiedColumns)
	{
		if ($object instanceof EntryVendorTask
			&& in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns)
			&& $object->getStatus() == EntryVendorTaskStatus::PENDING
			&& $object->getColumnsOldValue(EntryVendorTaskPeer::STATUS) == EntryVendorTaskStatus::PENDING_MODERATION
		)
			return true;

		if ($object instanceof EntryVendorTask
			&& in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns)
			&& in_array($object->getStatus(), array(EntryVendorTaskStatus::ERROR, EntryVendorTaskStatus::READY))
		)
			return true;

		if($object instanceof entry && $object->getType() == entryType::MEDIA_CLIP)
		{
			$event = new kObjectChangedEvent($object,$modifiedColumns);
			if ($this->shouldConsumeEvent($event))
				return true;

			if (in_array(entryPeer::LENGTH_IN_MSECS, $modifiedColumns))
			{
				return true;
			}

			if (in_array(entryPeer::STATUS, $modifiedColumns) && in_array($object->getStatus(), array(entryStatus::READY, entryStatus::DELETED)))
			{
				return true;
			}
		}

		if ($object instanceof categoryEntry && $object->getStatus() == CategoryEntryStatus::ACTIVE)
		{
			return true;
		}

		return false;
	}

	/**
	 * @param BaseObject $object
	 * @param BatchJob $raisedJob
	 * @return bool true if should continue to the next consumer
	 */
	public function objectAdded(BaseObject $object, BatchJob $raisedJob = null)
	{
		if ($object instanceof categoryEntry && $object->getStatus() == CategoryEntryStatus::ACTIVE)
			$this->checkAutomaticRules($object);

		return true;
	}

	/* (non-PHPdoc)
	 * @see kObjectAddedEventConsumer::objectAdded()
	 */
	public function objectCreated(BaseObject $object, BatchJob $raisedJob = null)
	{
		$this->updateReachProfileCreditUsage($object);
		return true;
	}

	/* (non-PHPdoc)
	 * @see kObjectChangedEventConsumer::objectChanged()
	 */
	public function objectChanged(BaseObject $object, array $modifiedColumns)
	{
		if ($object instanceof EntryVendorTask && in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns)
			&& $object->getStatus() == EntryVendorTaskStatus::PENDING
			&& $object->getColumnsOldValue(EntryVendorTaskPeer::STATUS) == EntryVendorTaskStatus::PENDING_MODERATION
		)
			return $this->updateReachProfileCreditUsage($object);

		if ($object instanceof EntryVendorTask
			&& in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns)
			&& $object->getStatus() == EntryVendorTaskStatus::ERROR
			&& in_array($object->getColumnsOldValue(EntryVendorTaskPeer::STATUS), array(EntryVendorTaskStatus::PENDING, EntryVendorTaskStatus::PROCESSING))
		)
			return $this->handleErrorTask($object);
		
		if ($object instanceof EntryVendorTask
			&& in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns)
			&& $object->getStatus() == EntryVendorTaskStatus::READY
		)
			return $this->invalidateAccessKey($object);

		if ($object instanceof entry && $object->getType() == entryType::MEDIA_CLIP)
		{
			$this->initReachProfileForPartner($object->getPartnerId());

			if (count(self::$booleanNotificationTemplatesFulfilled))
			{
				$event = new kObjectChangedEvent($object,$modifiedColumns);
				$this->consumeEvent($event);
			}

			if (in_array(entryPeer::LENGTH_IN_MSECS, $modifiedColumns))
			{
				return $this->handleEntryDurationChanged($object);
			}

			if (in_array(entryPeer::STATUS, $modifiedColumns))
			{
				if ($object->getStatus() == entryStatus::READY)
				{
					return $this->checkAutomaticRules($object, true);
				}

				if ($object->getStatus() == entryStatus::DELETED)
				{
					return $this->handleEntryDeleted($object);
				}
			}
		}

		if ($object instanceof categoryEntry && $object->getStatus() == CategoryEntryStatus::ACTIVE)
		{
			return $this->checkAutomaticRules($object);
		}

		return true;
	}

	private function updateReachProfileCreditUsage(EntryVendorTask $entryVendorTask)
	{
		ReachProfilePeer::updateUsedCredit($entryVendorTask->getReachProfileId(), $entryVendorTask->getPrice());
	}

	private function handleErrorTask(EntryVendorTask $entryVendorTask)
	{
		ReachProfilePeer::updateUsedCredit($entryVendorTask->getReachProfileId(), -$entryVendorTask->getPrice());
	}
	
	private function invalidateAccessKey(EntryVendorTask $entryVendorTask)
	{
		$ksString = $entryVendorTask->getAccessKey();
		
		try
		{
			$ksObj = kSessionUtils::crackKs($ksString);
		}
		catch(Exception $ex)
		{
			KalturaLog::debug("Failed to crackKs with error message [" . $ex->getMessage() . "], accessKey won't be invalidated");
		}
		
		$ksObj->kill();
	}

	private function handleEntryDurationChanged(entry $entry)
	{
		$pendingEntryVendorTasks = EntryVendorTaskPeer::retrievePendingByEntryId($entry->getId(), $entry->getPartnerId());
		$addedCostByProfileId = array();
		foreach ($pendingEntryVendorTasks as $pendingEntryVendorTask)
		{
			/* @var $pendingEntryVendorTask EntryVendorTask */
			$oldPrice = $pendingEntryVendorTask->getPrice();
			$newPrice = kReachUtils::calculateTaskPrice($entry, $pendingEntryVendorTask->getCatalogItem());
			$priceDiff = $newPrice - $oldPrice;
			
			if(!$priceDiff)
				continue;
			
			$pendingEntryVendorTask->setPrice($newPrice);
			if (!isset($addedCostByProfileId[$pendingEntryVendorTask->getReachProfileId()]))
				$addedCostByProfileId[$pendingEntryVendorTask->getReachProfileId()] = 0;

			if (kReachUtils::checkPriceAddon($pendingEntryVendorTask, $priceDiff))
			{
				$pendingEntryVendorTask->save();
				if($pendingEntryVendorTask->getStatus() != EntryVendorTaskStatus::PENDING_MODERATION)
					$addedCostByProfileId[$pendingEntryVendorTask->getReachProfileId()] += $priceDiff;
			}
			else
			{
				$pendingEntryVendorTask->setStatus(EntryVendorTaskStatus::ABORTED);
				$pendingEntryVendorTask->setPrice($newPrice);
				$pendingEntryVendorTask->setErrDescription("Current task price exceeded credit allowed, task was aborted");
				$pendingEntryVendorTask->save();
				$addedCostByProfileId[$pendingEntryVendorTask->getReachProfileId()] -= $oldPrice;
			}
		}

		foreach ($addedCostByProfileId as $reachProfileId => $addedCost)
		{
			if(!$addedCost)
				continue;
			
			ReachProfilePeer::updateUsedCredit($reachProfileId, $addedCost);
		}

		return true;
	}

	public static function addEntryVendorTaskByObjectIds($entryId, $vendorCatalogItemId, $reachProfileId, $context = null)
	{
		$entry = entryPeer::retrieveByPK($entryId);
		$reachProfile = ReachProfilePeer::retrieveActiveByPk($reachProfileId);
		$vendorCatalogItem = VendorCatalogItemPeer::retrieveByPK($vendorCatalogItemId);
		
		if(!$entry || !$reachProfile || !$vendorCatalogItem)
		{
			KalturaLog::log("Not all mandatory objects were found, task will not be added");
			return true;
		}

		$sourceFlavor = assetPeer::retrieveOriginalByEntryId($entry->getId());
		$sourceFlavorVersion = $sourceFlavor != null ? $sourceFlavor->getVersion() : 0;

		if (kReachUtils::isDuplicateTask($entryId, $vendorCatalogItemId, $entry->getPartnerId(), $sourceFlavorVersion))
		{
			KalturaLog::log("Trying to insert a duplicate entry vendor task for entry [$entryId], catalog item [$vendorCatalogItemId] and entry version [$sourceFlavorVersion]");
			return true;
		}

		//check if credit has expired
		if (kReachUtils::hasCreditExpired($reachProfile))
		{
			KalturaLog::log("Credit cycle has expired, Task could not be added for entry [$entryId] and catalog item [$vendorCatalogItemId]");
			return true;
		}

		if (!kReachUtils::isEnoughCreditLeft($entry, $vendorCatalogItem, $reachProfile))
		{
			KalturaLog::log("Exceeded max credit allowed, Task could not be added for entry [$entryId] and catalog item [$vendorCatalogItemId]");
			return true;
		}
		
		if(!kReachUtils::isEntryTypeSupported($entry->getType()))
		{
			KalturaLog::log("Entry of type [{$entry->getType()}] is not supported by Reach");
			return true;
		}

		$entryVendorTask = self::addEntryVendorTask($entry, $reachProfile, $vendorCatalogItem, false, $sourceFlavorVersion, $context, EntryVendorTaskCreationMode::AUTOMATIC);
		$entryVendorTask->save();
		return $entryVendorTask;
	}

	public static function addEntryVendorTask(entry $entry, ReachProfile $reachProfile, VendorCatalogItem $vendorCatalogItem, $validateModeration = true, $version = 0, $context = null, $creationMode = null)
	{
		//Create new entry vendor task object
		$entryVendorTask = new EntryVendorTask();

		//Assign default parameters
		$entryVendorTask->setEntryId($entry->getId());
		$entryVendorTask->setCatalogItemId($vendorCatalogItem->getId());
		$entryVendorTask->setReachProfileId($reachProfile->getId());
		$entryVendorTask->setPartnerId($entry->getPartnerId());
		$entryVendorTask->setKuserId(self::getTaskKuserId($entry));
		$entryVendorTask->setUserId(self::getTaskPuserId($entry));
		$entryVendorTask->setVendorPartnerId($vendorCatalogItem->getVendorPartnerId());
		$entryVendorTask->setVersion($version);
		$entryVendorTask->setQueueTime(null);
		$entryVendorTask->setFinishTime(null);

		//Set calculated values
		$shouldModerateOutput = !$reachProfile->shouldModerateOutputCaptions($vendorCatalogItem->getServiceType());
		$entryVendorTask->setAccessKey(kReachUtils::generateReachVendorKs($entryVendorTask->getEntryId(), $shouldModerateOutput, $vendorCatalogItem->getKsExpiry()));
		$entryVendorTask->setPrice(kReachUtils::calculateTaskPrice($entry, $vendorCatalogItem));

		if ($context)
			$entryVendorTask->setContext($context);

		if ($creationMode)
			$entryVendorTask->setCreationMode($creationMode);

		$status = EntryVendorTaskStatus::PENDING;
		if ($validateModeration && $reachProfile->shouldModerate($vendorCatalogItem->getServiceType()))
			$status = EntryVendorTaskStatus::PENDING_MODERATION;

		$dictionary = $reachProfile->getDictionaryByLanguage($vendorCatalogItem->getSourceLanguage());
		if ($dictionary)
			$entryVendorTask->setDictionary($dictionary->getData());

		$entryVendorTask->setStatus($status);
		return $entryVendorTask;
	}
	
	//For automatic dispatched tasks make sure to set the entry creator user as the entry owner
	protected static function getTaskKuserId(entry $entry)
	{
		$kuserId = kCurrentContext::getCurrentKsKuserId();
		if(kCurrentContext::$ks_partner_id <= PartnerPeer::GLOBAL_PARTNER)
		{
			$kuserId = $entry->getKuserId();
		}
		
		return $kuserId;
	}
	
	//For automatic dispatched tasks make sure to set the entry creator user as the entry owner
	protected static function getTaskPuserId(entry $entry)
	{
		$puserId = kCurrentContext::$ks_uid;
		if(kCurrentContext::$ks_partner_id <= PartnerPeer::GLOBAL_PARTNER)
		{
			$puserId = $entry->getPuserId();
		}
		
		return $puserId;
	}

	private function addingEntryVendorTaskByObjectIds($entryId, $allowedCatalogItemIds, $profileId, $object)
	{
		$existingCatalogItemIds = EntryVendorTaskPeer::retrieveExistingTasksCatalogItemIds($entryId, $allowedCatalogItemIds);
		$catalogItemIdsToAdd = array_unique(array_diff($allowedCatalogItemIds, $existingCatalogItemIds));

		foreach ($catalogItemIdsToAdd as $catalogItemIdToAdd)
		{
			//Pass the object Id as the context of the task
			self::addEntryVendorTaskByObjectIds($entryId, $catalogItemIdToAdd, $profileId, $this->getContextByObjectType($object));
		}
	}

	private function checkAutomaticRules($object, $checkEmptyRulesOnly = false)
	{
		$scope = new kScope();
		$entryId = $object->getEntryId();
		$scope->setEntryId($entryId);
		$this->initReachProfileForPartner($object->getPartnerId());
		if (self::$reachProfilesFilteredWithoutBooleanEventNotifications)
		{
			foreach (self::$reachProfilesFilteredWithoutBooleanEventNotifications as $profile)
			{
				/* @var $profile ReachProfile */
				$fullFieldCatalogItemIds = $profile->fulfillsRules($scope, $checkEmptyRulesOnly);
				if (!count($fullFieldCatalogItemIds))
				{
					continue;
				}

				$allowedCatalogItemIds = PartnerCatalogItemPeer::retrieveActiveCatalogItemIds($fullFieldCatalogItemIds, $object->getPartnerId());
				if(!count($allowedCatalogItemIds))
				{
					KalturaLog::debug("None of the fullfield catalog item ids are active on partner, [" . implode(",", $fullFieldCatalogItemIds) . "]");
					continue;
				}
				$this->addingEntryVendorTaskByObjectIds($entryId, $allowedCatalogItemIds, $profile->getId(), $object);
			}
		}
		return true;
	}

	private function handleEntryDeleted(entry $entry)
	{
		//Delete all pending moderation tasks
		$pendingModerationTasks = EntryVendorTaskPeer::retrievePendingByEntryId($entry->getId(), $entry->getPartnerId(), array(EntryVendorTaskStatus::PENDING_MODERATION));
		foreach ($pendingModerationTasks as $pendingModerationTask)
		{
			/* @var $pendingModerationTask EntryVendorTask */
			$pendingModerationTask->setStatus(EntryVendorTaskStatus::ABORTED);
			$pendingModerationTask->setErrDescription("Task was aborted by server, associated entry [{$entry->getId()}] was deleted");
			$pendingModerationTask->save();
		}
	}

	private function getContextByObjectType($object)
	{
		if ($object instanceof categoryEntry)
			return $object->getCategoryId();

		return null;
	}
}
