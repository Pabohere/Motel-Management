<div class="identity-heading" id="identity-gallery">
    <div><h3>{{ __('မှတ်ပုံတင် / အထောက်အထား') }}</h3><p class="muted">{{ __('ပုံကိုနှိပ်၍ ဤစာမျက်နှာပေါ်တွင် အကြီးကြည့်နိုင်ပါသည်။') }}</p></div>
    <span class="count-pill">{{ $stay->identityDocuments->count() }} / 5</span>
</div>
@if($stay->identityDocuments->isEmpty())
    <p class="muted">{{ __('အထောက်အထား မတင်ရသေးပါ။') }}</p>
@else
    <div class="identity-gallery">
        @foreach($stay->identityDocuments as $document)
            @php($previewUrl = route('stays.identity.show', [$stay, $document]))
            <a class="identity-card" href="{{ $previewUrl }}" target="_blank" rel="noopener" data-identity-preview data-kind="{{ $document->isImage() ? 'image' : 'pdf' }}" aria-label="{{ __('အထောက်အထား') }} {{ $loop->iteration }} {{ __('အကြီးကြည့်ရန်') }}">
                @if($document->isImage())
                    <img src="{{ $previewUrl }}" alt="{{ __('အထောက်အထားပုံ') }} {{ $loop->iteration }}" loading="lazy">
                @else
                    <span class="identity-pdf">PDF</span>
                @endif
                <span>{{ __('ဖိုင်') }} {{ $loop->iteration }} · {{ __('အကြီးကြည့်ရန်') }}</span>
            </a>
        @endforeach
    </div>
    <dialog id="identity-preview-dialog" class="identity-preview-dialog" aria-label="{{ __('အထောက်အထား အကြီးကြည့်ရန်') }}">
        <div class="identity-preview-top"><strong>{{ __('အထောက်အထား ပြန်ကြည့်ရန်') }}</strong><button type="button" class="identity-close" aria-label="{{ __('ပိတ်ရန်') }}">×</button></div>
        <img alt="{{ __('အထောက်အထားပုံ') }} {{ __('အကြီးကြည့်ရန်') }}" hidden>
        <iframe title="{{ __('အထောက်အထား PDF ပြန်ကြည့်ရန်') }}" hidden></iframe>
    </dialog>
@endif
