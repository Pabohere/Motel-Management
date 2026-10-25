<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

class MonthlyStatementTemplate
{
    private array $cells = [];

    private array $rows = [];

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function populate(ZipArchive $zip, array $report): array
    {
        $stringsDocument = new DOMDocument;
        $stringsDocument->loadXML($zip->getFromName('xl/sharedStrings.xml'), LIBXML_NONET);
        $strings = [];
        foreach ($stringsDocument->getElementsByTagName('si') as $string) {
            $strings[] = $string->textContent;
        }
        $current = $this->amounts($report['financials']);
        $prior = $this->amounts($report['comparativeFinancials']);
        $definitions = [
            'pl' => ['title' => 'STATEMENT OF PROFIT OR LOSS', 'columns' => ['G', 'I'], 'hidden' => ['C', 'E'], 'range' => [10, 32], 'totals' => [14, 24, 28, 32]],
            'bs' => ['title' => 'STATEMENT OF FINANCIAL POSITION', 'columns' => ['H', 'J'], 'hidden' => ['D', 'F'], 'range' => [10, 41], 'totals' => [10, 16, 22, 26, 31, 35, 37, 41]],
            'equity' => ['title' => 'STATEMENT OF CHANGES IN EQUITY', 'columns' => ['C', 'D', 'E'], 'hidden' => [], 'range' => [12, 32], 'totals' => [16, 19, 29, 32]],
            'cashflow' => ['title' => 'STATEMENT OF CASH FLOWS', 'columns' => ['H', 'J'], 'hidden' => ['D', 'F'], 'range' => [9, 48], 'totals' => [19, 25, 29, 35, 43, 45, 48]],
        ];
        $previews = [];
        foreach ($definitions as $index => $definition) {
            $number = count($previews) + 1;
            $document = new DOMDocument;
            $document->loadXML($zip->getFromName('xl/worksheets/sheet'.$number.'.xml'), LIBXML_NONET);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('s', self::NS);
            $this->cells = [];
            $this->rows = [];
            foreach ($xpath->query('//s:sheetData/s:row') as $rowNode) {
                $this->rows[$rowNode->getAttribute('r')] = $rowNode;
                foreach ($rowNode->childNodes as $cellNode) {
                    if ($cellNode instanceof DOMElement) {
                        $this->cells[$cellNode->getAttribute('r')] = $cellNode;
                    }
                }
            }
            $labels = [];
            $labelAddresses = [];
            foreach ($xpath->query('//s:sheetData/s:row/s:c[s:v or s:f or s:is]') as $cell) {
                $address = $cell->getAttribute('r');
                $text = $cell->getAttribute('t') === 's' ? ($strings[(int) $cell->textContent] ?? '') : '';
                if (preg_match('/^[AB](\d+)$/', $address, $match) && trim($text) !== '') {
                    $labels[(int) $match[1]] = trim($text);
                    $labelAddresses[(int) $match[1]] = $address;
                }
                if ($cell->getAttribute('t') !== 's' || preg_match('/^[C-Z]/', $address)) {
                    $this->write($document, $xpath, $address, null);
                }
            }
            $end = $report['financials']['asOf']->format('d F Y');
            $title = $definition['title'].($index === 'bs' ? ' as at ' : ' for the month ended ').$end;
            $this->write($document, $xpath, 'A1', (string) config('app.name'), style: 28);
            $this->write($document, $xpath, 'A3', $title);
            if ($index !== 'equity') {
                [$column, $previousColumn] = $definition['columns'];
                $this->write($document, $xpath, $column.'7', $index === 'bs' ? $report['financials']['asOf']->format('d.m.Y') : $report['month']);
                $this->write($document, $xpath, $previousColumn.'7', $index === 'bs' ? $report['comparativeFinancials']['asOf']->format('d.m.Y') : $report['comparisonMonth']);
                foreach ([$column, $previousColumn] as $currencyColumn) {
                    $this->write($document, $xpath, $currencyColumn.'8', '(MMK)');
                }
                foreach ([$column => $current[$index], $previousColumn => $prior[$index]] as $target => $amounts) {
                    foreach ($amounts as $row => $amount) {
                        $formula = $this->formula($index, $row, $target);
                        if ($index === 'cashflow' && $row === 10) {
                            $formula = "'pl'!".($target === $previousColumn ? 'I32' : 'G32');
                        }
                        $this->write($document, $xpath, $target.$row, $amount, $formula);
                    }
                }
            } else {
                $this->write($document, $xpath, 'C7', 'Attributable to equity holders of the Company');
                $this->write($document, $xpath, 'C8', 'Monthly amounts in MMK');
                foreach ([9, 22] as $headerRow) {
                    foreach (['C' => 'Share capital', 'D' => 'Retained earnings', 'E' => 'Total'] as $target => $label) {
                        $this->write($document, $xpath, $target.$headerRow, $label);
                        $this->write($document, $xpath, $target.($headerRow + 1), '(MMK)');
                    }
                }
                foreach ([12 => $report['month'], 25 => $report['comparisonMonth']] as $row => $period) {
                    $labels[$row] = 'For the month ended '.($row === 12 ? $report['financials'] : $report['comparativeFinancials'])['asOf']->format('d F Y');
                    $this->write($document, $xpath, 'A'.$row, $labels[$row]);
                }
                foreach ([0 => $current['equity'], 13 => $prior['equity']] as $offset => $rows) {
                    foreach ($rows as $row => $values) {
                        foreach (['C', 'D', 'E'] as $position => $target) {
                            $formula = $target === 'E' ? 'SUM(C'.($row + $offset).':D'.($row + $offset).')' : null;
                            if ($row === 15 && $target === 'D') {
                                $formula = "'pl'!".($offset === 0 ? 'G32' : 'I32');
                            }
                            if (in_array($row, [16, 19], true) && $target !== 'E') {
                                $formula = $row === 16 ? 'SUM('.$target.(14 + $offset).':'.$target.(15 + $offset).')' : 'SUM('.$target.(13 + $offset).','.$target.(16 + $offset).':'.$target.(18 + $offset).')';
                            }
                            $this->write($document, $xpath, $target.($row + $offset), $values[$position], $formula, in_array($row, [16, 19], true) ? 37 : 2);
                        }
                    }
                }
            }
            $overrides = match ($index) {
                'pl' => [],
                'bs' => [15 => 'Other assets (classification pending)', 34 => 'Borrowings (maturity unclassified)'],
                'equity' => [13 => 'Balance at beginning of the month', 15 => 'Net profit (loss) for the month', 18 => 'Dividend paid / owner drawings', 19 => 'Balance at end of the month', 26 => 'Balance at beginning of the month', 28 => 'Net profit (loss) for the month', 31 => 'Dividend paid / owner drawings', 32 => 'Balance at end of the month'],
                'cashflow' => [18 => 'Operating profit before working capital changes', 19 => 'Adjusted operating profit', 20 => 'Other non-cash / reclassification adjustments', 24 => 'Trade, guest advances and other payables', 32 => 'Asset purchases / other investing payments', 33 => 'Asset disposals / other investing receipts', 41 => 'Dividend paid / owner drawings', 42 => 'Other financing cash flows', 43 => 'Net cash provided by (used in) financing activities', 46 => 'Cash and cash equivalents at beginning of month', 48 => 'Cash and cash equivalents at end of month'],
            };
            foreach ($overrides as $row => $label) {
                $labels[$row] = $label;
                $this->write($document, $xpath, $labelAddresses[$row] ?? 'A'.$row, $label, style: in_array($row, $definition['totals'], true) ? 28 : 3);
            }
            // Scratch calculations and annual sample annotations do not belong in the monthly output.
            foreach (['K24', 'K25', 'F43', 'D61', 'F61', 'H61', 'J61', 'D63', 'F63', 'D62', 'F62', 'H62', 'J62', 'F19', 'F32'] as $address) {
                if ($index === 'equity' || (int) preg_replace('/\D/', '', $address) > $definition['range'][1]) {
                    $this->write($document, $xpath, $address, null);
                }
            }
            if ($index === 'pl') {
                $this->write($document, $xpath, 'K24', null);
                $this->write($document, $xpath, 'K25', null);
            }
            if ($index === 'pl') {
                $this->write($document, $xpath, 'A44', '_____________________');
                $this->write($document, $xpath, 'G44', '_____________________');
            } elseif ($index === 'bs') {
                $this->write($document, $xpath, 'A50', '_____________________');
                $this->write($document, $xpath, 'J50', '_____________________');
            }
            $this->setPrintArea($document, $xpath);
            $zip->addFromString('xl/worksheets/sheet'.$number.'.xml', $document->saveXML());
            $rows = [];
            ksort($labels);
            foreach ($labels as $row => $label) {
                if ($row < $definition['range'][0] || $row > $definition['range'][1]) {
                    continue;
                }
                $values = [];
                foreach ($definition['columns'] as $target) {
                    $node = $this->cells[$target.$row] ?? null;
                    $values[] = $node && $node->hasChildNodes() ? ($node->getAttribute('t') === 'inlineStr' ? $node->textContent : (int) $node->getElementsByTagName('v')->item(0)?->textContent) : null;
                }
                $rows[] = ['label' => $label, 'values' => $values, 'total' => in_array($row, $definition['totals'], true), 'final' => $row === $definition['range'][1]];
            }
            $previews[] = [...$definition, 'name' => $index, 'title' => $title, 'rows' => $rows];
        }

        $this->cells = [];
        $this->rows = [];

        return $previews;
    }

