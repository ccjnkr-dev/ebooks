
@extends('layouts.admin')
@section('title','Customer access')
@section('admin-content')<a class="breadcrumb" href="{{ route('admin.customers') }}">Customers</a><h1>{{ $user->name }}’s library.</h1><div class="panel table-wrap"><table><thead><tr><th>Content</th><th>Access</th><th>Action</th></tr></thead><tbody>
@forelse($entitlements as $entry)<tr><td>{{ $entry->product->title }}</td><td>{{ $entry->revoked_at?'Revoked':'Active' }}</td><td>
@unless($entry->revoked_at)<form method="post" action="{{ route('admin.access.revoke',$entry) }}" x-data @submit="if(!confirm('Revoke access to this work?')) $event.preventDefault()">
@csrf @method('delete')<button class="text-button">Revoke access</button></form>
@endunless</td></tr>
@empty<tr><td colspan="3">No purchased digital content.</td></tr>
@endforelse</tbody></table></div>
@endsection
