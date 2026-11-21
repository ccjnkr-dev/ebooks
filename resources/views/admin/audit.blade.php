
@extends('layouts.admin')
@section('title','Activity log')
@section('admin-content')<span class="eyebrow">A RECORD OF THE DETAILS</span><h1>Studio activity.</h1><div class="panel table-wrap"><table><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Subject</th></tr></thead><tbody>
@forelse($events as $event)<tr><td>{{ $event->created_at->format('M j, Y g:i a') }}</td><td>{{ $event->user?->name ?? 'System' }}</td><td>{{ $event->action }}</td><td>{{ $event->subject }}</td></tr>
@empty<tr><td colspan="4">Admin activity will be recorded here.</td></tr>
@endforelse</tbody></table></div>{{ $events->links() }}
@endsection
