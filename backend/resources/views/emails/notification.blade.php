<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#142033;">
    <div style="max-width:600px;margin:0 auto;padding:24px;background:#ffffff;border:1px solid #e4e9ef;border-radius:12px;">
        {{-- The logo on its own brand band: a PNG (every mail client shows PNG), on the brand background. --}}
        <div style="margin:-24px -24px 24px;padding:20px 24px;background:#07111F;border-radius:12px 12px 0 0;">
            <x-brand-logo variant="email" style="display:block;width:180px;max-width:100%;height:auto;" />
        </div>
        <div style="font-size:15px;line-height:1.6;white-space:pre-line;">{{ $log->message }}</div>

        @if(!empty($receipt))
            @include('emails.partials.receipt', ['r' => $receipt])
        @endif
    </div>
</body>
</html>
