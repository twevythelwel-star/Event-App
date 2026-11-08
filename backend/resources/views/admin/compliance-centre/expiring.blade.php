@extends('layouts.admin')

@section('title', 'Expiring documents')
@section('heading', 'Expiring Documents')
@section('subheading', 'Verified permits and licences, and approved host IDs, that have expired or expire soon. An expired document blocks publishing again.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Expiring</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'expiring', 'withStateFilter' => true])
    @unless($canIdentity)<div class="ac-alert info" role="note"><div>Host ID expiry dates are shown only to administrators granted identity document access.</div></div>@endunless
    <section class="ac-card">@include('admin.compliance-centre._expiring-table', ['rows' => $rows])</section>
@endsection
