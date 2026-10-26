@extends('layouts.app')

@section('title', 'Edit Event - ' . $event->title)

@section('content')
<div class="space-y-8 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                <a href="{{ route('host.dashboard') }}" class="hover:text-indigo-600 transition-colors">Host Dashboard</a>
                <span>&rsaquo;</span>
                <span class="text-indigo-600">Edit Event</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $event->title }}</h1>
                @if ($event->status === 'published')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Published</span>
                @elseif ($event->status === 'draft')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">Draft</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">{{ ucfirst($event->status) }}</span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">Update event information, schedule, dining collection rules, and promotional artwork.</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($event->status === 'published')
                <form action="{{ route('events.unpublish', $event->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors">
                        Unpublish
                    </button>
                </form>
            @else
                <form action="{{ route('events.publish', $event->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-colors">
                        Publish Live
                    </button>
                </form>
            @endif
            <a href="{{ route('host.dashboard') }}" class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-sm">
                Back to Dashboard
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm">
            <div class="font-semibold mb-1 flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Please correct the following errors:
            </div>
            <ul class="list-disc list-inside space-y-1 text-xs text-rose-700 ml-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('events.update', $event->id) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')

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
                    <input type="text" id="title" name="title" value="{{ old('title', $event->title) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="tagline" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Tagline / Subtitle</label>
                    <input type="text" id="tagline" name="tagline" value="{{ old('tagline', $event->tagline) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="slug" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Custom URL Slug</label>
                    <div class="flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-200 bg-slate-100 text-slate-500 text-xs">/events/</span>
                        <input type="text" id="slug" name="slug" value="{{ old('slug', $event->slug) }}" class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-r-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Event Description & Information</label>
                    <textarea id="description" name="description" rows="4" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">{{ old('description', $event->description) }}</textarea>
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
                    <input type="text" id="date_str" name="date_str" value="{{ old('date_str', $event->date_str) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="time_str" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Time Display <span class="text-rose-500">*</span></label>
                    <input type="text" id="time_str" name="time_str" value="{{ old('time_str', $event->time_str) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="timezone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Timezone</label>
                    <select id="timezone" name="timezone" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="America/New_York" {{ old('timezone', $event->timezone) === 'America/New_York' ? 'selected' : '' }}>America/New_York (EDT / UTC-4)</option>
                        <option value="America/Chicago" {{ old('timezone', $event->timezone) === 'America/Chicago' ? 'selected' : '' }}>America/Chicago (CDT / UTC-5)</option>
                        <option value="America/Denver" {{ old('timezone', $event->timezone) === 'America/Denver' ? 'selected' : '' }}>America/Denver (MDT / UTC-6)</option>
                        <option value="America/Los_Angeles" {{ old('timezone', $event->timezone) === 'America/Los_Angeles' ? 'selected' : '' }}>America/Los_Angeles (PDT / UTC-7)</option>
                        <option value="America/Jamaica" {{ old('timezone', $event->timezone) === 'America/Jamaica' ? 'selected' : '' }}>America/Jamaica (EST / UTC-5)</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="venue_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Venue Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="venue_name" name="venue_name" value="{{ old('venue_name', $event->venue_name) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="coordinates" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">GPS Coordinates</label>
                    <input type="text" id="coordinates" name="coordinates" value="{{ old('coordinates', $event->coordinates) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div class="sm:col-span-3">
                    <label for="venue_address" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Complete Physical Venue Address <span class="text-rose-500">*</span></label>
                    <input type="text" id="venue_address" name="venue_address" value="{{ old('venue_address', $event->venue_address) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
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
                    <input type="text" id="organizer_name" name="organizer_name" value="{{ old('organizer_name', $event->organizer_name) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="organizer_phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Phone Number <span class="text-rose-500">*</span></label>
                    <input type="tel" id="organizer_phone" name="organizer_phone" value="{{ old('organizer_phone', $event->organizer_phone) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="organizer_whatsapp" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">WhatsApp Line</label>
                    <input type="tel" id="organizer_whatsapp" name="organizer_whatsapp" value="{{ old('organizer_whatsapp', $event->organizer_whatsapp) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="organizer_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Support Email</label>
                    <input type="email" id="organizer_email" name="organizer_email" value="{{ old('organizer_email', $event->organizer_email) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
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
                        <option value="JMD" {{ old('currency', $event->currency) === 'JMD' ? 'selected' : '' }}>JMD (Jamaican Dollar - J$)</option>
                        <option value="USD" {{ old('currency', $event->currency) === 'USD' ? 'selected' : '' }}>USD (United States Dollar - US$)</option>
                    </select>
                </div>

                <div>
                    <label for="advance_order_deadline" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Advance Kitchen Deadline <span class="text-rose-500">*</span></label>
                    <input type="datetime-local" id="advance_order_deadline" name="advance_order_deadline" value="{{ old('advance_order_deadline', $event->advance_order_deadline ? $event->advance_order_deadline->format('Y-m-d\TH:i') : '') }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Publication State</label>
                    <select id="status" name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="draft" {{ old('status', $event->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="published" {{ old('status', $event->status) === 'published' ? 'selected' : '' }}>Published Live</option>
                        <option value="unpublished" {{ old('status', $event->status) === 'unpublished' ? 'selected' : '' }}>Unpublished (Hidden)</option>
                        <option value="cancelled" {{ old('status', $event->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label for="packaging_fee_jmd" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Packaging Fee (JMD)</label>
                    <div class="flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-200 bg-slate-100 text-slate-500 text-xs">J$</span>
                        <input type="number" id="packaging_fee_jmd" name="packaging_fee_jmd" value="{{ old('packaging_fee_jmd', $event->packaging_fee_jmd) }}" min="0" step="50" class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-r-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    </div>
                </div>

                <div>
                    <label for="packaging_fee_usd" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Packaging Fee (USD)</label>
                    <div class="flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-200 bg-slate-100 text-slate-500 text-xs">$</span>
                        <input type="number" id="packaging_fee_usd" name="packaging_fee_usd" value="{{ old('packaging_fee_usd', $event->packaging_fee_usd) }}" min="0" step="0.25" class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-r-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    </div>
                </div>

                <div>
                    <label for="pickup_instructions" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">Meal Collection Instructions</label>
                    <input type="text" id="pickup_instructions" name="pickup_instructions" value="{{ old('pickup_instructions', $event->pickup_instructions) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
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
                        <p class="text-xs text-slate-500">Inspect the currently assigned flyer or upload a high-resolution replacement image.</p>
                    </div>
                </div>
                @if ($event->flyer_path)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Flyer Active
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                        No Flyer
                    </span>
                @endif
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
                            <p class="text-sm font-semibold text-slate-900 mb-1">Click to replace or drag new flyer here</p>
                            <p class="text-xs text-slate-500">Supported: WebP, PNG, JPG (Max 5MB)</p>
                            <p class="text-xs text-slate-400 mt-1">Recommended: 1200 x 630px (Landscape) or 1080 x 1080px (Square)</p>
                        </div>
                    </div>
                </div>

                <!-- Current / Preview Area -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex flex-col items-center justify-center text-center min-h-[220px]">
                    @if ($event->flyer_path)
                        <div id="current-container" class="w-full space-y-3">
                            <img id="current-image" src="{{ $event->flyer_url ?? '/storage/' . $event->flyer_path }}" alt="{{ $event->title }}" class="w-full h-48 object-cover rounded-xl shadow-sm border border-slate-200">
                            <div class="flex items-center justify-between text-xs text-slate-600">
                                <span class="font-medium truncate max-w-[150px]">{{ basename($event->flyer_path) }}</span>
                                <span class="text-slate-400">{{ round(($event->flyer_size_bytes ?? 0) / 1024) }} KB</span>
                            </div>
                        </div>
                    @else
                        <div id="preview-placeholder" class="space-y-2">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-200 flex items-center justify-center text-slate-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </div>
                            <p class="text-xs font-semibold text-slate-500">No Flyer Uploaded</p>
                            <p class="text-[11px] text-slate-400">Select an artwork file above to preview.</p>
                        </div>
                    @endif

                    <div id="preview-container" class="hidden w-full space-y-3">
                        <img id="preview-image" src="" alt="Flyer Preview" class="w-full h-48 object-cover rounded-xl shadow-sm border border-slate-200">
                        <div class="flex items-center justify-between text-xs text-slate-600">
                            <span id="preview-filename" class="font-medium truncate max-w-[150px]"></span>
                            <span id="preview-filesize" class="text-slate-400"></span>
                        </div>
                        <button type="button" id="clear-flyer" class="w-full py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors">
                            Revert to Previous Image
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-slate-200">
            <div>
                @if ($event->flyer_path)
                    <button type="button" onclick="if(confirm('Remove this flyer artwork permanently?')) document.getElementById('delete-flyer-form').submit();" class="text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline">
                        Delete Flyer Artwork
                    </button>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('host.dashboard') }}" class="px-5 py-2.5 text-center text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm shadow-indigo-200">
                    Save Changes
                </button>
            </div>
        </div>
    </form>

    @if ($event->flyer_path)
        <form id="delete-flyer-form" action="{{ route('events.flyer.remove', $event->id) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('flyer-input');
        const dropZone = document.getElementById('drop-zone');
        const placeholder = document.getElementById('preview-placeholder');
        const currentContainer = document.getElementById('current-container');
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
                if (placeholder) placeholder.classList.add('hidden');
                if (currentContainer) currentContainer.classList.add('hidden');
                container.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }

        input.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                handleFile(this.files[0]);
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                img.src = '';
                container.classList.add('hidden');
                if (currentContainer) currentContainer.classList.remove('hidden');
                if (placeholder) placeholder.classList.remove('hidden');
            });
        }

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
