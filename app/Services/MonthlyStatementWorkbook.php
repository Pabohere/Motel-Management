<?php

namespace App\Services;

use DOMDocument;
use RuntimeException;
use ZipArchive;

class MonthlyStatementWorkbook
{
    public function create(array $report): string
    {
        return $this->build($report)['path'];
    }

    public function preview(array $report): array
    {
        $result = $this->build($report);
        unlink($result['path']);

        return $result['sheets'];
    }

    private function build(array $report): array
    {
        $path = tempnam(sys_get_temp_dir(), 'motel-statements-');
        if ($path === false || ! copy(resource_path('templates/financial-statements.xlsx'), $path)) {
            throw new RuntimeException('Unable to create statement download.');
        }
        $zip = new ZipArchive;
        try {
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Unable to open statement template.');
            }
            $styles = new DOMDocument;
            $styles->loadXML($zip->getFromName('xl/styles.xml'), LIBXML_NONET);
            $formats = $styles->getElementsByTagName('cellXfs')->item(0);
            $noteStyle = $formats->firstChild->cloneNode(true);
            $noteStyle->setAttribute('applyAlignment', '1');
            $alignment = $styles->createElementNS($styles->documentElement->namespaceURI, 'alignment');
            $alignment->setAttribute('wrapText', '1');
            $alignment->setAttribute('vertical', 'top');
            $noteStyle->appendChild($alignment);
            $formats->appendChild($noteStyle);
            $formats->setAttribute('count', (string) $formats->childNodes->length);
            $zip->addFromString('xl/styles.xml', $styles->saveXML());
            $template = app(MonthlyStatementTemplate::class);
            $sheets = $template->populate($zip, $report);
            $zip->addFromString('xl/worksheets/sheet5.xml', view('reports.template-notes', ['notes' => $template->notes($report), 'company' => (string) config('app.name')])->render());
            $workbook = new DOMDocument;
            $workbook->loadXML($zip->getFromName('xl/workbook.xml'), LIBXML_NONET);
            $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $sheet = $workbook->createElementNS($namespace, 'sheet');
            $sheet->setAttribute('name', 'notes');
            $sheet->setAttribute('sheetId', '5');
            $sheet->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', 'rId9');
            $workbook->getElementsByTagName('sheets')->item(0)->appendChild($sheet);
            $definedNames = $workbook->getElementsByTagName('definedNames')->item(0);
            foreach (['pl' => 'I60', 'bs' => 'J60', 'equity' => 'E36', 'cashflow' => 'J50', 'notes' => 'J12'] as $index => $end) {
                $name = $workbook->createElementNS($namespace, 'definedName');
                $name->setAttribute('name', '_xlnm.Print_Area');
                $name->setAttribute('localSheetId', (string) array_search($index, ['pl', 'bs', 'equity', 'cashflow', 'notes'], true));
                $name->appendChild($workbook->createTextNode("'".$index."'!\$A\$1:\$".preg_replace('/(\D+)(\d+)/', '$1\$$2', $end)));
                $definedNames->appendChild($name);
            }
            $calculation = $workbook->getElementsByTagName('calcPr')->item(0);
            $calculation->setAttribute('fullCalcOnLoad', '1');
            $calculation->setAttribute('forceFullCalc', '1');
            $zip->addFromString('xl/workbook.xml', $workbook->saveXML());
            $this->appendPackageElement($zip, 'xl/_rels/workbook.xml.rels', 'Relationship', [
                'Id' => 'rId9', 'Type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet', 'Target' => 'worksheets/sheet5.xml',
            ]);
            $this->appendPackageElement($zip, '[Content_Types].xml', 'Override', [
                'PartName' => '/xl/worksheets/sheet5.xml', 'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml',
            ]);
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish statement workbook.');
            }

            return ['path' => $path, 'sheets' => $sheets];
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    private function appendPackageElement(ZipArchive $zip, string $filename, string $tag, array $attributes): void
    {
        $document = new DOMDocument;
        $document->loadXML($zip->getFromName($filename), LIBXML_NONET);
        $element = $document->createElementNS($document->documentElement->namespaceURI, $tag);
        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, $value);
        }
        $document->documentElement->appendChild($element);
        $zip->addFromString($filename, $document->saveXML());
    }
}
