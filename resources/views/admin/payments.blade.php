
@extends('layouts.admin')
@section('title','Payments')
@section('admin-content')<span class="eyebrow">PAYMENT DESK</span><h1>Payments, clearly tracked.</h1><p class="muted">All entries below are simulated transactions. Refunds are managed from the related order.</p><div class="panel table-wrap"><table><thead><tr><th>Reference</th><th>Customer / order</th><th>Gateway</th><th>Status</th><th>Amount</th></tr></thead><tbody>
@forelse($payments as $payment)<tr><td><span class="small">{{ $payment->reference }}</span></td><td>{{ $payment->order->user->name }}<a class="block text-link" href="{{ route('orders.show',$payment->order) }}">{{ substr($payment->order->number,0,8) }}</a></td><td>{{ $payment->gateway }}</td><td><span class="badge {{ $payment->status }}">{{ $payment->status }}</span></td><td>₱{{ number_format($payment->amount/100,2) }}</td></tr>
@empty<tr><td colspan="5">Payment records will appear after checkout.</td></tr>
@endforelse</tbody></table></div>{{ $payments->links() }}
@endsection
