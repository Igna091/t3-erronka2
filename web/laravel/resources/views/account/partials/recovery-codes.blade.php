{{-- The recovery codes, and buttons to keep them (account.js; hidden without JavaScript, when
     the codes can still be selected and copied by hand). --}}
<div class="codes" id="recovery-codes" data-codes-file="{{ \Illuminate\Support\Str::slug(config('app.name')) }}-recovery-codes.txt">
    @foreach ($codes as $code)
        <code>{{ $code }}</code>
    @endforeach
</div>
<div class="actions">
    <button class="btn btn--outline btn--small" type="button" data-download="recovery-codes" hidden>Download (.txt)</button>
    <button class="btn btn--outline btn--small" type="button" data-copy="recovery-codes" hidden>Copy all</button>
    <button class="btn btn--outline btn--small" type="button" data-print hidden>Print</button>
</div>
