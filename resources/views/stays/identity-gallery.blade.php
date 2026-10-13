<div class="identity-heading" id="identity-gallery">
    <div><h3>မှတ်ပုံတင် / အထောက်အထား</h3><p class="muted">ပုံကိုနှိပ်၍ ဤစာမျက်နှာပေါ်တွင် အကြီးကြည့်နိုင်ပါသည်။</p></div>
    <span class="count-pill">{{ $stay->identityDocuments->count() }} / 5</span>
</div>
@if($stay->identityDocuments->isEmpty())
    <p class="muted">အထောက်အထား မတင်ရသေးပါ။</p>
@else
    <div class="identity-gallery">
        @foreach($stay->identityDocuments as $document)
            @php($previewUrl = route('stays.identity.show', [$stay, $document]))
            <a class="identity-card" href="{{ $previewUrl }}" target="_blank" rel="noopener" data-identity-preview data-kind="{{ $document->isImage() ? 'image' : 'pdf' }}" aria-label="အထောက်အထား {{ $loop->iteration }} ကို ကြည့်ရန်">
                @if($document->isImage())
                    <img src="{{ $previewUrl }}" alt="အထောက်အထားပုံ {{ $loop->iteration }}" loading="lazy">
                @else
                    <span class="identity-pdf">PDF</span>
                @endif
                <span>ဖိုင် {{ $loop->iteration }} · အကြီးကြည့်ရန် ↗</span>
            </a>
        @endforeach
    </div>
    <dialog id="identity-preview-dialog" class="identity-preview-dialog" aria-label="အထောက်အထား အကြီးကြည့်ရန်">
        <div class="identity-preview-top"><strong>အထောက်အထား ပြန်ကြည့်ရန်</strong><button type="button" class="identity-close" aria-label="ပိတ်ရန်">×</button></div>
        <img alt="အထောက်အထားပုံ အကြီးကြည့်ရန်" hidden>
        <iframe title="အထောက်အထား PDF ပြန်ကြည့်ရန်" hidden></iframe>
    </dialog>
@endif
