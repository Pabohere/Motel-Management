<section class="panel top-space financial-entry-panel" id="financial-entries">
    <h2>{{ __('စာရင်းညှိမှတ်တမ်း') }}</h2>
    <p class="muted">{{ __('ဧည့်သည်လက်ခံငွေနှင့် ပုံမှန်ထွက်ငွေများကို အလိုအလျောက်တွက်ထားပြီးဖြစ်၍ ထပ်မထည့်ပါနှင့်။ မှားယွင်းသောစာရင်းကို ဖျက်မည့်အစား Debit / Credit ပြောင်းပြန်ဖြင့် ပြန်ညှိနိုင်သည်။') }}</p>
    <details class="financial-entry-help"><summary>{{ __('စာရင်းသွင်းနမူနာများ') }}</summary>
        <ul>
            <li>{{ __('မတည်ငွေထည့်ခြင်း: Debit လက်ငင်းငွေ၊ Credit ပိုင်ရှင်မတည်ငွေ။') }}</li>
            <li>{{ __('ချေးငွေရရှိခြင်း: Debit လက်ငင်းငွေ၊ Credit ချေးငွေပေးရန်။ ချေးငွေရင်းပြန်ဆပ်လျှင် ပြောင်းပြန်သွင်းပါ။ အတိုးကို အသုံးစရိတ်အဖြစ် သီးခြားသွင်းပါ။') }}</li>
            <li>{{ __('ပစ္စည်းဝယ်ယူခြင်း: Debit ပုံသေပိုင်ဆိုင်မှု၊ Credit လက်ငင်းငွေ။ အကြွေးဝယ်လျှင် Credit ပေးရန်အကြွေး။') }}</li>
            <li>{{ __('ငွေမပေးရသေးသောစရိတ်: Debit လုပ်ငန်းအသုံးစရိတ်၊ Credit ပေးရန်အကြွေး။ ပေးချေချိန်တွင် Debit ပေးရန်အကြွေး၊ Credit လက်ငင်းငွေ။') }}</li>
            <li>{{ __('တန်ဖိုးလျော့: Debit တန်ဖိုးလျော့စရိတ်၊ Credit စုဆောင်းတန်ဖိုးလျော့။') }}</li>
            <li>{{ __('အဖွင့်ပိုင်ဆိုင်မှု: Debit သက်ဆိုင်ရာပိုင်ဆိုင်မှု၊ Credit အဖွင့်ပိုင်ရှင်အရင်းအနှီး။ အဖွင့်အကြွေးအတွက် Debit အဖွင့်ပိုင်ရှင်အရင်းအနှီး၊ Credit သက်ဆိုင်ရာပေးရန်တာဝန်။ စနစ်ထဲရှိပြီးသား ပမာဏကို ထပ်မထည့်ပါနှင့်။') }}</li>
        </ul>
    </details>
    <form method="post" action="{{ route('financial-entries.store') }}" class="financial-entry-form">
        @csrf
        <div class="form-grid">
            <label>{{ __('ရက်စွဲ') }}<input type="date" name="entry_date" max="{{ today()->toDateString() }}" value="{{ old('entry_date', today()->toDateString()) }}" required></label>
            <label>{{ __('အကြောင်းအရာ') }}<input name="description" maxlength="255" value="{{ old('description') }}" required></label>
            <label>Debit<select name="debit_account" required><option value="">{{ __('အကောင့်ရွေးရန်') }}</option>@foreach($financialAccounts as $key => $label)<option value="{{ $key }}" @selected(old('debit_account') === $key)>{{ __($label) }}</option>@endforeach</select></label>
            <label>Credit<select name="credit_account" required><option value="">{{ __('အကောင့်ရွေးရန်') }}</option>@foreach($financialAccounts as $key => $label)<option value="{{ $key }}" @selected(old('credit_account') === $key)>{{ __($label) }}</option>@endforeach</select></label>
            <label>{{ __('ပမာဏ (MMK)') }}<input type="number" name="amount" min="1" max="1000000000000" value="{{ old('amount') }}" required></label>
            <label>{{ __('ငွေသားစီးဆင်းမှုအမျိုးအစား') }}<select name="cash_flow_category"><option value="">{{ __('အကောင့်အလိုက် အလိုအလျောက်') }}</option>@foreach(['operating' => 'လုပ်ငန်းလည်ပတ်မှု', 'investing' => 'ရင်းနှီးမြှုပ်နှံမှု', 'financing' => 'ငွေကြေးထောက်ပံ့မှု'] as $key => $label)<option value="{{ $key }}" @selected(old('cash_flow_category') === $key)>{{ __($label) }}</option>@endforeach</select></label>
        </div>
        <label class="financial-opening-checkbox"><input type="checkbox" name="is_opening" value="1" @checked(old('is_opening'))>{{ __('အဖွင့်စာရင်း — လ၏ပထမရက်ကို ရွေးပါ။') }}</label>
        <button class="btn">{{ __('စာရင်းညှိမှတ်တမ်း သိမ်းရန်') }}</button>
    </form>
    <div class="monthly-table-scroll"><table><thead><tr><th>{{ __('ရက်စွဲ') }}</th><th>{{ __('အကြောင်းအရာ') }}</th><th>Debit</th><th>Credit</th><th>{{ __('ပမာဏ (MMK)') }}</th><th>{{ __('တာဝန်ခံ') }}</th></tr></thead><tbody>
        @forelse($financialEntries as $entry)
            <tr><td>{{ $entry->entry_date }}@if($entry->is_opening)<small>{{ __('အဖွင့်စာရင်း') }}</small>@endif</td><td>{{ $entry->description }}</td><td>{{ __($financialAccounts[$entry->debit_account]) }}</td><td>{{ __($financialAccounts[$entry->credit_account]) }}</td><td>{{ number_format($entry->amount) }}</td><td>{{ $entry->creator_name }}</td></tr>
        @empty<tr><td colspan="6" class="empty">{{ __('ဤလအတွက် မှတ်တမ်း မရှိသေးပါ။') }}</td></tr>@endforelse
    </tbody></table></div>
</section>