    public function notes(array $report): array
    {
        return [
            'Reporting period: '.$report['month'].'-01 to '.$report['financials']['asOf']->format('Y-m-d H:i').' (Asia/Yangon). Comparative period: '.$report['comparisonMonth'].'. Amounts in MMK. USD is not recorded; no exchange rate has been assumed.',
            'Room revenue is recognized by completed night; additional services by recorded date. The current month is through the stated cutoff; the comparative month is complete.',
            'n.a. means not separately classified in the ledger, not a confirmed zero. Staff cost includes expenses recorded under the salary category. Other operating expenses and unclassified journal expenses are included in general and administrative expenses. Cost of sales is not separately classified. No income tax or exchange gain has been estimated. Profit is based on recorded transactions only.',
            'Other assets are shown as classification pending. Borrowings are shown separately because loan maturities are not recorded. Guest advances and other liabilities are included in other current liabilities. Property, plant and equipment is net of recorded accumulated depreciation.',
            'Share capital includes opening equity and owner contributions. Owner drawings are included with dividends and deducted from retained earnings. No director signature or approval is generated.',
            'The indirect cash flow starts with recorded profit and adjusts for depreciation, receivables and operating liabilities. Other non-cash / reclassification adjustments bridge non-cash entries and explicit cash-flow classifications to recorded operating cash flows. Investing receipts/payments include all entries classified as investing.',
            'Other operating cash-flow adjustments: '.$report['month'].' '.number_format($this->amounts($report['financials'])['cashflow'][20]).' MMK; '.$report['comparisonMonth'].' '.number_format($this->amounts($report['comparativeFinancials'])['cashflow'][20]).' MMK.',
            (! $report['financials']['hasOpeningEntries'] || ! $report['comparativeFinancials']['hasOpeningEntries']) ? 'Opening balances are missing for one or both periods. Statements reflect only recorded transactions and adjustments; record prior assets, liabilities and equity for complete balances.' : 'Opening balances include the opening journal entries recorded in the system.',
            'Reconciliation differences (current / comparative, MMK): balance sheet '.number_format($report['financials']['balanceDifference']).' / '.number_format($report['comparativeFinancials']['balanceDifference']).'; cash flow '.number_format($report['financials']['cashDifference']).' / '.number_format($report['comparativeFinancials']['cashDifference']).'. Differences require review; they have not been plugged.',
        ];
    }

