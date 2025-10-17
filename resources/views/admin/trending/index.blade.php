@extends('admin.layout')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>🔥 Trending Videos</h2>
        <a href="{{ route('admin.trending.create') }}" class="btn btn-primary">+ Add Trending Video</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Thumbnail</th>
                <th>Title</th>
                <th>Description</th>
                <th>Video Link</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($trendingVideos as $video)
            <tr>
                <td><img src="{{ $video->thumbnail }}" width="100"></td>
                <td>{{ $video->title }}</td>
                <td>{{ Str::limit($video->description, 80) }}</td>
                <td><a href="{{ $video->video_url }}" target="_blank">Watch</a></td>
                <td>
                    <form action="{{ route('admin.trending.destroy', $video->id) }}" method="POST" onsubmit="return confirm('Delete this video?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-sm">🗑️ Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center">No trending videos found.</td></tr>
        @endforelse
        </tbody>
    </table>

</div>
@endsection
