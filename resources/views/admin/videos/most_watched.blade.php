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
        <th class="text-end">Views</th>
      </tr>
    </thead>
    <tbody>
      @foreach($videos as $key => $video)
      <tr>
        <td>{{ $key + 1 }}</td>
        <td><img src="{{ $video->thumbnail }}" alt="" width="100" class="rounded shadow-sm"></td>
        <td>{{ $video->title }}</td>
        <td>{{ $video->category->name ?? '-' }}</td>
        <td class="text-end"><strong>{{ number_format($video->views_count) }}</strong></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  {{ $videos->links('pagination::bootstrap-5') }}

</div>
@endsection
