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

    <form method="POST" action="{{ route('admin.notifications.send') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label for="title" class="form-label">Notification Title</label>
            <input type="text" class="form-control" name="title" required placeholder="Enter title">
        </div>

        <div class="mb-3">
            <label for="message" class="form-label">Notification Message</label>
            <textarea class="form-control" name="message" rows="3" required placeholder="Enter message"></textarea>
        </div>

        <div class="mb-3">
            <label for="image" class="form-label">Upload Notification Image</label>
            <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/webp">
            <small class="text-muted">Optional. JPG, PNG or WebP, up to 5 MB. The image will be uploaded for the notification.</small>
            @error('image') <div class="text-danger">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="image_url" class="form-label">Or use an image URL</label>
            <input type="url" class="form-control" id="image_url" name="image_url" value="{{ old('image_url') }}" placeholder="https://www.bitdrama.io/path/to/image.jpg">
            <small class="text-muted">Use a publicly accessible HTTPS URL. Upload a file or enter a URL, not both.</small>
            @error('image_url') <div class="text-danger">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Send Notification</button>
    </form>
</div>
@endsection
