<?php
/**
 * @package api
 * @subpackage objects
 */
class KalturaPartnerPublicInfo extends KalturaObject
{
	/**
	 * @var string
	 */
	public $analyticsUrl;

	/**
	 * @var string
	 */
	public $ottEnvironmentUrl;

	private static $map_between_objects = array
	(
		"analyticsUrl",
		"ottEnvironmentUrl",
	);

	public function getMapBetweenObjects ( )
	{
		return array_merge ( parent::getMapBetweenObjects() , self::$map_between_objects );
	}
}