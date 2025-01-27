<?php
/**
 * @package api
 * @subpackage filters
 */
class KalturaBaseEntryFilter extends KalturaBaseEntryBaseFilter
{
	static private $map_between_objects = array
	(
		"freeText" => "_free_text",
		"excludedFreeTextGroups" => "_excluded_free_text_groups",
		"descriptionLike" => "_like_description",
		"isRoot" => "_is_root",
		"categoriesFullNameIn" => "_in_categories_full_name", 
		"categoryAncestorIdIn" => "_in_category_ancestor_id",
		"redirectFromEntryId" => "_eq_redirect_from_entry_id",
		"entitledUsersEditMatchAnd" => "_matchand_entitled_kusers_edit",
		"entitledUsersPublishMatchAnd" => "_matchand_entitled_kusers_publish",
		"entitledUsersEditMatchOr" => "_matchor_entitled_kusers_edit",
		"entitledUsersPublishMatchOr" => "_matchor_entitled_kusers_publish",
		"entitledUsersViewMatchAnd" => "_matchand_entitled_kusers_view",
		"entitledUsersViewMatchOr" => "_matchor_entitled_kusers_view",
		"conversionProfileIdEqual" => "_eq_conversion_profile_id",
	);
	
	static private $order_by_map = array
	(
		"recent" => "recent", // needed for backward compatibility
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	public function getOrderByMap()
	{
		return array_merge(parent::getOrderByMap(), self::$order_by_map);
	}

	/**
	 * @var string
	 */
	public $freeText;

	/**
	 * @var string
	 */
	public $excludedFreeTextGroups;

	/**
	 * @var string
	 */
	public $descriptionLike;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $isRoot;
	
	/**
	 * @var string
	 */
	public $categoriesFullNameIn;
	
	/**
	 * All entries within this categoy or in child categories  
	 * @var string
	 */
	public $categoryAncestorIdIn;

	/**
	 * The id of the original entry
	 * @var string
	 */
	public $redirectFromEntryId;

	/**
	 * @var int
	 */
	public $conversionProfileIdEqual;

	/* (non-PHPdoc)
	 * @see KalturaFilter::getCoreFilter()
	 */
	protected function getCoreFilter()
	{
		return new entryFilter();
	}
	
	/**
	 * Set the default status to ready if other status filters are not specified
	 */
	private function setDefaultStatus()
	{
		if ($this->statusEqual === null && 
			$this->statusIn === null &&
			$this->statusNotEqual === null &&
			$this->statusNotIn === null)
		{
			$this->statusEqual = KalturaEntryStatus::READY;
		}
	}
	
	/**
	 * Set the default moderation status to ready if other moderation status filters are not specified
	 */
	private function setDefaultModerationStatus()
	{
		if ($this->moderationStatusEqual === null && 
			$this->moderationStatusIn === null && 
			$this->moderationStatusNotEqual === null && 
			$this->moderationStatusNotIn === null)
		{
			$moderationStatusesNotIn = array(
				KalturaEntryModerationStatus::PENDING_MODERATION, 
				KalturaEntryModerationStatus::REJECTED);
			$this->moderationStatusNotIn = implode(",", $moderationStatusesNotIn); 
		}
	}

	/**
	 * The user_id is infact a puser_id and the kuser_id should be retrieved
	 */
	private function fixFilterUserId()
	{
		if ($this->userIdEqual !== null)
		{
			$kuser = kuserPeer::getKuserByPartnerAndUid(kCurrentContext::getCurrentPartnerId(), $this->userIdEqual);
			if ($kuser)
				$this->userIdEqual = $kuser->getId();
			else 
				$this->userIdEqual = myKuserUtils::NON_EXISTING_USER_ID; // no result will be returned when the user is missing
		}

		if(!empty($this->userIdIn))
		{
			$this->userIdIn = myKuserUtils::preparePusersToKusersFilter( $this->userIdIn );
		}

		if(!empty($this->userIdNotIn))
		{
			$this->userIdNotIn = myKuserUtils::preparePusersToKusersFilter($this->userIdNotIn);
		}
		
		if(!empty($this->entitledUsersEditMatchAnd))
		{
			$this->entitledUsersEditMatchAnd = myKuserUtils::preparePusersToKusersFilter( $this->entitledUsersEditMatchAnd );
		}

		if(!empty($this->entitledUsersPublishMatchAnd))
		{
			$this->entitledUsersPublishMatchAnd = myKuserUtils::preparePusersToKusersFilter( $this->entitledUsersPublishMatchAnd );
		}
		
		if(!empty($this->entitledUsersEditMatchOr))
		{
			$this->entitledUsersEditMatchOr = myKuserUtils::preparePusersToKusersFilter( $this->entitledUsersEditMatchOr );
		}

		if(!empty($this->entitledUsersPublishMatchOr))
		{
			$this->entitledUsersPublishMatchOr = myKuserUtils::preparePusersToKusersFilter( $this->entitledUsersPublishMatchOr );
		}
		
		if(!empty($this->entitledUsersViewMatchOr))
		{
			$this->entitledUsersViewMatchOr = myKuserUtils::preparePusersToKusersFilter( $this->entitledUsersViewMatchOr );
		}

		if(!empty($this->entitledUsersViewMatchAnd))
		{
			$this->entitledUsersViewMatchAnd = myKuserUtils::preparePusersToKusersFilter( $this->entitledUsersViewMatchAnd );
		}
	}
	
	/**
	 * @param KalturaFilterPager $pager
	 * @return KalturaCriteria
	 */
	public function prepareEntriesCriteriaFilter(KalturaFilterPager $pager = null)
	{
		// because by default we will display only READY entries, and when deleted status is requested, we don't want this to disturb
		entryPeer::allowDeletedInCriteriaFilter(); 
		
		$c = KalturaCriteria::create(entryPeer::OM_CLASS);
	
		if( $this->idEqual == null && $this->redirectFromEntryId == null )
		{
			$this->setDefaultStatus();
			$this->setDefaultModerationStatus($this);
			if (($this->parentEntryIdEqual == null) && ($this->idIn == null) && ($this->displayInSearchEqual == null))
			{
				$displayInSearchStatusNotIn = array(mySearchUtils::DISPLAY_IN_SEARCH_RECYCLED, mySearchUtils::DISPLAY_IN_SEARCH_SYSTEM);
				$c->add(entryPeer::DISPLAY_IN_SEARCH, $displayInSearchStatusNotIn, Criteria::NOT_IN);
			}
		}
		
		$this->fixFilterUserId($this);
		$entryFilter = new entryFilter();
		$entryFilter->setPartnerSearchScope(baseObjectFilter::MATCH_KALTURA_NETWORK_AND_PRIVATE);
		$this->toObject($entryFilter);
		
		if($pager)
		{
			$pager->attachToCriteria($c);
		}
			
		$entryFilter->attachToCriteria($c);
		
		return $c;
	}
	
	protected function doGetListResponse(KalturaFilterPager $pager)
	{
		myDbHelper::$use_alternative_con = myDbHelper::DB_HELPER_CONN_PROPEL3;

		$disableWidgetSessionFilters = false;
		$c = $this->prepareEntriesCriteriaFilter($pager);
		if ($this->idEqual || $this->idIn || $this->referenceIdEqual || $this->redirectFromEntryId
			|| $this->referenceIdIn || $this->parentEntryIdEqual )
		{
			$disableWidgetSessionFilters = true;
			if (kEntitlementUtils::getEntitlementEnforcement() && !kCurrentContext::$is_admin_session &&
				entryPeer::getUserContentOnly())
			{
				entryPeer::setFilterResults(true);
			}

			KalturaCriterion::disableTag(KalturaCriterion::TAG_WIDGET_SESSION);
		}

		$list = entryPeer::doSelect($c);
		entryPeer::fetchPlaysViewsData($list);
		$totalCount = $c->getRecordsCount();
		
		if ($disableWidgetSessionFilters)
		{
			KalturaCriterion::restoreTag(KalturaCriterion::TAG_WIDGET_SESSION);
		}

		myDbHelper::$use_alternative_con = null;
			
		return array($list, $totalCount);		
	}

	/* (non-PHPdoc)
	 * @see KalturaFilter::getListResponse()
	 */
	public function getListResponse(KalturaFilterPager $pager, KalturaDetachedResponseProfile $responseProfile = null)
	{
		list($list, $totalCount) = $this->doGetListResponse($pager);
		
	    $newList = KalturaBaseEntryArray::fromDbArray($list, $responseProfile);
		$response = new KalturaBaseEntryListResponse();
		$response->objects = $newList;
		$response->totalCount = $totalCount;
		
		return $response;
	}

	/* (non-PHPdoc)
	 * @see KalturaRelatedFilter::validateForResponseProfile()
	 */
	public function validateForResponseProfile()
	{		
		if(kEntitlementUtils::getEntitlementEnforcement())
		{
			if(PermissionPeer::isValidForPartner(PermissionName::FEATURE_ENABLE_RESPONSE_PROFILE_USER_CACHE, kCurrentContext::getCurrentPartnerId()))
			{
				KalturaResponseProfileCacher::useUserCache();
				return;
			}
			
			throw new KalturaAPIException(KalturaErrors::CANNOT_LIST_RELATED_ENTITLED_WHEN_ENTITLEMENT_IS_ENABLE, get_class($this));
		}
		
		if(		!kCurrentContext::$is_admin_session
			&&	!$this->idEqual 
			&&	!$this->idIn
			&&	!$this->referenceIdEqual
			&&	!$this->redirectFromEntryId
			&&	!$this->referenceIdIn 
			&&	!$this->parentEntryIdEqual)
		{
			if(kCurrentContext::$ks_object->privileges === ks::PATTERN_WILDCARD || kCurrentContext::$ks_object->getPrivilegeValue(ks::PRIVILEGE_LIST) === ks::PATTERN_WILDCARD)
			{
				return;
			}
			
			if(PermissionPeer::isValidForPartner(PermissionName::FEATURE_ENABLE_RESPONSE_PROFILE_USER_CACHE, kCurrentContext::getCurrentPartnerId()))
			{
				KalturaResponseProfileCacher::useUserCache();
				return;
			}
			
			throw new KalturaAPIException(KalturaErrors::USER_KS_CANNOT_LIST_RELATED_ENTRIES, get_class($this));
		}
	}
}
