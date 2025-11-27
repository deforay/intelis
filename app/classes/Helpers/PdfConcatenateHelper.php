<?php

namespace App\Helpers;

use setasign\Fpdi\Tcpdf\Fpdi;


class PdfConcatenateHelper extends FPDI
{
	public array $files = [];
	public function setFiles(array $files): void
	{
		$this->files = $files;
	}

	/**
	 * Merge PDFs with optional chunking to avoid file handle limits.
	 *
	 * @param array<int,string> $files
	 */
	public function mergeFiles(array $files, string $outputPath, int $chunkSize = 50): bool
	{
		$files = array_values(array_filter($files, 'is_file'));
		if ($files === []) {
			return false;
		}

		$tempMerged = [];
		$filesToMerge = $files;

		if ($chunkSize > 0 && count($filesToMerge) > $chunkSize) {
			$chunks = array_chunk($filesToMerge, $chunkSize);
			foreach ($chunks as $idx => $chunk) {
				$tmpName = dirname($outputPath) . DIRECTORY_SEPARATOR . 'merged-part-' . ($idx + 1) . '.pdf';
				$merger = new self();
				$merger->setFiles($chunk);
				$merger->setPrintHeader(false);
				$merger->setPrintFooter(false);
				$merger->concat();
				$merger->Output($tmpName, "F");
				$tempMerged[] = $tmpName;
			}
			$filesToMerge = $tempMerged;
		}

		$this->setFiles($filesToMerge);
		$this->setPrintHeader(false);
		$this->setPrintFooter(false);
		$this->concat();
		$this->Output($outputPath, "F");

		// Clean up intermediate files
		foreach ($tempMerged as $tmp) {
			@unlink($tmp);
		}

		return true;
	}
	public function concat(): void
	{
		foreach ($this->files as $file) {
			$pagecount = $this->setSourceFile($file);
			for ($i = 1; $i <= $pagecount; $i++) {
				$tplidx = $this->importPage($i);
				$s = $this->getTemplateSize($tplidx);
				$this->AddPage('P', [$s['w'], $s['h']]);
				$this->useTemplate($tplidx);
			}
		}
	}
}
