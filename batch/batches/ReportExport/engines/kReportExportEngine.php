<?php
/**
 * @package Scheduler
 * @subpackage ReportExport
 */
abstract class kReportExportEngine
{
	const FILENAME_PATTERN = "%s.csv";

	protected $reportItem;
	protected $fp;
	protected $fileName;
	protected $fromDate;
	protected $toDate;

	public function __construct($reportItem, $outputPath)
	{
		$this->reportItem = $reportItem;
		$this->filename = $this->createFileName($outputPath);
		$this->fp = fopen($this->filename, 'w');
		if (!$this->fp)
		{
			throw new KOperationEngineException("Failed to open report file : " . $this->filename);
		}
	}

	abstract public function createReport();
	abstract protected function buildCsv($res);

	
	protected function writeReportTitle($title)
	{
		$this->writeRow("# ------------------------------------");
		$this->writeRow("Report: $title");
		$this->writeFilterData();
		$this->writeRow("# ------------------------------------");
	}

	protected function writeFilterData()
	{
		$filter = $this->reportItem->filter;
		if ($filter->toDay && $filter->fromDay)
		{
			$fromDate = strtotime(date('Y-m-d 00:00:00', strtotime($filter->fromDay)));
			$toDate = strtotime(date('Y-m-d 23:59:59', strtotime($filter->toDay)));
			$this->writeRow("Filtered dates (Unix time): $fromDate - $toDate");
		}
		else if ($filter->toDate && $filter->fromDate)
		{
			$this->writeRow("Filtered dates (Unix time): $filter->fromDate - $filter->toDate");
		}
	}

	protected function getFileUniqueId()
	{
		$id = print_r($this->reportItem, true);
		$id .= time();
		return md5($id);
	}

	protected function writeHeaders($headers)
	{
		fwrite($this->fp, $headers."\n");
	}

	protected function writeRow($row)
	{
		fwrite($this->fp, $row."\n");
	}

	protected function createFileName($outputPath)
	{
		$fileName = vsprintf(self::FILENAME_PATTERN, array($this->reportItem->reportTitle));
		$fileName = 'Report_export_' .  $this->getFileUniqueId(). '_' . $fileName;

		return $outputPath.DIRECTORY_SEPARATOR.$fileName;
	}

}
