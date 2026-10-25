@php
    $statement = $financials['statements'][$statementKey];
    $priorRows = $comparativeFinancials['statements'][$statementKey]['rows'];
    $groups = match ($statementKey) {
        'balance_sheet' => [0 => 'ပိုင်ဆိုင်မှုများ (Assets)', 6 => 'ပေးရန်တာဝန်များ (Liabilities)', 11 => 'ပိုင်ရှင်အရင်းအနှီး (Equity)'],
        'income_statement' => [0 => 'ဝင်ငွေများ (Revenue)', 3 => 'အသုံးစရိတ်များ (Expenses)'],
        'cash_flow' => [1 => 'လုပ်ငန်းလည်ပတ်မှု (Operating)', 4 => 'ရင်းနှီးမြှုပ်နှံမှု (Investing)', 7 => 'ငွေကြေးထောက်ပံ့မှု (Financing)', 10 => 'ငွေလက်ကျန်ပြန်လည်တိုက်စစ်ခြင်း'],
    };
    $rowNumber = 0;
    $merges = [];
    $textCell = static fn (string $address, string $text, int $style = 0): string => '<c r="'.$address.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
    $heading = function (string $text, int $style = 0, int $height = 36) use (&$rowNumber, &$merges, $textCell): string {
        $rowNumber++;
        $merges[] = 'A'.$rowNumber.':C'.$rowNumber;
        return '<row r="'.$rowNumber.'" ht="'.$height.'" customHeight="1">'.$textCell('A'.$rowNumber, $text, $style).'</row>';
    };
