@extends('admin.layout')

@section('content')
<div class="container mt-4">
  <h2>👥 Manage Users</h2>
  <table class="table table-striped mt-3">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Phone / Email</th>
        <th>Status</th>
        <th>Plan</th>
        <th>Subscription Ends</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($users as $user)
      <tr>
        <td>{{ $user->id }}</td>
        <td>{{ $user->name }}</td>
        <td>{{ $user->email ?? $user->phone }}</td>
        <td>
          <span class="badge bg-{{ $user->status === 'active' ? 'success' : 'danger' }}">
            {{ ucfirst($user->status) }}
          </span>
        </td>
        <td>{{ $user->subscriptions->last()->plan->name ?? 'Free' }}</td>
        <td>{{ optional($user->subscriptions->last())->end_date }}</td>
        <td>
          @if($user->status === 'active')
            <form action="{{ route('admin.users.ban', $user->id) }}" method="POST" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-danger">Ban</button>
            </form>
          @else
            <form action="{{ route('admin.users.unban', $user->id) }}" method="POST" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-success">Unban</button>
            </form>
          @endif

          <!-- Upgrade -->
          <form action="{{ route('admin.users.upgrade', $user->id) }}" method="POST" class="d-inline">
            @csrf
            <select name="plan_id" class="form-select form-select-sm d-inline-block" style="width:120px;">
              @foreach(\App\Models\Plan::all() as $plan)
                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
              @endforeach
            </select>
            <button class="btn btn-sm btn-primary">Upgrade</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{ $users->links() }}
</div>
@endsection