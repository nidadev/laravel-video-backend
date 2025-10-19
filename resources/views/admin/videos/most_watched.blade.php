@extends('admin.layout')

@section('content')
<div class="container mt-4">
  <h2 class="mb-4">🔥 Most Watched Videos</h2>

  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>#</th>
        <th>Thumbnail</th>
        <th>Title</th>
        <th>Category</th>
        <th>Views</th>
        <th>Last Viewed</th>
      </tr>
    </thead>
    <tbody>
      @foreach($videos as $key => $video)
      <tr>
        <td>{{ $key + 1 }}</td>
        <td><img src="{{ $video->thumbnail }}" alt="" width="100" class="rounded shadow-sm"></td>
        <td>{{ $video->title }}</td>
        <td>{{ $video->category->name ?? '-' }}</td>
        <td><strong>{{ $video->views_count }}</strong></td>
        <td>{{ optional($video->views->last())->created_at?->diffForHumans() ?? '-' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