@endphp
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>
    <sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
    <sheetFormatPr defaultRowHeight="34"/>
    <cols><col min="1" max="1" width="64" customWidth="1"/><col min="2" max="3" width="25" customWidth="1"/></cols>
    <sheetData>
        {!! $heading((string) config('app.name'), 1, 40) !!}
        {!! $heading(__($statement['title']), 1, 40) !!}
        {!! $heading(($statementKey === 'balance_sheet' ? __('ရက်စွဲအရ လက်ကျန်') : __('စာရင်းကာလ').': '.$month.'-01 —').' '.$financials['asOf']->format('d M Y H:i')) !!}
        {!! $heading(__('ငွေကြေး: မြန်မာကျပ် (MMK) · အနုတ်ပမာဏကို ကွင်းစကွင်းပိတ်ဖြင့် ပြထားသည်။')) !!}
        @php
            $rowNumber++;
        @endphp
        <row r="{{ $rowNumber }}" ht="65" customHeight="1">
            {!! $textCell('A'.$rowNumber, __('စာရင်းအကြောင်းအရာ'), 2) !!}
            {!! $textCell('B'.$rowNumber, __('ယခုလ').' '.$month."\n".__('အထိ').' '.$financials['asOf']->format('d M Y H:i'), 2) !!}
            {!! $textCell('C'.$rowNumber, __('ယခင်လ').' '.$comparisonMonth."\n".__('အထိ').' '.$comparativeFinancials['asOf']->format('d M Y H:i'), 2) !!}
        </row>
        @foreach($statement['rows'] as $index => $row)
            @if(isset($groups[$index])){!! $heading(__($groups[$index]), 2) !!}@endif
            @php
                $rowNumber++;
                $isFinalTotal = $statementKey === 'income_statement' ? $loop->last : $index === count($statement['rows']) - 2;
                $numberStyle = $row['total'] ? ($isFinalTotal ? 6 : 4) : 3;
                $labelStyle = $row['total'] ? ($isFinalTotal ? 7 : 5) : 0;
            @endphp
            <row r="{{ $rowNumber }}" ht="38" customHeight="1">
                {!! $textCell('A'.$rowNumber, __($row['label']), $labelStyle) !!}
                <c r="B{{ $rowNumber }}" s="{{ $numberStyle }}"><v>{{ $row['amount'] }}</v></c>
                <c r="C{{ $rowNumber }}" s="{{ $numberStyle }}"><v>{{ $priorRows[$index]['amount'] }}</v></c>
            </row>
        @endforeach
        {!! $heading(__('ဘဏ္ဍာရေးရှင်းတမ်း မှတ်စုများ'), 2) !!}
        @if(! $financials['hasOpeningEntries'] || ! $comparativeFinancials['hasOpeningEntries'])
            {!! $heading(__('အဖွင့်စာရင်း မထည့်ရသေးပါ။ စနစ်တွင် မှတ်တမ်းရှိသောငွေနှင့် စာရင်းညှိမှုများကိုသာ အခြေခံထားသည်။ ယခင်ပိုင်ဆိုင်မှု၊ အကြွေးနှင့် မတည်ငွေများကို အဖွင့်စာရင်းထည့်ပါ။'), 0, 90) !!}
        @endif
        @if($financials['balanceDifference'] !== 0 || $financials['cashDifference'] !== 0)
            {!! $heading(__('စာရင်းကွာဟချက် ရှိနေသည်။ စာရင်းညှိမှတ်တမ်းနှင့် အဖွင့်စာရင်းကို စစ်ဆေးပါ။'), 0, 60) !!}
        @endif
        @if($financials['closingCash'] < 0)
            {!! $heading(__('ငွေလက်ကျန် အနုတ်ဖြစ်နေသည်။ အဖွင့်ငွေ၊ ချေးငွေ၊ မတည်ငွေနှင့် ပေးချေမှုမှတ်တမ်းများ ပြည့်စုံမှုကို စစ်ဆေးပါ။'), 0, 60) !!}
        @endif
        {!! $heading(__('အခန်းခကို တည်းခိုပြီးသည့်ညအလိုက် ခွဲတွက်သည်။ အပိုဝန်ဆောင်မှုကို မှတ်တမ်းတင်ရက်အလိုက် တွက်သည်။'), 0, 60) !!}
        {!! $heading(__('မပြီးဆုံးသေးသော တည်းခိုမှုကို သတ်မှတ်ထားသော ထွက်ရက်၊ ပြီးဆုံးပြီးသော တည်းခိုမှုကို အမှန်တကယ်ထွက်ရက်ဖြင့် ညအရေအတွက်ခွဲတွက်သည်။ ယနေ့မပြီးသေးသောညကို ဝင်ငွေမသွင်းသေးပါ။'), 0, 90) !!}
        {!! $heading(__('ကြိုတင်လက်ခံထားပြီး မရရှိသေးသောဝင်ငွေကို ပေးရန်တာဝန်အဖြစ် ပြသည်။ ရရန်အကြွေးသည် ရရှိပြီးသောဝင်ငွေမှ မရသေးသည့်ငွေဖြစ်၍ ဧည့်သည်ကျန်ငွေစာရင်းနှင့် ကွာနိုင်သည်။'), 0, 90) !!}
        {!! $heading(__('ငွေသားစီးဆင်းမှုတွင် စနစ်က လက်ခံပြီးဟု မှတ်ထားသော ငွေသား၊ ဘဏ်ငွေလွှဲနှင့် ကတ်ပေးငွေများကို ပေါင်းတွက်ထားသည်။ ပုံမှန်ထွက်ငွေများကို ပေးချေပြီးအသုံးစရိတ်အဖြစ် တွက်သည်။'), 0, 90) !!}
        {!! $heading(__('ယခုလ မပြီးသေးပါက ယနေ့အထိသာ တွက်ထားပြီး ယခင်လကို လကုန်အထိ ပြထားသည်။ မှတ်တမ်းပြင်ဆင်မှုရှိလျှင် နှိုင်းယှဉ်ပမာဏများလည်း ပြန်တွက်မည်။'), 0, 90) !!}
    </sheetData>
    <mergeCells count="{{ count($merges) }}">@foreach($merges as $range)<mergeCell ref="{{ $range }}"/>@endforeach</mergeCells>
    <printOptions horizontalCentered="1"/>
    <pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>
    <pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="0"/>
</worksheet>
