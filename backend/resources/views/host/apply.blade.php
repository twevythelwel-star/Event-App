@extends('layouts.app')

@section('title', 'Apply for Event Host Privileges - EventBite')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <a href="{{ route('attendee.dashboard') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-1 mb-3">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Attendee Dashboard
        </a>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Apply for Event Host Privileges</h1>
        <p class="text-slate-600 mt-2">Publish event flyers, sell digital tickets, and manage advance food orders across the United States.</p>
    </div>

    @if(session('status'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm">
            <div class="font-bold mb-1">Please correct the following:</div>
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($existingApplication && $existingApplication->isPending())
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 mb-8 text-amber-900">
            <div class="flex items-start gap-4">
                <div class="p-3 bg-amber-100 rounded-xl text-amber-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-200 text-amber-900 mb-2">Application Pending Review</span>
                    <h2 class="text-xl font-bold text-amber-950">Your Host Request for "{{ $existingApplication->organization_name }}" is Under Review</h2>
                    <p class="text-amber-800 text-sm mt-1">Submitted on {{ $existingApplication->created_at->format('M j, Y g:i A') }}. Our operations desk reviews all applications to ensure organizer compliance and attendee safety. You will retain your Attendee access while your request is processed.</p>
                </div>
            </div>
        </div>
    @elseif($existingApplication && $existingApplication->isRejected())
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6 mb-8 text-rose-900">
            <div class="flex items-start gap-4">
                <div class="p-3 bg-rose-100 rounded-xl text-rose-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-200 text-rose-900 mb-2">Application Not Approved</span>
                    <h2 class="text-xl font-bold text-rose-950">Prior Host Request for "{{ $existingApplication->organization_name }}"</h2>
                    <p class="text-rose-800 text-sm mt-1 font-medium">Reason: {{ $existingApplication->rejection_reason }}</p>
                    <p class="text-rose-700 text-xs mt-2">You may review your details and submit an updated application below.</p>
                </div>
            </div>
        </div>
    @endif

    @if(!$existingApplication || !$existingApplication->isPending())
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 sm:p-8">
                <form action="{{ route('host.apply.submit') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <label for="organization_name" class="block text-sm font-semibold text-slate-900">Organization / Brand / Promoter Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="organization_name" name="organization_name" value="{{ old('organization_name', $existingApplication?->organization_name) }}" required minlength="3" placeholder="e.g. South Florida Cultural Foundation" class="mt-2 block w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-slate-500 mt-1">This name will appear on published event flyers, tickets, and official customer receipts.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="country" class="block text-sm font-semibold text-slate-900">Primary Country of Operation <span class="text-rose-500">*</span></label>
                            <select id="country" name="country" required class="mt-2 block w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="US" {{ old('country', $existingApplication?->country ?? 'US') === 'US' ? 'selected' : '' }}>United States (USD & Card Support)</option>
                                <option value="JM" {{ old('country', $existingApplication?->country) === 'JM' ? 'selected' : '' }}>Jamaica (International Expansion — Coming Soon)</option>
                            </select>
                        </div>

                        <div>
                            <label for="contact_phone" class="block text-sm font-semibold text-slate-900">Operational Contact Phone <span class="text-rose-500">*</span></label>
                            <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $existingApplication?->contact_phone ?? Auth::user()->phone) }}" required placeholder="+1 (555) 019-2834" class="mt-2 block w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label for="website_or_social" class="block text-sm font-semibold text-slate-900">Website or Official Social Profile <span class="text-slate-400 font-normal">(Optional)</span></label>
                        <input type="text" id="website_or_social" name="website_or_social" value="{{ old('website_or_social', $existingApplication?->website_or_social) }}" placeholder="e.g. instagram.com/islandevents or https://events.com" class="mt-2 block w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-slate-900">Event Programming & Operations Summary</label>
                        <textarea id="description" name="description" rows="4" placeholder="Briefly describe the types of events, frequency, and estimated attendee capacity you plan to host..." class="mt-2 block w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $existingApplication?->description) }}</textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex items-center justify-between">
                        <a href="{{ route('attendee.dashboard') }}" class="px-5 py-2.5 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                        <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm rounded-xl transition duration-150">
                            Submit Host Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
