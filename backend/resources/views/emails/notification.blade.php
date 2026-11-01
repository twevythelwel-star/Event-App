<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#142033;">
    <div style="max-width:600px;margin:0 auto;padding:24px;background:#ffffff;border:1px solid #e4e9ef;border-radius:12px;">
        <x-brand-logo
            variant="compact"
            style="display:block;width:220px;max-width:100%;height:auto;margin:0 0 24px;border-radius:4px;background:#11172f;"
        />
        <div style="font-size:15px;line-height:1.6;white-space:pre-line;">{{ $log->message }}</div>

        @if(!empty($receipt))
            @include('emails.partials.receipt', ['r' => $receipt])
        @endif
    </div>
</body>
</html>
