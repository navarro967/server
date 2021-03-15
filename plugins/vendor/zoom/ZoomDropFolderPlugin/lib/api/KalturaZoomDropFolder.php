<?php

/**
 * @package plugins.ZoomDropFolder
 * @subpackage api.objects
 */
class KalturaZoomDropFolder extends KalturaDropFolder
{
	const CONFIGURATION_PARAM_NAME = 'ZoomAccount';
	const MAP_NAME = 'vendor';
	const ZOOM_BASE_URL = 'ZoomBaseUrl';
	
	/**
	 * @readonly
	 */
	public $refreshToken;
	
	/**
	 * @readonly
	 */
	public $accessToken;
	
	/**
	 * @readonly
	 */
	public $clientId;
	
	/**
	 * @readonly
	 */
	public $clientSecret;
	
	/**
	 * @readonly
	 */
	public $baseURL;
	
	/**
	 * @readonly
	 */
	public $jwtToken;
	
	/**
	 * @var int
	 * @readonly
	 */
	public $zoomVendorIntegrationId;
	
	/**
	 * @var KalturaZoomIntegrationSetting
	 * @readonly
	 */
	public $zoomVendorIntegration;
	
	/*
	 * mapping between the field on this object (on the left) and the setter/getter on the entry object (on the right)
	 */
	private static $map_between_objects = array(
		'zoomVendorIntegrationId',
	);
	
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}
	
	public function doFromObject($sourceObject, KalturaDetachedResponseProfile $responseProfile = null)
	{
		parent::doFromObject($sourceObject, $responseProfile);
		
		/* @var ZoomVendorIntegration $vendorIntegration */
		$vendorIntegration = VendorIntegrationPeer ::retrieveByPK($this->zoomVendorIntegrationId);
		
		if($vendorIntegration)
		{
			$headerData = self::getZoomHeaderData();
			$this->clientId = $headerData[0];
			$this->clientSecret = $headerData[1];
			$this->baseURL = $headerData[2];
			$this->jwtToken = $vendorIntegration ->getJwtToken();
			$this->refreshToken = $vendorIntegration->getRefreshToken();
			$this->accessToken = $vendorIntegration->getAccessToken();
			$zoomClient = new kZoomClient($this->baseURL, $this->jwtToken, $this->refreshToken, $this->clientId,
			                              $this->clientSecret, $this->accessToken);
			$freshTokens = $zoomClient->refreshTokens();
			$this->accessToken = $freshTokens[kZoomTokens::ACCESS_TOKEN];
			$this->refreshToken = $freshTokens[kZoomTokens::REFRESH_TOKEN];
			
			$vendorIntegration->saveTokensData($freshTokens);
			$vendorIntegration->save();
			$zoomIntegrationObject = new KalturaZoomIntegrationSetting();
			$zoomIntegrationObject->fromObject($vendorIntegration);
			$this->zoomVendorIntegration = $zoomIntegrationObject;
		}
		else
		{
			throw new KalturaAPIException(KalturaZoomDropFolderErrors::DROP_FOLDER_INTEGRATION_DATA_MISSING);
		}
		
	}
	
	private static function getZoomHeaderData()
	{
		$zoomConfiguration = kConf::get(self::CONFIGURATION_PARAM_NAME, self::MAP_NAME);
		$clientId = $zoomConfiguration['clientId'];
		$zoomBaseURL = $zoomConfiguration['ZoomBaseUrl'];
		$clientSecret = $zoomConfiguration['clientSecret'];
		return array($clientId, $clientSecret, $zoomBaseURL);
	}
}