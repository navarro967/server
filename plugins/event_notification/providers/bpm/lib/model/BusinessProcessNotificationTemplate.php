<?php
/**
 * @package plugins.businessProcessNotification
 * @subpackage model
 */
abstract class BusinessProcessNotificationTemplate extends BatchEventNotificationTemplate
{
	const CUSTOM_DATA_SERVER_ID = 'serverId';
	const CUSTOM_DATA_PROCESS_ID = 'processId';
	
	/* (non-PHPdoc)
	 * @see BatchEventNotificationTemplate::getJobData()
	 */
	public function getJobData(kScope $scope = null)
	{
		$jobData = new kBusinessProcessNotificationDispatchJobData();
		$jobData->setTemplateId($this->getId());
		$jobData->setServerId($this->getServerId());
		
		$contentParametersValues = array();
		$contentParameters = $this->getContentParameters();
		foreach($contentParameters as $contentParameter)
		{
			/* @var $contentParameter kEventNotificationParameter */
			$value = $contentParameter->getValue();
			if($scope && $value instanceof kStringField)
				$value->setScope($scope);
				
			$contentParametersValues[$contentParameter->getKey()] = $value->getValue();
		}
		$userParameters = $this->getUserParameters();
		foreach($userParameters as $userParameter)
		{
			/* @var $userParameter kEventNotificationParameter */
			$value = $userParameter->getValue();
			if($scope && $value instanceof kStringField)
				$value->setScope($scope);
				
			$contentParametersValues[$userParameter->getKey()] = $value->getValue();
		}
		$jobData->setContentParameters($contentParametersValues);
		
		if($scope instanceof kEventScope)
		{
			$object = $scope->getObject();
			$jobData->setObject($object);
		}
		
		return $jobData;
	}

	protected function dispatchPerCase(kScope $scope, $eventNotificationType = null)
	{
		$jobData = parent::getJobData($scope);
		/* @var $jobData kBusinessProcessNotificationDispatchJobData */
		if(!$jobData->getObject())
		{
			return null;
		}
		
		$caseIds = $this->getCaseIds($jobData->getObject());
		$jobId = null;
		foreach($caseIds as $caseId)
		{
			$currentJobData = clone $jobData;
			$currentJobData->setCaseId($caseId);
			$jobId = $this->dispatchJob($scope, $currentJobData, $eventNotificationType);
		}
		return $jobId;
	}
	
	public static function getCaseTemplatesIds(BaseObject $object)
	{
		if(method_exists($object, 'getFromCustomData'))
		{
			$values = $object->getFromCustomData(null, 'businessProcessCases', array());
			if(!$values || !count($values))
			{
				KalturaLog::err('Object [' . get_class($object) . '][' . $object->getPrimaryKey() . '] case id not found in custom-data');
			}
			$templatesIds = array();
			foreach($values as $value)
			{
				$templatesIds[] = $value['templateId'];
			}
			return $templatesIds;
		}
		KalturaLog::err('Object [' . get_class($object) . '] does not support custom-data');
		return array();
	}
	
	public function getCaseIds(BaseObject $object)
	{
		if(method_exists($object, 'getFromCustomData'))
		{
			$values = $object->getFromCustomData($this->getServerId() . '_' . $this->getProcessId(), 'businessProcessCases');
			if(!$values || !count($values))
			{
				KalturaLog::err('Object [' . get_class($object) . '][' . $object->getPrimaryKey() . '] case id not found in custom-data');
			}
			$caseIds = array();
			foreach($values as $value)
			{
				$caseIds[] = $value['caseId'];
			}
			return $caseIds;
		}
		KalturaLog::err('Object [' . get_class($object) . '] does not support custom-data');
		return array();
	}
	
	public function addCaseId(BaseObject $object, $caseId)
	{
		if(method_exists($object, 'putInCustomData'))
		{
			$values = $this->getCaseIds($object);
			$values[] = array(
				'caseId' => $caseId,
				'templateId' => $this->getId(),
			);
			$object->putInCustomData($this->getServerId() . '_' . $this->getProcessId(), $values, 'businessProcessCases');
			$object->save();
		}
	}
	
	public function getServerId()									{return $this->getFromCustomData(self::CUSTOM_DATA_SERVER_ID);}
	public function getProcessId()									{return $this->getFromCustomData(self::CUSTOM_DATA_PROCESS_ID);}
	
	public function setServerId($v)									{return $this->putInCustomData(self::CUSTOM_DATA_SERVER_ID, $v);}
	public function setProcessId($v)								{return $this->putInCustomData(self::CUSTOM_DATA_PROCESS_ID, $v);}
}
