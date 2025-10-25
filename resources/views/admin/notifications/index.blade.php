@extends('admin.layout')

@section('content')
<div class="container mt-5">
    <h2>Send Push Notification</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.notifications.send') }}">
        @csrf
        <div class="mb-3">
            <label for="title" class="form-label">Notification Title</label>
            <input type="text" class="form-control" name="title" required placeholder="Enter title">
        </div>

        <div class="mb-3">
            <label for="message" class="form-label">Notification Message</label>
            <textarea class="form-control" name="message" rows="3" required placeholder="Enter message"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Send Notification</button>
    </form>
</div>
@endsection
