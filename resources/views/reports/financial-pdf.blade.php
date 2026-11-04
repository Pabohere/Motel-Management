<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; font-src data:; base-uri 'none'; @isset($printNonce)script-src 'nonce-{{ $printNonce }}'@endisset">
    <title>{{ config('app.name') }} · {{ $month }} · Financial Statements</title>
    <style>
        @font-face { font-family: Statement; src: url(data:font/ttf;base64,{{ $embeddedFont }}) format('truetype'); font-weight: 100 900; }
        @page { size: A4; margin: 14mm 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font-family: Cambria, 'Times New Roman', Statement, serif; font-size: 10pt; line-height: 1.25; }
        section { break-before: page; }
        section:first-of-type { break-before: auto; }
        header { margin-bottom: 7mm; }
        header strong { display: block; margin-bottom: 4mm; font-size: 12pt; }
        h1 { font-size: 10.5pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        th, td { padding: 1.8mm 1mm; vertical-align: top; font-weight: 400; }
        th { text-align: left; width: 58%; }
        td, thead th:not(:first-child) { width: 21%; text-align: right; font-variant-numeric: tabular-nums; }
        thead th { padding-bottom: 4mm; }
        thead small { display: block; margin-top: 2mm; font-size: 9pt; }
        td { white-space: nowrap; }
        .total th, .total td { font-weight: 700; }
        .total td { border-top: 1px solid #000; }
        .final td { border-bottom: 3px double #000; }
        .equity th { width: 46%; }
        .equity td, .equity thead th:not(:first-child) { width: 18%; }
        .equity-period th { padding-top: 5mm; font-weight: 700; }
        .bs th, .bs td { padding-top: 1mm; padding-bottom: 1mm; }
        .cashflow th, .cashflow td { padding-top: .6mm; padding-bottom: .6mm; line-height: 1.15; }
        .signatures { margin-top: 8mm; break-inside: avoid; }
        .signature-lines { display: flex; justify-content: space-between; margin-top: 10mm; }
        footer { margin-top: 8mm; font-size: 9pt; font-style: italic; }
        .print-toolbar { padding: 16px; margin-bottom: 24px; background: #edf5ff; font-family: Statement, sans-serif; }
        .print-toolbar button { padding: 12px 20px; cursor: pointer; font: inherit; }
        @media screen { body { max-width: 210mm; padding: 20px; margin: auto; } section { margin-bottom: 32px; } }
        @media print { .print-toolbar { display: none; } }
        .notes h1 { margin-bottom: 6mm; }
        .notes p { line-height: 1.5; margin: 0 0 5mm; }
    </style>
</head>
<body>
    @isset($printNonce)
    <div class="print-toolbar"><button type="button" id="save-pdf">Print / Save as PDF</button><p>{{ __('Print dialog တွင် Save as PDF ကို ရွေးပြီး သိမ်းပါ။') }}</p></div>
    <script nonce="{{ $printNonce }}">document.getElementById('save-pdf').addEventListener('click', () => window.print());</script>
    @endisset
    @foreach($templateSheets as $sheet)
    <section class="{{ $sheet['name'] }}">
        <header><strong>{{ config('app.name') }}</strong><h1>{{ $sheet['title'] }}</h1></header>
        <table>
            <thead><tr><th></th>
                @if($sheet['name'] === 'equity')<th>Share capital<small>(MMK)</small></th><th>Retained earnings<small>(MMK)</small></th><th>Total<small>(MMK)</small></th>
                @else<th>{{ $sheet['name'] === 'bs' ? $financials['asOf']->format('d.m.Y') : $month }}<small>(MMK)</small></th><th>{{ $sheet['name'] === 'bs' ? $comparativeFinancials['asOf']->format('d.m.Y') : $comparisonMonth }}<small>(MMK)</small></th>@endif
            </tr></thead>
            <tbody>
                @foreach($sheet['rows'] as $row)
                <tr class="{{ $row['total'] ? 'total' : '' }} {{ $row['final'] ? 'final' : '' }} {{ str_starts_with($row['label'], 'For the month') ? 'equity-period' : '' }}">
                    <th scope="row">{{ $row['label'] }}</th>
                    @foreach($row['values'] as $amount)<td>{{ is_int($amount) ? ($amount < 0 ? '('.number_format(abs($amount)).')' : ($amount === 0 ? '—' : number_format($amount))) : $amount }}</td>@endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @if(in_array($sheet['name'], ['pl', 'bs'], true))<div class="signatures">Authenticated by Directors: -<div class="signature-lines"><span>_____________________</span><span>_____________________</span></div></div>@endif
        <footer>The accompanying notes to accounts form an integral part of the financial statements.</footer>
    </section>
    @endforeach
    <section class="notes"><h1>NOTES TO THE MONTHLY FINANCIAL STATEMENTS</h1>@foreach($templateNotes as $note)<p>{{ $loop->iteration }}. {{ $note }}</p>@endforeach</section>
</body>
</html>
