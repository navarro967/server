<?php
/**
 * @package deployment
 */
require_once (__DIR__ . '/../../bootstrap.php');

$script = realpath(dirname(__FILE__) . "/../../../tests/standAloneClient/exec.php");

$deleteTemplate = realpath(dirname(__FILE__) . "/xml/deleteRedundantEntryStatusEqualsEmailNotification.xml");
$addTemplate = realpath(dirname(__FILE__) . "/xml/2022_02_16_addEntryStatusEqualEmailNotification.xml");

if(!file_exists($deleteTemplate) || !file_exists($addTemplate) || !file_exists($script))
{
	KalturaLog::err("Missing update script file");
	return;
}

$returnVar = false;
while (!$returnVar)
{
	passthru("php $script $deleteTemplate", $returnVar);
}

KalturaLog::debug("Deleted all entry status equal notification templates, proceeding with create latest version of the template");
passthru("php $script $addTemplate");
