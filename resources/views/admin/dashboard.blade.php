@extends('admin.layout')

@section('content')
<div class="container mt-4">
  <h1 class="mb-4">📊 Admin Dashboard</h1>

  <div class="row g-4">

    <!-- Total Videos -->
    <div class="col-md-3">
      <div class="card shadow-sm text-center border-0">
        <div class="card-body">
          <h6 class="text-muted">🎞 Total Videos</h6>
          <h2 class="fw-bold text-primary">{{ $videoCount ?? 0 }}</h2>
        </div>
      </div>
    </div>

    <!-- Total Users -->
    <div class="col-md-3">
      <div class="card shadow-sm text-center border-0">
        <div class="card-body">
          <h6 class="text-muted">👥 Total Users</h6>
          <h2 class="fw-bold text-success">{{ $totalUsers ?? 0 }}</h2>
        </div>
      </div>
    </div>

    <!-- Banned Users -->
    <div class="col-md-3">
      <div class="card shadow-sm text-center border-0">
        <div class="card-body">
          <h6 class="text-muted">🚫 Banned Users</h6>
          <h2 class="fw-bold text-danger">{{ $bannedUsers ?? 0 }}</h2>
        </div>
      </div>
    </div>

    <!-- Active Subscriptions -->
    <div class="col-md-3">
      <div class="card shadow-sm text-center border-0">
        <div class="card-body">
          <h6 class="text-muted">💳 Active Subscriptions</h6>
          <h2 class="fw-bold text-info">{{ $activeSubscriptions ?? 0 }}</h2>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
