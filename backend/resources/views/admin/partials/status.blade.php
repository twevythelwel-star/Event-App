{{-- Payment status badge: label and colour come from the stored order status. --}}
<span class="ac-badge {{ \App\Support\PaymentStatusPresenter::tone($status) }}">{{ \App\Support\PaymentStatusPresenter::label($status) }}</span>
