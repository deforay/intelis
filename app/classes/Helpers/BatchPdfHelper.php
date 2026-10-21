<?php

namespace App\Helpers;

use Override;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\TbPoolService;

class BatchPdfHelper extends BasePdf
{
    public ?string $logo = null;
    public ?string $text = null;
    public ?string $batch = null;
    public ?string $resulted = null;
    public ?string $reviewed = null;
    public ?string $createdBy = null;
    public ?string $worksheetName = null;


    public function setHeading(?string $logo, ?string $text, ?string $batch, ?string $resulted, ?string $reviewed, ?string $createdBy, ?string $worksheetName): void
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->batch = $batch;
        $this->resulted = $resulted;
        $this->reviewed = $reviewed;
        $this->createdBy = $createdBy;
        $this->worksheetName = $worksheetName;
    }
    //Page header
    #[Override]
    public function Header(): void
    {

        if (trim((string) $this->logo) != "" && MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
            $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
            $this->Image($imageFilePath, 15, 10, 15, '', '', '', 'T');
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 10, 26, $this->text, 0, 0, 0, true, 'A');
        $this->SetFont('helvetica', '', 13);
        $this->writeHTMLCell(0, 0, 0, 10, _translate('Batch Number/Code') . ' : ' . $this->batch, 0, 0, 0, true, 'C');
        $this->writeHTMLCell(0, 0, 0, 20, $this->worksheetName, 0, 0, 0, true, 'C');
        $this->SetFont('helvetica', '', 9);
        $this->writeHTMLCell(0, 0, 144, 10, _translate('Result On') . ' : ' . $this->resulted, 0, 0, 0, true, 'C');
        $this->writeHTMLCell(0, 0, 144, 16, _translate('Reviewed On') . ' : ' . $this->reviewed, 0, 0, 0, true, 'C');
        $this->writeHTMLCell(0, 0, 144, 22, _translate('Created By') . ' : ' . $this->createdBy, 0, 0, 0, true, 'C');
        $html = '<hr />';
        $this->writeHTMLCell(0, 0, 10, 32, $html, 0, 0, 0, true, 'J');
    }

    // Page footer
    #[Override]
    public function Footer(): void
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $text = _translate("Batch file generated on") . ' : ' . DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime(), true);
        $this->Cell(0, 10, $text, 0, false, 'L  ', 0);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }

    public static function buildBarcodeImageTag(
        CommonService $general,
        ?string $code,
        string $format,
        array $options = []
    ): string {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }

        $format = strtoupper($format);
        $linearWidth = $options['linear_width'] ?? '200px';
        $linearHeight = $options['linear_height'] ?? '25px';
        $qrSize = $options['qr_size'] ?? '50px';

        $altText = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (stripos($format, 'QR') === 0) {
            $src = $general->get2DBarcodeImageContent($code, $format);
            return sprintf(
                '<img style="width:%1$s;height:%1$s;" src="%2$s" alt="%3$s">',
                $qrSize,
                $src,
                $altText
            );
        }

        $src = $general->getBarcodeImageContent($code, $format);
        return sprintf(
            '<img style="width:%1$s;height:%2$s;" src="%3$s" alt="%4$s">',
            $linearWidth,
            $linearHeight,
            $src,
            $altText
        );
    }

    /**
     * The worksheet of a pooled TB batch, or an empty string when the batch has no
     * pools. Both batch PDFs print it in place of their sample list: a pooled batch is
     * run cartridge by cartridge.
     *
     * @param array<string, mixed> $batch the batch_details row
     */
    public static function buildTbCartridgesWorksheet(
        CommonService $general,
        array $batch,
        int $batchId,
        string $barcodeFormat
    ): string {
        /** @var TbPoolService $tbPoolService */
        $tbPoolService = ContainerRegistry::get(TbPoolService::class);
        $cartridges = $tbPoolService->cartridgesOfBatch(
            $batchId,
            (string) $batch['batch_code'],
            $batch['label_order'] ?? null,
            $general->labScopeWhere('')
        );
        if ($cartridges === []) {
            return '';
        }
        // Cartridges may be labelled from this print, so the pools are fixed from now.
        $tbPoolService->markPoolsPrinted($batchId, DateUtility::getCurrentDateTime());
        $controlNames = json_decode((string) ($batch['control_names'] ?? ''), true);
        return self::buildTbCartridgesTable($general, $cartridges, is_array($controlNames) ? $controlNames : [], $barcodeFormat);
    }

    /**
     * The rows of a pooled TB worksheet, one per cartridge with the barcode to scan as
     * its sample ID: a pool's code with the samples to mix into it, or a sample's own
     * code when it runs alone. Controls keep their place in the run.
     *
     * @param list<array{kind: string, code: string, samples: list<array<string, mixed>>}> $cartridges
     *        from TbPoolService::cartridgesOfBatch()
     * @param array<string, string> $controlNames batch_details.control_names
     */
    public static function buildTbCartridgesTable(
        CommonService $general,
        array $cartridges,
        array $controlNames,
        string $barcodeFormat
    ): string {
        if ($cartridges === []) {
            return '';
        }

        $escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sampleLabel = static function (array $sample) use ($escape): string {
            $code = $escape($sample['sample_code'] ?: $sample['remote_sample_code']);
            return empty($sample['lab_assigned_code']) ? $code : $code . ' (' . $escape($sample['lab_assigned_code']) . ')';
        };

        $html = '<h3>' . $escape(_translate('GeneXpert cartridges')) . '</h3>'
            . '<p>' . $escape(_translate('Run one cartridge per row. Scan its barcode as the cartridge\'s sample ID. For a pool, mix the samples listed into one cartridge.')) . '</p>'
            . '<table cellspacing="0" cellpadding="4" style="width:100%;" border="1">'
            . '<tr>'
            . '<th align="center" width="5%"><strong>#</strong></th>'
            . '<th align="center" width="22%"><strong>' . $escape(_translate('Cartridge Sample ID')) . '</strong></th>'
            . '<th align="center" width="38%"><strong>' . $escape(_translate('BARCODE')) . '</strong></th>'
            . '<th align="center" width="35%"><strong>' . $escape(_translate('Samples')) . '</strong></th>'
            . '</tr>';

        foreach ($cartridges as $index => $cartridge) {
            $barcode = self::buildBarcodeImageTag($general, $cartridge['code'], $barcodeFormat);
            $code = $escape($cartridge['code']);
            if ($cartridge['kind'] === 'control') {
                // The label the lab gave the control, as the sample list prints it.
                $name = trim((string) ($controlNames[$cartridge['code']] ?? ''));
                if ($name === '') {
                    $name = str_replace(['no of ', 'in house'], ['', 'In-House'], str_replace('_', ' ', $cartridge['code']));
                }
                $code = $escape($name);
                $barcode = '';
                $contents = '<strong>' . $escape(_translate('Control')) . '</strong>';
            } elseif ($cartridge['kind'] === 'pool') {
                $result = $cartridge['samples'][0]['pool_result'] ?? null;
                $contents = '<strong>' . $escape(sprintf(_translate('Pool of %d'), count($cartridge['samples']))) . '</strong><br>'
                    . implode('<br>', array_map($sampleLabel, $cartridge['samples']));
                if (!empty($result)) {
                    $contents .= '<br><em>' . $escape($result === 'negative' ? _translate('Pool negative') : _translate('Pool not negative: test individually')) . '</em>';
                }
            } else {
                $contents = '<strong>' . $escape(_translate('Single sample')) . '</strong><br>' . $sampleLabel($cartridge['samples'][0]);
            }

            $html .= '<tr nobr="true">'
                . '<td align="center" style="vertical-align:middle;">' . ($index + 1) . '</td>'
                . '<td align="center" style="vertical-align:middle;">' . $code . '</td>'
                . '<td align="center" style="vertical-align:middle;line-height:30px;">' . $barcode . '</td>'
                . '<td style="vertical-align:middle;">' . $contents . '</td>'
                . '</tr>';
        }

        return $html . '</table>';
    }
}
