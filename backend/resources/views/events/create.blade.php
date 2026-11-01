@extends('layouts.app')

@section('title', 'Create event — EventBite')
@section('page-title', 'Create event')

@section('content')
<form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" class="eb-stack" style="gap: var(--layout-section-gap);">
    @csrf
    <x-page-header title="Create event"
                   description="Save it as a draft to finish later, or publish it now to start selling tickets."
                   :breadcrumbs="['Host dashboard' => route('host.dashboard'), 'Create event' => null]" />

    @if($errors->any())
        <x-alert type="error" title="Please check the highlighted fields">{{ $errors->count() === 1 ? $errors->first() : $errors->count() . ' fields need attention.' }}</x-alert>
    @endif

    @include('events.partials.form', ['event' => null])

    <div class="eb-cluster" style="justify-content: flex-end;">
        <x-button variant="ghost" :href="route('host.dashboard')">Cancel</x-button>
        <button type="submit" name="status" value="draft" class="eb-btn eb-btn-secondary">Save as draft</button>
        <button type="submit" name="status" value="published" class="eb-btn eb-btn-primary">Publish event</button>
    </div>
</form>
@endsection
