@php
    use App\Support\AdminAuthorization as Admin;

    $adminUser = Auth::user();
    // Navigation is built from the same Gate abilities the controllers enforce.
    $nav = collect([
        ['route' => 'admin.console.overview', 'match' => 'admin.console.overview', 'label' => 'Dashboard', 'icon' => 'dashboard', 'can' => Admin::ACCESS],
        ['route' => 'admin.console.users.index', 'match' => 'admin.console.users.*', 'label' => 'Users', 'icon' => 'users', 'can' => Admin::USERS_VIEW],
        ['route' => 'admin.console.roles.index', 'match' => 'admin.console.roles.*', 'label' => 'Roles & Permissions', 'icon' => 'roles', 'can' => Admin::ROLES_VIEW],
        ['route' => 'admin.console.events.index', 'match' => 'admin.console.events.*', 'label' => 'Events', 'icon' => 'events', 'can' => Admin::EVENTS_VIEW],
        ['route' => 'admin.console.locations.index', 'match' => 'admin.console.locations.*', 'label' => 'Locations', 'icon' => 'events', 'can' => Admin::SETTINGS_MANAGE],
        ['route' => 'admin.console.orders.index', 'match' => 'admin.console.orders.*', 'label' => 'Orders & Payments', 'icon' => 'payment', 'can' => Admin::PAYMENTS_VIEW],
        ['route' => 'admin.console.reports.index', 'match' => 'admin.console.reports.*', 'label' => 'Reports', 'icon' => 'reports', 'can' => Admin::REPORTS_VIEW],
        ['route' => 'admin.console.audit.index', 'match' => 'admin.console.audit.*', 'label' => 'Audit Logs', 'icon' => 'audit', 'can' => Admin::AUDIT_VIEW],
        ['route' => 'admin.console.settings.edit', 'match' => 'admin.console.settings.*', 'label' => 'Platform Settings', 'icon' => 'settings', 'can' => Admin::SETTINGS_MANAGE],
    ])->filter(fn ($item) => Gate::allows($item['can']));

    $roleLabel = Admin::isSystemAdmin($adminUser) ? 'System Administrator' : 'Administrator';
    $initials = collect(preg_split('/\s+/', trim($adminUser->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#11172f">
    <meta property="og:site_name" content="EventBite">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/eventbite-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <title>@yield('title', 'Administration') · EventBite Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/admin-console.css">
    <link rel="stylesheet" href="{{ asset('css/brand.css') }}">
</head>
<body class="ac-body">
<a class="sr-only" href="#ac-main">Skip to content</a>
<div class="ac-shell" id="acShell" data-nav-open="false">
    <aside class="ac-sidebar" aria-label="Administration">
        <a class="ac-brand" href="{{ route('admin.console.overview') }}">
            <span>
                <x-brand-logo variant="compact" />
                <span class="ac-brand-sub">Administration</span>
            </span>
        </a>

        <div class="ac-nav-label">Platform</div>
        <nav class="ac-nav">
            @foreach($nav as $item)
                <a href="{{ route($item['route']) }}" @if(request()->routeIs($item['match'])) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" size="md" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="ac-sidebar-foot">
            @if(Admin::organizerScope($adminUser) === null)
                Access: all organizers
            @else
                Access: {{ count(Admin::organizerScope($adminUser)) }} assigned organizer{{ count(Admin::organizerScope($adminUser)) === 1 ? '' : 's' }}
            @endif
            <br><a href="{{ route('home') }}">Back to EventBite</a>
        </div>
    </aside>

    <div class="ac-main">
        <header class="ac-topbar">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
                <button type="button" class="ac-btn ac-btn-sm ac-menu-toggle" aria-controls="acShell" aria-expanded="false" id="acMenuToggle">
                    <x-icon name="menu-bars" size="sm" /><span>Menu</span>
                </button>
                <nav class="ac-breadcrumbs" aria-label="Breadcrumb">
                    <a href="{{ route('admin.console.overview') }}">Admin</a>
                    @hasSection('breadcrumbs')
                        @yield('breadcrumbs')
                    @endif
                </nav>
            </div>

            <details class="ac-account">
                <summary aria-label="Account menu">
                    <span class="ac-avatar" aria-hidden="true">{{ $initials }}</span>
                    <span class="ac-account-text">
                        <span class="ac-account-name">{{ $adminUser->name }}</span>
                        <span class="ac-account-role">{{ $roleLabel }}</span>
                    </span>
                </summary>
                <div class="ac-account-menu" role="menu">
                    <div style="padding:8px 10px;font-size:12px;color:var(--ac-text-3)">Signed in as<br><strong style="color:var(--ac-text)">{{ $adminUser->email }}</strong></div>
                    <hr>
                    <a href="{{ route('settings.show') }}" role="menuitem">My account settings</a>
                    <a href="{{ route('home') }}" role="menuitem">Back to EventBite</a>
                    <hr>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" role="menuitem">Sign out</button>
                    </form>
                </div>
            </details>
        </header>

        <main class="ac-content" id="ac-main" tabindex="-1">
            <div class="ac-page-head">
                <div>
                    <h1>@yield('heading')</h1>
                    @hasSection('subheading')<p>@yield('subheading')</p>@endif
                </div>
                @hasSection('actions')<div style="display:flex;gap:8px;flex-wrap:wrap">@yield('actions')</div>@endif
            </div>

            @if(session('success'))
                <div class="ac-alert success" role="status"><x-icon name="check" size="sm" /><span>{{ session('success') }}</span></div>
            @endif
            @if(session('error'))
                <div class="ac-alert error" role="alert"><x-icon name="alert" size="sm" /><span>{{ session('error') }}</span></div>
            @endif
            @if($errors->any())
                <div class="ac-alert error" role="alert">
                    <x-icon name="alert" size="sm" />
                    <div><strong>Please fix the following:</strong>
                        <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    (function () {
        var shell = document.getElementById('acShell');
        var toggle = document.getElementById('acMenuToggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                var open = shell.getAttribute('data-nav-open') !== 'true';
                shell.setAttribute('data-nav-open', open ? 'true' : 'false');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
        // One submission per form: stops double-posting a status change or payment decision.
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            var button = form.querySelector('button[type="submit"]');
            if (button && button.dataset.loadingText) {
                button.dataset.originalText = button.textContent;
                button.textContent = button.dataset.loadingText;
                button.setAttribute('aria-busy', 'true');
            }
        });
        // Going Back can restore a page whose form is still marked as submitting; make it usable again.
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form[data-submitting="true"]').forEach(function (form) {
                delete form.dataset.submitting;
                var button = form.querySelector('button[type="submit"]');
                if (button && button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                    button.removeAttribute('aria-busy');
                }
            });
        });
    })();
</script>
</body>
</html>
