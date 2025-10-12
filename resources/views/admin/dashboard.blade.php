@extends('admin.layout')

@section('content')
  <div class="container">
    <h1 class="mb-4">Welcome, Admin 👋</h1>
    <div class="row">
      <div class="col-md-4">
        <div class="card text-center">
          <div class="card-body">
            <h5>Total Videos</h5>
            <h2>{{ $videoCount ?? 0 }}</h2>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
