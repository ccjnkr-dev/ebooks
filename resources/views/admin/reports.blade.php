
@extends('layouts.admin')
@section('title','Sales reports')
@section('admin-content')<span class="eyebrow">A CLEARER PICTURE</span><h1>Creative work, in numbers.</h1><p class="muted">Simulated sales grouped by order creation date. Refunded orders are excluded from net sales.</p><form class="filter-bar"><label>From<input type="date" name="from" value="{{ $from }}" required></label><label>To<input type="date" name="to" value="{{ $to }}" required></label><button class="button primary">Update report</button><button class="button secondary" name="export" value="csv">Export CSV</button></form><div class="stats-grid"><div class="stat-card"><span>Gross simulated sales</span><strong>₱{{ number_format($gross/100,2) }}</strong></div><div class="stat-card"><span>Refunds</span><strong>₱{{ number_format($refunds/100,2) }}</strong></div><div class="stat-card"><span>Net simulated sales</span><strong>₱{{ number_format($net/100,2) }}</strong></div><div class="stat-card"><span>Orders created</span><strong>{{ $count }}</strong></div></div><div class="panel"><h2>Daily net sales</h2><div class="sales-chart">
@forelse($daily as $day)<div class="chart-row"><span>{{ $day->day }}</span><div class="chart-track"><i style="width:{{ max(2,($day->total/max(1,$daily->max('total')))*100) }}%"></i></div><strong>₱{{ number_format($day->total/100,2) }}</strong></div>
@empty<p class="muted">No paid orders in this period.</p>
@endforelse</div></div><div class="panel table-wrap"><h2>Popular discoveries</h2><table><thead><tr><th>Work</th><th>Units</th><th>Net sales</th></tr></thead><tbody>
@forelse($popular as $item)<tr><td>{{ $item->title }}</td><td>{{ $item->units }}</td><td>₱{{ number_format($item->revenue/100,2) }}</td></tr>
@empty<tr><td colspan="3">Popular products will appear after paid orders.</td></tr>
@endforelse</tbody></table></div>
@endsection
