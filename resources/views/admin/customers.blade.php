
@extends('layouts.admin')
@section('title','Customers')
@section('admin-content')<span class="eyebrow">THE PEOPLE BEHIND THE COLLECTIONS</span><h1>Your community.</h1><div class="panel table-wrap"><table><thead><tr><th>Customer</th><th>Orders</th><th>Access records</th><th>Account</th><th>Manage</th></tr></thead><tbody>
@forelse($users as $user)<tr><td><strong>{{ $user->name }}</strong><small class="block muted">{{ $user->email }}</small></td><td>{{ $user->orders_count }}</td><td><a class="text-link" href="{{ route('admin.access',$user) }}">{{ $user->entitlements_count }} records</a></td><td>{{ $user->is_admin?'Administrator':($user->is_active?'Active':'Disabled') }}</td><td>
@unless($user->is_admin)<form method="post" action="{{ route('admin.customers.update',$user) }}">
@csrf @method('patch')<button class="text-button" name="is_active" value="{{ $user->is_active?'0':'1' }}">{{ $user->is_active?'Disable account':'Enable account' }}</button></form>
@endunless</td></tr>
@empty<tr><td colspan="5">No customer accounts yet.</td></tr>
@endforelse</tbody></table></div>{{ $users->links() }}
@endsection
