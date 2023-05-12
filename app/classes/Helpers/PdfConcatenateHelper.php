<?php

namespace App\Helpers;

use setasign\Fpdi\Tcpdf\Fpdi;


class PdfConcatenateHelper extends FPDI
{
	public array $files = [];
	public function setFiles($files)
	{
		$this->files = $files;
	}
	public function concat()
	{
		foreach ($this->files as $file) {
			$pagecount = $this->setSourceFile($file);
			for ($i = 1; $i <= $pagecount; $i++) {
				$tplidx = $this->ImportPage($i);
				$s = $this->getTemplatesize($tplidx);
				$this->AddPage('P', array($s['w'], $s['h']));
				$this->useTemplate($tplidx);
			}
		}
	}
}
