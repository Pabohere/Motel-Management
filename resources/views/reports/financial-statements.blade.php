<section class="financial-package" aria-labelledby="financial-package-heading">
    <div class="section-heading"><h2 id="financial-package-heading">{{ __('လစဉ်ဘဏ္ဍာရေးရှင်းတမ်း ၃ စောင်') }}</h2><span class="muted">{{ $financials['asOf']->format('d M Y · H:i') }} · MMK</span></div>
    @if(! $financials['hasOpeningEntries'])<p class="financial-policy financial-setup-note">{{ __('အဖွင့်စာရင်း မထည့်ရသေးပါ။ စနစ်တွင် မှတ်တမ်းရှိသောငွေနှင့် စာရင်းညှိမှုများကိုသာ အခြေခံထားသည်။ ယခင်ပိုင်ဆိုင်မှု၊ အကြွေးနှင့် မတည်ငွေများကို အဖွင့်စာရင်းထည့်ပါ။') }}</p>@endif
    <div class="financial-highlights">
        <div><span>{{ __('အသားတင်အမြတ် / အရှုံး') }}</span><strong>{{ number_format($financials['profit']) }} K</strong></div>
        <div><span>{{ __('လကုန်ငွေလက်ကျန်') }}</span><strong>{{ number_format($financials['closingCash']) }} K</strong></div>
        <div><span>{{ __('ပိုင်ဆိုင်မှုစုစုပေါင်း') }}</span><strong>{{ number_format($financials['assets']) }} K</strong></div>
    </div>
    @if($financials['balanceDifference'] !== 0 || $financials['cashDifference'] !== 0)<p class="alert error">{{ __('စာရင်းကွာဟချက် ရှိနေသည်။ စာရင်းညှိမှတ်တမ်းနှင့် အဖွင့်စာရင်းကို စစ်ဆေးပါ။') }}</p>@endif
    @if($financials['closingCash'] < 0)<p class="alert error">{{ __('ငွေလက်ကျန် အနုတ်ဖြစ်နေသည်။ အဖွင့်ငွေ၊ ချေးငွေ၊ မတည်ငွေနှင့် ပေးချေမှုမှတ်တမ်းများ ပြည့်စုံမှုကို စစ်ဆေးပါ။') }}</p>@endif
    <div class="financial-statements-grid statement-documents">
        @foreach($financials['statements'] as $key => $statement)
            @php
                $groups = match ($key) {
                    'balance_sheet' => [0 => 'ပိုင်ဆိုင်မှုများ (Assets)', 6 => 'ပေးရန်တာဝန်များ (Liabilities)', 11 => 'ပိုင်ရှင်အရင်းအနှီး (Equity)'],
                    'income_statement' => [0 => 'ဝင်ငွေများ (Revenue)', 3 => 'အသုံးစရိတ်များ (Expenses)'],
                    'cash_flow' => [1 => 'လုပ်ငန်းလည်ပတ်မှု (Operating)', 4 => 'ရင်းနှီးမြှုပ်နှံမှု (Investing)', 7 => 'ငွေကြေးထောက်ပံ့မှု (Financing)', 10 => 'ငွေလက်ကျန်ပြန်လည်တိုက်စစ်ခြင်း'],
                };
                $reconciliationLabel = match ($key) { 'balance_sheet' => 'စာရင်းကွာဟချက်', 'cash_flow' => 'ငွေလက်ကျန်ကွာဟချက်', default => null };
                $priorRows = $comparativeFinancials['statements'][$key]['rows'];
            @endphp
            <section class="panel financial-statement {{ $key }}" aria-labelledby="{{ $key }}-heading">
                <header class="statement-document-heading">
                    <strong>{{ config('app.name') }}</strong>
                    <h2 id="{{ $key }}-heading">{{ __($statement['title']) }}</h2>
                    <p>{{ $key === 'balance_sheet' ? __('ရက်စွဲအရ လက်ကျန်') : __('စာရင်းကာလ') }}: @if($key !== 'balance_sheet'){{ $month }}-01 — @endif{{ $financials['asOf']->format('d M Y') }}</p>
                    <small>{{ __('ငွေကြေး: မြန်မာကျပ် (MMK) · အနုတ်ပမာဏကို ကွင်းစကွင်းပိတ်ဖြင့် ပြထားသည်။') }}</small>
                </header>
                <div class="statement-table-scroll" tabindex="0" role="region" aria-label="{{ __($statement['title']) }}"><table class="standard-statement-table">
                    <thead><tr><th scope="col">{{ __('စာရင်းအကြောင်းအရာ') }}</th><th scope="col">{{ __('ယခုလ') }}<small>{{ $month }}<br>{{ __('အထိ') }} {{ $financials['asOf']->format('d M Y') }}</small></th><th scope="col">{{ __('ယခင်လ') }}<small>{{ $comparisonMonth }}<br>{{ __('အထိ') }} {{ $comparativeFinancials['asOf']->format('d M Y') }}</small></th></tr></thead>
                    <tbody>
                        @foreach($statement['rows'] as $index => $row)
                            @continue($row['label'] === $reconciliationLabel)
                            @if(isset($groups[$index]))<tr class="statement-group"><th scope="rowgroup" colspan="3">{{ __($groups[$index]) }}</th></tr>@endif
                            <tr class="{{ $row['total'] ? 'statement-subtotal' : '' }} {{ $loop->last || ($reconciliationLabel && $index === count($statement['rows']) - 2) ? 'statement-grand-total' : '' }}">
                                <th scope="row">{{ __($row['label']) }}</th>
                                <td>{{ $row['amount'] < 0 ? '('.number_format(abs($row['amount'])).')' : ($row['amount'] === 0 ? '—' : number_format($row['amount'])) }}</td>
                                <td>{{ $priorRows[$index]['amount'] < 0 ? '('.number_format(abs($priorRows[$index]['amount'])).')' : ($priorRows[$index]['amount'] === 0 ? '—' : number_format($priorRows[$index]['amount'])) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
                @if($reconciliationLabel)<p class="statement-reconciliation">{{ __($reconciliationLabel) }}: {{ number_format($key === 'balance_sheet' ? $financials['balanceDifference'] : $financials['cashDifference']) }} MMK</p>@endif
                <p class="statement-footnote">{{ __('စာရင်းတွက်ချက်ပုံနှင့် အဖွင့်စာရင်းဆိုင်ရာ မှတ်စုများကို အောက်တွင် ဖော်ပြထားသည်။') }}</p>
            </section>
        @endforeach
    </div>
    <section class="financial-policy statement-notes">
        <h2>{{ __('ဘဏ္ဍာရေးရှင်းတမ်း မှတ်စုများ') }}</h2>
        <p>{{ __('အခန်းခကို တည်းခိုပြီးသည့်ညအလိုက် ခွဲတွက်သည်။ အပိုဝန်ဆောင်မှုကို မှတ်တမ်းတင်ရက်အလိုက် တွက်သည်။') }}</p>
        <p>{{ __('မပြီးဆုံးသေးသော တည်းခိုမှုကို သတ်မှတ်ထားသော ထွက်ရက်၊ ပြီးဆုံးပြီးသော တည်းခိုမှုကို အမှန်တကယ်ထွက်ရက်ဖြင့် ညအရေအတွက်ခွဲတွက်သည်။ ယနေ့မပြီးသေးသောညကို ဝင်ငွေမသွင်းသေးပါ။') }}</p>
        <p>{{ __('ကြိုတင်လက်ခံထားပြီး မရရှိသေးသောဝင်ငွေကို ပေးရန်တာဝန်အဖြစ် ပြသည်။ ရရန်အကြွေးသည် ရရှိပြီးသောဝင်ငွေမှ မရသေးသည့်ငွေဖြစ်၍ ဧည့်သည်ကျန်ငွေစာရင်းနှင့် ကွာနိုင်သည်။') }}</p>
        <p>{{ __('ငွေသားစီးဆင်းမှုတွင် စနစ်က လက်ခံပြီးဟု မှတ်ထားသော ငွေသား၊ ဘဏ်ငွေလွှဲနှင့် ကတ်ပေးငွေများကို ပေါင်းတွက်ထားသည်။ ပုံမှန်ထွက်ငွေများကို ပေးချေပြီးအသုံးစရိတ်အဖြစ် တွက်သည်။') }}</p>
        <p>{{ __('ယခုလ မပြီးသေးပါက ယနေ့အထိသာ တွက်ထားပြီး ယခင်လကို လကုန်အထိ ပြထားသည်။ မှတ်တမ်းပြင်ဆင်မှုရှိလျှင် နှိုင်းယှဉ်ပမာဏများလည်း ပြန်တွက်မည်။') }}</p>
    </section>
</section>
