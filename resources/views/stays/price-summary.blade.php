<section class="price-summary" id="price-summary" aria-labelledby="price-summary-heading">
    <h2 id="price-summary-heading">{{ __('Stay price summary') }}</h2>
    <p class="quote-equation"><span id="quote-rate">—</span> {{ __('MMK / night') }} × <span id="quote-nights">—</span> {{ __('nights') }} = <strong><span id="quote-total">—</span> MMK</strong></p>
    <p id="quote-invalid" class="muted">{{ __('Select a room and valid dates to calculate the nightly price.') }}</p>
    <p id="quote-override" class="override-note" hidden>{{ __('Manual price override. Date and room changes will keep your entered charge.') }}</p>
    <dl class="price-totals" aria-live="polite" aria-atomic="true"><div><dt>{{ __('Room charge') }}</dt><dd><output id="quote-charge">—</output> MMK</dd></div>@if($showPayment ?? false)<div><dt>{{ __('Paid now') }}</dt><dd><output id="quote-paid">—</output> MMK</dd></div><div><dt>{{ __('Remaining balance') }}</dt><dd><output id="quote-balance">—</output> MMK</dd></div>@endif</dl>
    <noscript><p>{{ __('Use nightly rate × calendar nights (minimum one night), then review the charge and payment before saving.') }}</p></noscript>
</section>