    private function amounts(array $financials): array
    {
        $closing = $financials['closingBalances'];
        $opening = $financials['openingBalances'];
        $change = $financials['accountChanges'];
        $retained = -$closing['revenue'] - $closing['other_income'] - $closing['operating_expense'] - $closing['depreciation_expense'] - $closing['drawings'];
        $openingRetained = -$opening['revenue'] - $opening['other_income'] - $opening['operating_expense'] - $opening['depreciation_expense'] - $opening['drawings'];
        $capital = -$closing['capital'] - $closing['opening_equity'];
        $openingCapital = -$opening['capital'] - $opening['opening_equity'];
        $receivablesChange = -$change['receivables'];
        $payablesChange = -$change['payables'] - $change['guest_deposits'] - $change['other_liabilities'];
        $operatingAdjustment = $financials['flows']['operating'] - ($financials['profit'] + $change['depreciation_expense'] + $receivablesChange + $payablesChange);
        $components = $financials['financingComponents'];
        $recognized = $financials['profit'];
        $capitalChange = $capital - $openingCapital;
        $drawings = -$change['drawings'];

        return [
            'pl' => [10 => $financials['earnedRevenue'], 12 => 'n.a.', 14 => $financials['earnedRevenue'], 16 => -$change['operating_expense'] + $financials['staffCost'], 18 => -$financials['staffCost'], 20 => -$change['depreciation_expense'], 22 => -$change['other_income'], 24 => $recognized, 26 => 'n.a.', 28 => $recognized, 30 => 'n.a.', 32 => $recognized],
            'bs' => [10 => $closing['cash'] + $closing['receivables'] + $closing['other_assets'], 11 => $closing['cash'], 12 => $closing['receivables'], 13 => 'n.a.', 14 => 'n.a.', 15 => $closing['other_assets'], 16 => $closing['fixed_assets'] + $closing['accumulated_depreciation'], 17 => 'n.a.', 18 => $closing['fixed_assets'] + $closing['accumulated_depreciation'], 19 => 'n.a.', 20 => 'n.a.', 22 => $financials['assets'], 26 => -$closing['payables'] - $closing['guest_deposits'] - $closing['other_liabilities'], 27 => -$closing['payables'], 28 => 'n.a.', 29 => -$closing['guest_deposits'] - $closing['other_liabilities'], 31 => 'n.a.', 32 => 'n.a.', 33 => 'n.a.', 34 => -$closing['loans'], 35 => $financials['liabilities'], 37 => $financials['equity'], 38 => $capital, 39 => $retained, 41 => $financials['liabilities'] + $financials['equity']],
            'equity' => [13 => [$openingCapital, $openingRetained, $openingCapital + $openingRetained], 14 => ['n.a.', 'n.a.', 'n.a.'], 15 => [0, $recognized, $recognized], 16 => [0, $recognized, $recognized], 17 => [$capitalChange, 0, $capitalChange], 18 => [0, $drawings, $drawings], 19 => [$capital, $retained, $financials['equity']]],
            'cashflow' => [10 => $recognized, 12 => $change['depreciation_expense'], 13 => 'n.a.', 14 => 'n.a.', 15 => 'n.a.', 16 => 'n.a.', 17 => 'n.a.', 19 => $recognized + $change['depreciation_expense'], 20 => $operatingAdjustment, 21 => 'n.a.', 22 => 'n.a.', 23 => $receivablesChange, 24 => $payablesChange, 25 => $financials['flows']['operating'], 26 => 'n.a.', 27 => 'n.a.', 28 => 'n.a.', 29 => $financials['flows']['operating'], 31 => null, 32 => -$financials['outflows']['investing'], 33 => $financials['inflows']['investing'], 34 => 'n.a.', 35 => $financials['flows']['investing'], 38 => $components['capital'], 39 => $components['borrowings'], 40 => $components['repayments'], 41 => $components['drawings'], 42 => $financials['flows']['financing'] - array_sum($components), 43 => $financials['flows']['financing'], 45 => $financials['netCash'], 46 => $financials['openingCash'], 47 => 'n.a.', 48 => $financials['openingCash'] + $financials['netCash']],
        ];
    }

