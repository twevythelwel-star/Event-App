<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#11172f">
    <meta property="og:site_name" content="EventBite">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/eventbite-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <title>Access denied · EventBite</title>
    <link rel="stylesheet" href="/css/admin-console.css">
    <link rel="stylesheet" href="{{ asset('css/brand.css') }}">
</head>
<body class="ac-body">
    <main class="ac-content" style="max-width:560px;margin:12vh auto 0">
        <div class="ac-card">
            <x-brand-logo variant="compact" />
            <div class="ac-card-body" style="padding:28px">
                <span class="ac-badge danger">403 · Access denied</span>
                <h1 style="margin:12px 0 6px;font-size:22px">You don't have permission to see this</h1>
                <p style="margin:0;color:var(--ac-text-2)">
                    Your account isn't allowed to open this page or make this change. If you think you should have access,
                    ask a System Administrator to check your role and assigned organizers.
                </p>
                <div class="ac-form-actions">
                    @auth
                        @can(\App\Support\AdminAuthorization::ACCESS)
                            <a class="ac-btn ac-btn-dark" href="{{ route('admin.console.overview') }}">Admin dashboard</a>
                        @endcan
                    @endauth
                    <a class="ac-btn" href="{{ url('/') }}">Go to EventBite</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
