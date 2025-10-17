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

    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th>Thumbnail</th>
                <th>Title</th>
                <th>Description</th>
                <th>Preview</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($trendingVideos as $video)
            <tr>
                <td class="text-center">
                    @if(!empty($video->thumbnail))
                        <img src="{{ $video->thumbnail }}" width="100" class="rounded shadow-sm" alt="Thumbnail">
                    @else
                        <span class="text-muted">No Thumbnail</span>
                    @endif
                </td>

                <td>{{ $video->title }}</td>

                <td>{{ \Illuminate\Support\Str::limit($video->description, 80) }}</td>

                <td>
                    @if(!empty($video->video_url))
                        <video width="200" controls class="rounded">
                            <source src="{{ $video->video_url }}" type="video/mp4">
                            Your browser does not support video playback.
                        </video>
                    @else
                        <span class="text-muted">No Video</span>
                    @endif
                </td>

                <td>
                    <form action="{{ route('admin.trending.destroy', $video->id) }}" method="POST" onsubmit="return confirm('Delete this video?')" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-sm">🗑️ Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted">No trending videos found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
