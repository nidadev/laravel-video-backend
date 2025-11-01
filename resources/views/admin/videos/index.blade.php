@extends('admin.layout')

@section('content')
<div class="container mt-4">

  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>🎬 All Videos</h2>
    <a href="{{ route('admin.videos.presigned.create') }}" class="btn btn-primary">
      ➕ Add New Video
    </a>
  </div>

  <!-- Flash message -->
  @if(session('success'))
    <div class="alert alert-success">
      {{ session('success') }}
    </div>
  @endif

  <!-- Video Table -->
  <div class="card shadow-sm">
    <div class="card-body">
      <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Thumbnail</th>
            <th>Title</th>
            <th>Category</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($videos as $video)
            <tr>
              <td>{{ $video->id }}</td>
              <td>
                @if($video->thumbnail)
                  <img src="{{ $video->thumbnail }}" alt="Thumbnail" width="80" class="rounded">
                @else
                  <span class="text-muted">No Thumbnail</span>
                @endif
              </td>
              <td>{{ $video->title }}</td>
              <td>{{ $video->category->name ?? '—' }}</td>
              <td>
                @php
                  $badge = [
                    'ready' => 'success',
                    'published' => 'primary',
                    'processing' => 'warning',
                    'disabled' => 'secondary'
                  ][$video->status] ?? 'light';
                @endphp
                <span class="badge bg-{{ $badge }}">{{ ucfirst($video->status) }}</span>
              </td>
              <td>{{ $video->created_at->format('Y-m-d') }}</td>
              <td>
                <a href="{{ route('admin.videos.edit', $video->id) }}" class="btn btn-sm btn-outline-primary">
                  ✏️ Edit
                </a>
                <form action="{{ route('admin.videos.destroy', $video->id) }}" method="POST" style="display:inline-block;">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this video?')">
                    🗑️ Delete
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">No videos uploaded yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
      <!-- Pagination -->
<div class="mt-3 d-flex justify-content-center">
  {{ $videos->links('pagination::bootstrap-5') }}
</div>
    </div>
  </div>
</div>
@endsection
