<?php
/**
 * @service confMaps
 * @package plugins.confMaps
 * @subpackage api.services
 */
class ConfMapsService extends KalturaBaseService
{
	protected function kalturaNetworkAllowed($actionName)
	{
		return true;
	}

	/**
	 * Add configuration map
	 *
	 * @action add
	 * @param KalturaConfMaps $map
	 * @return KalturaConfMaps
	 * @throws KalturaErrors::MAP_ALREADY_EXIST
	 */
	function addAction(KalturaConfMaps $map)
	{
		$dbMap = ConfMapsPeer::getLatestMap($map->name, $map->relatedHost);
		if($dbMap)
		{
			throw new KalturaAPIException(KalturaErrors::MAP_ALREADY_EXIST, $map->name, $map->relatedHost);
		}
		$map->validateContent();
		$newMapVersion = new ConfMaps();
		$map->toInsertableObject($newMapVersion);
		$newMapVersion->setStatus(ConfMapsStatus::STATUS_ENABLED);
		$newMapVersion->setVersion(0);
		$newMapVersion->setRemarks(kCurrentContext::$ks);
		$newMapVersion->save();
		$newMapVersion->syncMapsToCache();
		$map->fromObject($newMapVersion);
		return $map;
	}
	/**
	 * Update configuration map
	 *
	 * @action update
	 * @param KalturaConfMaps $map
	 * @return KalturaConfMaps
	 * @throws KalturaErrors::MAP_DOES_NOT_EXIST
	 */
	function updateAction(KalturaConfMaps $map)
	{
		//get map by values name / hostname
		$dbMap = ConfMapsPeer::getLatestMap($map->name, $map->relatedHost);
		if(!$dbMap)
		{
			throw new KalturaAPIException(KalturaErrors::MAP_DOES_NOT_EXIST );
		}
		$map->validateContent();
		$newMapVersion = new ConfMaps();
		$newMapVersion->addNewMapVersion($dbMap, $map->content);
		$newMapVersion->syncMapsToCache();
		$map->fromObject($newMapVersion);
		return $map;
	}

	/**
	 * List configuration maps
	 *
	 * @action list
	 * @param KalturaConfMapsFilter $filter
	 * @return KalturaConfMapsListResponse
	 * @throws KalturaErrors::MISSING_MAP_NAME
	 */
	function listAction(KalturaConfMapsFilter $filter)
	{
		kApiCache::disableCache();
		$pager = new KalturaFilterPager();
		$response = $filter->getListResponse($pager);
		return $response;
	}

	/**
	 * Get configuration map
	 *
	 * @action get
	 * @param KalturaConfMapsFilter $filter
	 * @return KalturaConfMaps
	 */
	function getAction(KalturaConfMapsFilter $filter)
	{
		$confMap = $filter->getMap();
		return $confMap;
	}
}