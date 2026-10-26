@extends('layouts.app')

@section('title', 'Event Host Applications - Administrator Operations Desk')

@section('content')
<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 pb-4 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Administrator Operations</span>
                <span class="text-xs text-slate-500">• Dual-Market Verification (JM / US)</span>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Event Host Applications</h1>
            <p class="text-slate-600 mt-1">Review organizer applications and grant Event Host privileges with an immutable audit trail.</p>
        </div>
        <div class="mt-4 md:mt-0 flex gap-3">
            <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Return to Operations Desk
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-bold text-slate-900">Pending Host Applications</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">{{ $pendingApplications->count() }} Pending</span>
            </div>
        </div>

        @if($pendingApplications->isEmpty())
            <div class="p-12 text-center text-slate-500">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-base font-semibold text-slate-700">No pending Host applications</p>
                <p class="text-sm text-slate-500 mt-1">All applicant submissions have been reviewed.</p>
            </div>
        @else
            <div class="divide-y divide-slate-200">
                @foreach($pendingApplications as $app)
                    <div class="p-6 hover:bg-slate-50 transition">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                            <div class="space-y-3 flex-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-xl font-bold text-slate-900">{{ $app->organization_name }}</h3>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $app->country === 'JM' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $app->country === 'JM' ? 'Jamaica' : 'United States' }}
                                    </span>
                                    <span class="text-xs text-slate-400">ID: #{{ $app->id }}</span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-slate-600">
                                    <div>
                                        <span class="text-slate-400 text-xs block">Applicant Name</span>
                                        <span class="font-semibold text-slate-900">{{ $app->user?->name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 text-xs block">Applicant Email</span>
                                        <span class="font-mono text-slate-800">{{ $app->user?->email }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 text-xs block">Contact Phone</span>
                                        <span class="font-mono text-slate-800">{{ $app->contact_phone }}</span>
                                    </div>
                                </div>

                                @if($app->website_or_social)
                                    <div class="text-xs text-slate-500">
                                        <span class="font-semibold text-slate-700">Online Profile:</span>
                                        <a href="{{ $app->website_or_social }}" target="_blank" rel="noopener noreferrer" class="text-emerald-600 hover:underline">{{ $app->website_or_social }}</a>
                                    </div>
                                @endif

                                @if($app->description)
                                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700">
                                        <span class="font-semibold block text-slate-900 mb-1">Programming Overview:</span>
                                        {{ $app->description }}
                                    </div>
                                @endif

                                <div class="text-xs text-slate-400">
                                    Submitted {{ $app->created_at->diffForHumans() }} ({{ $app->created_at->format('Y-m-d H:i:s T') }})
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row lg:flex-col gap-2 shrink-0">
                                <form action="{{ route('admin.host-applications.approve', $app->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" onclick="return confirm('Approve host privileges for {{ addslashes($app->organization_name) }}?')" class="w-full px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition">
                                        Approve Host Privileges
                                    </button>
                                </form>

                                <form action="{{ route('admin.host-applications.reject', $app->id) }}" method="POST" class="mt-1">
                                    @csrf
                                    <input type="hidden" name="reason" value="Application did not meet minimum operational verification standards.">
                                    <button type="submit" onclick="var r = prompt('Enter rejection reason for this applicant:', 'Application did not meet minimum operational verification standards.'); if (r) { this.form.reason.value = r; return true; } return false;" class="w-full px-4 py-2 text-sm font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition">
                                        Reject Application
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
