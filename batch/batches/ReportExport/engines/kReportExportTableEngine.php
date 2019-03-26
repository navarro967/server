<?php
/**
 * @package Scheduler
 * @subpackage ReportExport
 */
class kReportExportTableEngine extends kReportExportEngine
{
	const MAX_CSV_RESULT_SIZE = 60000;
	
	public function createReport()
	{
		$pager = new KalturaFilterPager();
		$pager->pageIndex = 1;
		$pager->pageSize = self::MAX_CSV_RESULT_SIZE;

		$result =  KBatchBase::$kClient->report->getTable($this->reportItem->reportType, $this->reportItem->filter,
			$pager, $this->reportItem->order, $this->reportItem->objectIds, $this->reportItem->responseOptions);
		return $this->buildCsv($result);
	}

	protected function buildCsv($result)
	{
		$this->writeReportTitle($this->reportItem->reportTitle);
		$this->writeHeaders($result->header);
		$rows = explode(';', $result->data);
		foreach ($rows as $row)
		{
			$this->writeRow($row);
		}
		fclose($this->fp);
		return $this->filename;
	}

}
