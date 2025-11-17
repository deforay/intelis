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
