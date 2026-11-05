@extends('layouts.app')

@section('title', 'Food — ' . $event->title)
@section('page-title', 'Add food')

@section('content')

<div class="eb-checkout">
    @include('checkout._event-header')
    @include('checkout._steps', ['current' => 'Food'])

    <form method="POST" action="{{ route('checkout.food.save', $event) }}" class="eb-checkout-body" id="food-form">
        @csrf
        @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif

        @include('checkout._food-builder')

        <div class="eb-checkout-bar">
            <a href="{{ route('checkout.tickets', $event) }}" class="eb-btn eb-btn-secondary">Back</a>
            <button type="submit" class="eb-btn eb-btn-primary" onclick="if (this.form.checkValidity()) { this.setAttribute('data-saving', ''); this.setAttribute('aria-busy', 'true'); }">Continue to checkout</button>
        </div>
    </form>
</div>


@endsection