    private function formula(string $sheet, int $row, string $column): ?string
    {
        $expressions = match ($sheet) {
            'pl' => [14 => 'SUM({c}10:{c}13)', 24 => 'SUM({c}14:{c}23)', 28 => 'SUM({c}24:{c}27)', 32 => 'SUM({c}28:{c}30)'],
            'bs' => [10 => 'SUM({c}11:{c}15)', 16 => 'SUM({c}17:{c}20)', 22 => 'SUM({c}10,{c}16)', 26 => 'SUM({c}27:{c}29)', 35 => 'SUM({c}26,{c}31,{c}34)', 37 => 'SUM({c}38:{c}39)', 41 => 'SUM({c}35,{c}37)'],
            'cashflow' => [19 => 'SUM({c}10:{c}17)', 25 => 'SUM({c}19:{c}24)', 29 => 'SUM({c}25:{c}28)', 35 => 'SUM({c}32:{c}34)', 43 => 'SUM({c}38:{c}42)', 45 => 'SUM({c}29,{c}35,{c}43)', 48 => 'SUM({c}45:{c}47)'],
        };

        return isset($expressions[$row]) ? str_replace('{c}', $column, $expressions[$row]) : null;
    }

    private function write(DOMDocument $document, DOMXPath $xpath, string $address, int|string|null $value, ?string $formula = null, ?int $style = null): void
    {
        $cell = $this->cells[$address] ?? null;
        if (! $cell) {
            preg_match('/([A-Z]+)(\d+)/', $address, $parts);
            $row = $this->rows[$parts[2]] ?? null;
            if (! $row) {
                $row = $document->createElementNS(self::NS, 'row');
                $row->setAttribute('r', $parts[2]);
                $sheetData = $xpath->query('//s:sheetData')->item(0);
                $next = null;
                foreach ($sheetData->childNodes as $candidate) {
                    if ($candidate instanceof DOMElement && (int) $candidate->getAttribute('r') > (int) $parts[2]) {
                        $next = $candidate;
                        break;
                    }
                }
                $sheetData->insertBefore($row, $next);
                $this->rows[$parts[2]] = $row;
            }
            $cell = $document->createElementNS(self::NS, 'c');
            $cell->setAttribute('r', $address);
            $cell->setAttribute('s', (string) ($style ?? 2));
            $next = null;
            foreach ($row->childNodes as $candidate) {
                if ($candidate instanceof DOMElement && $this->columnNumber($candidate->getAttribute('r')) > $this->columnNumber($address)) {
                    $next = $candidate;
                    break;
                }
            }
            $row->insertBefore($cell, $next);
            $this->cells[$address] = $cell;
        }
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');
        if ($style !== null) {
            $cell->setAttribute('s', (string) $style);
        }
        if ($value === null) {
            return;
        }
        if (is_int($value)) {
            if ($formula) {
                $cell->appendChild($document->createElementNS(self::NS, 'f', $formula));
            }
            $cell->appendChild($document->createElementNS(self::NS, 'v', (string) $value));
        } else {
            $cell->setAttribute('t', 'inlineStr');
            $inline = $cell->appendChild($document->createElementNS(self::NS, 'is'));
            $text = $inline->appendChild($document->createElementNS(self::NS, 't'));
            $text->appendChild($document->createTextNode($value));
        }
    }

    private function columnNumber(string $address): int
    {
        $number = 0;
        foreach (str_split(preg_replace('/\d/', '', $address)) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }

        return $number;
    }

    private function setPrintArea(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//s:pageSetup') as $setup) {
            $setup->setAttribute('fitToWidth', '1');
            $setup->setAttribute('fitToHeight', '1');
        }
        $properties = $xpath->query('//s:sheetPr')->item(0);
        if ($properties) {
            $page = $xpath->query('s:pageSetUpPr', $properties)->item(0) ?? $properties->appendChild($document->createElementNS(self::NS, 'pageSetUpPr'));
            $page->setAttribute('fitToPage', '1');
        }
    }
}
