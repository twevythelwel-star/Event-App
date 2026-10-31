@extends('layouts.app')

@section('title', 'Create New Event')

@section('content')
<div class="space-y-8 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                <a href="{{ route('host.dashboard') }}" class="hover:text-indigo-600 transition-colors">Host Dashboard</a>
                <span>&rsaquo;</span>
                <span class="text-indigo-600">Create Event</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Create New Event</h1>
            <p class="text-sm text-slate-500 mt-1">Configure event ticketing, advance food ordering deadlines, and upload high-resolution promotional artwork.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('host.dashboard') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-sm">
                Cancel
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm">
            <div class="font-semibold mb-1 flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Please fix the following validation errors:
            </div>
            <ul class="list-disc list-inside space-y-1 text-xs text-rose-700 ml-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
        @csrf

        <!-- Card 1: Event Identity -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">1</div>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Event Identity & Branding</h2>
                    <p class="text-xs text-slate-500">Public event titles, slug routing, and promotional tagline.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="sm:col-span-2">
                    <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Event Title <span class="text-rose-500">*</span></label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" required placeholder="e.g. Summer Cultural Food & Music Festival" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="tagline" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Tagline / Subtitle</label>
                    <input type="text" id="tagline" name="tagline" value="{{ old('tagline') }}" placeholder="e.g. Food, music, and traditions from our community" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="slug" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Custom URL Slug (Optional)</label>
                    <div class="flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-200 bg-slate-100 text-slate-500 text-xs">/events/</span>
                        <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="reggae-sunsplash-2026" class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-r-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Event Description & Information</label>
                    <textarea id="description" name="description" rows="4" placeholder="Detailed program schedule, parking instructions, entry guidelines, security policies, and meal collection terms..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Card 2: Schedule & Location -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">2</div>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Schedule & Location</h2>
                    <p class="text-xs text-slate-500">Dates, operational hours, and physical venue coordinates for attendee navigation.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <label for="date_str" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Date Display <span class="text-rose-500">*</span></label>
                    <input type="text" id="date_str" name="date_str" value="{{ old('date_str', 'Saturday, August 15, 2026') }}" required placeholder="Saturday, August 15, 2026" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="time_str" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Time Display <span class="text-rose-500">*</span></label>
                    <input type="text" id="time_str" name="time_str" value="{{ old('time_str') }}" required placeholder="4:00 PM - 11:30 PM local time" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="timezone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Timezone</label>
                    <select id="timezone" name="timezone" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="" @selected(old('timezone') === null || old('timezone') === '')>Automatic from city and state</option>
                        @foreach($timezones as $timezoneOption)
                            <option value="{{ $timezoneOption }}" @selected(old('timezone') === $timezoneOption)>{{ $timezoneOption }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="venue_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Venue Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="venue_name" name="venue_name" value="{{ old('venue_name') }}" required placeholder="e.g. Lummus Park Promenade & Pavilion" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <input type="hidden" name="country_code" value="US">
                <div class="sm:col-span-2">
                    <label for="street_address" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Street Address <span class="text-rose-500">*</span></label>
                    <input type="text" id="street_address" name="street_address" value="{{ old('street_address') }}" required maxlength="255" placeholder="Street number and street name" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900">
                </div>
                <div>
                    <label for="venue_building_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Building / venue name</label>
                    <input type="text" id="venue_building_name" name="venue_building_name" value="{{ old('venue_building_name') }}" maxlength="180" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900">
                </div>
                <div>
                    <label for="city" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">City or town <span class="text-rose-500">*</span></label>
                    <input type="text" id="city" name="city" value="{{ old('city') }}" required list="event-city-options" maxlength="120" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900">
                    <datalist id="event-city-options">@foreach($markets as $market)<option value="{{ $market->city }}"></option>@endforeach</datalist>
                </div>
                <div>
                    <label for="state_code" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">State / territory <span class="text-rose-500">*</span></label>
                    <select id="state_code" name="state_code" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900">
                        <option value="">Select a state or territory</option>
                        @foreach($states as $state)<option value="{{ $state->code }}" @selected(old('state_code') === $state->code)>{{ $state->name }} ({{ $state->code }})</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="zip_code" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">ZIP code <span class="text-rose-500">*</span></label>
                    <input type="text" id="zip_code" name="zip_code" value="{{ old('zip_code') }}" required inputmode="numeric" pattern="[0-9]{5}(-[0-9]{4})?" maxlength="10" placeholder="12345" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900">
                </div>
                <div>
                    <label for="latitude" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Latitude (optional)</label>
                    <input type="number" step="0.0000001" min="-90" max="90" id="latitude" name="latitude" value="{{ old('latitude') }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900">
                </div>
                <div>
                    <label for="longitude" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Longitude (optional)</label>
                    <input type="number" step="0.0000001" min="-180" max="180" id="longitude" name="longitude" value="{{ old('longitude') }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900">
                </div>
            </div>
        </div>

        <!-- Card 3: Organizer Contact & Support -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">3</div>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Host Contact & Direct Support</h2>
                    <p class="text-xs text-slate-500">Contact information printed on attendee receipts and digital passes for direct inquiries.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label for="organizer_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Host / Organization Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="organizer_name" name="organizer_name" value="{{ old('organizer_name', auth()->user()?->name) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="organizer_phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Phone Number <span class="text-rose-500">*</span></label>
                    <input type="tel" id="organizer_phone" name="organizer_phone" value="{{ old('organizer_phone', '+1 (305) 555-0199') }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="organizer_whatsapp" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">WhatsApp Line</label>
                    <input type="tel" id="organizer_whatsapp" name="organizer_whatsapp" value="{{ old('organizer_whatsapp', '+1 (305) 555-0199') }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="organizer_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Support Email</label>
                    <input type="email" id="organizer_email" name="organizer_email" value="{{ old('organizer_email', auth()->user()?->email) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
            </div>
        </div>

        <!-- Card 4: Currency, Advance Food Ordering & Packaging Fees -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">4</div>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Ordering Window & Financial Rules</h2>
                    <p class="text-xs text-slate-500">Currencies, kitchen advance cutoff deadlines, and automated packaging surcharges.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <label for="currency" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Settlement Currency <span class="text-rose-500">*</span></label>
                    <select id="currency" name="currency" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="USD" {{ old('currency', 'USD') === 'USD' ? 'selected' : '' }}>USD (United States Dollar - US$)</option>
                    </select>
                </div>

                <div>
                    <label for="advance_order_deadline" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Advance Kitchen Deadline <span class="text-rose-500">*</span></label>
                    <input type="datetime-local" id="advance_order_deadline" name="advance_order_deadline" value="{{ old('advance_order_deadline', now()->addDays(7)->format('Y-m-d\TH:i')) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Initial Publication State</label>
                    <select id="status" name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="draft" {{ old('status', 'draft') === 'draft' ? 'selected' : '' }}>Save as Draft</option>
                        <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>Publish Immediately</option>
                    </select>
                </div>

                <div>
                    <label for="packaging_fee_jmd" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Packaging Fee (JMD)</label>
                    <div class="flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-200 bg-slate-100 text-slate-500 text-xs">J$</span>
                        <input type="number" id="packaging_fee_jmd" name="packaging_fee_jmd" value="{{ old('packaging_fee_jmd', 150) }}" min="0" step="50" class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-r-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    </div>
                </div>

                <div>
                    <label for="packaging_fee_usd" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Packaging Fee (USD)</label>
                    <div class="flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-200 bg-slate-100 text-slate-500 text-xs">$</span>
                        <input type="number" id="packaging_fee_usd" name="packaging_fee_usd" value="{{ old('packaging_fee_usd', '1.00') }}" min="0" step="0.25" class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-r-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    </div>
                </div>

                <div>
                    <label for="pickup_instructions" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Meal Collection Instructions</label>
                    <input type="text" id="pickup_instructions" name="pickup_instructions" value="{{ old('pickup_instructions', 'Present digital QR pass at Hospitality Tent 2.') }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
            </div>
        </div>

        <!-- Card 5: High-Resolution Event Flyer Artwork -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">5</div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Promotional Flyer Artwork</h2>
                        <p class="text-xs text-slate-500">Upload official flyer imagery to drive conversions across public social channels and ticket discovery.</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    S3/R2 Isolated
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                <!-- Dropzone Area -->
                <div class="lg:col-span-2 space-y-4">
                    <div id="drop-zone" class="relative border-2 border-dashed border-slate-300 rounded-2xl p-8 text-center hover:border-indigo-400 hover:bg-indigo-50/20 transition-all cursor-pointer">
                        <input type="file" id="flyer-input" name="flyer" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="flex flex-col items-center pointer-events-none">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3 shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-900 mb-1">Click to upload or drag and drop flyer</p>
                            <p class="text-xs text-slate-500">Supported: WebP, PNG, JPG (RGB Color, Max 5MB)</p>
                            <p class="text-xs text-slate-400 mt-1">Recommended resolution: 1200 x 630px or 1080 x 1080px (Square)</p>
                        </div>
                    </div>

                    <!-- File specs checklist -->
                    <div class="grid grid-cols-3 gap-3 text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>Max 5 MB file size</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>Min 300 &times; 200px</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>Max 4500 &times; 4500px</span>
                        </div>
                    </div>
                </div>

                <!-- Preview Area -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex flex-col items-center justify-center text-center min-h-[220px]">
                    <div id="preview-placeholder" class="space-y-2">
                        <div class="w-12 h-12 mx-auto rounded-full bg-slate-200 flex items-center justify-center text-slate-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </div>
                        <p class="text-xs font-semibold text-slate-500">Live Artwork Preview</p>
                        <p class="text-[11px] text-slate-400">Select an image to inspect aspect ratio and render fidelity prior to submission.</p>
                    </div>

                    <div id="preview-container" class="hidden w-full space-y-3">
                        <img id="preview-image" src="" alt="Flyer Preview" class="w-full h-48 object-cover rounded-xl shadow-sm border border-slate-200">
                        <div class="flex items-center justify-between text-xs text-slate-600">
                            <span id="preview-filename" class="font-medium truncate max-w-[150px]"></span>
                            <span id="preview-filesize" class="text-slate-400"></span>
                        </div>
                        <button type="button" id="clear-flyer" class="w-full py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors">
                            Clear Image
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-6 border-t border-slate-200">
            <a href="{{ route('host.dashboard') }}" class="w-full sm:w-auto px-5 py-2.5 text-center text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                Cancel
            </a>
            <button type="submit" name="status" value="draft" class="w-full sm:w-auto px-5 py-2.5 text-sm font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                Save as Draft
            </button>
            <button type="submit" name="status" value="published" class="w-full sm:w-auto px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm shadow-indigo-200">
                Publish Event
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('flyer-input');
        const dropZone = document.getElementById('drop-zone');
        const placeholder = document.getElementById('preview-placeholder');
        const container = document.getElementById('preview-container');
        const img = document.getElementById('preview-image');
        const filename = document.getElementById('preview-filename');
        const filesize = document.getElementById('preview-filesize');
        const clearBtn = document.getElementById('clear-flyer');

        function handleFile(file) {
            if (!file) return;
            if (!file.type.match('image.*')) {
                alert('Please upload an image file (JPG, PNG, or WebP).');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('File exceeds the 5MB size limit.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result;
                filename.textContent = file.name;
                filesize.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                placeholder.classList.add('hidden');
                container.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }

        input.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                handleFile(this.files[0]);
            }
        });

        clearBtn.addEventListener('click', function () {
            input.value = '';
            img.src = '';
            placeholder.classList.remove('hidden');
            container.classList.add('hidden');
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.add('border-indigo-500', 'bg-indigo-50/30');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-indigo-500', 'bg-indigo-50/30');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                input.files = files;
                handleFile(files[0]);
            }
        });
    });
</script>
@endsection
