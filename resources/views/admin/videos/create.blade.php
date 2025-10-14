@extends('admin.layout')

@section('content')
<div class="container">
  <h2>Upload New Video with Episodes / Variants</h2>
  <form action="{{ route('admin.videos.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="mb-3">
      <label>Title</label>
      <input type="text" name="title" class="form-control" required value="{{ old('title') }}">
    </div>

    <div class="mb-3">
      <label>Description</label>
      <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
    </div>

    <div class="mb-3">
      <label>Category</label>
      <select name="category_id" class="form-select" required>
        <option value="">Select category</option>
        @foreach ($categories as $cat)
          <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
            {{ $cat->name }}
          </option>
        @endforeach
      </select>
    </div>
    <!-- ✅ Subcategory -->
    <div class="mb-3">
      <label class="form-label">Subcategory</label>
      <select name="subcategory" class="form-select">
        <option value="">Select Subcategory</option>
        @foreach (['Drama', 'Action', 'Comedy', 'Adventure', 'Romance', 'Thriller', 'Horror'] as $subcat)
          <option value="{{ $subcat }}" {{ old('subcategory') == $subcat ? 'selected' : '' }}>
            {{ $subcat }}
          </option>
        @endforeach
      </select>
      @error('subcategory') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
      <label>Thumbnail</label>
      <input type="file" name="thumbnail" class="form-control">
    </div>

    <hr>
    <h4>Video Files / Episodes</h4>
    <div id="video-files-container">
      <div class="video-file-item mb-4">
        <div class="row">
          <div class="col-md-4">
            <label>Video File</label>
            <input type="file" name="video_files[]" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label>Variant / Label (e.g. Episode 1, 720p)</label>
            <input type="text" name="variants[]" class="form-control" placeholder="Episode 1">
          </div>
          <div class="col-md-2">
            <label>Duration (seconds or hh:mm:ss)</label>
            <input type="text" name="durations[]" class="form-control" placeholder="e.g. 3600 or 01:00:00">
          </div>
          <div class="col-md-2">
            <label>DRM?</label>
            <select name="drms[]" class="form-select">
              <option value="0" selected>No</option>
              <option value="1">Yes</option>
            </select>
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger remove-file-item">X</button>
          </div>
        </div>
      </div>
    </div>
    <button type="button" class="btn btn-secondary mb-3" id="add-video-file">+ Add Another File</button>

    <button type="submit" class="btn btn-success">Upload</button>
  </form>
</div>

@push('scripts')
<script>
  document.getElementById('add-video-file').addEventListener('click', function() {
    const container = document.getElementById('video-files-container');
    const item = document.querySelector('.video-file-item');
    const clone = item.cloneNode(true);

    // Clear inputs in clone
    clone.querySelectorAll('input').forEach(input => {
      input.value = '';
    });
    clone.querySelectorAll('select').forEach(sel => {
      sel.selectedIndex = 0;
    });

    container.appendChild(clone);
  });

  document.getElementById('video-files-container').addEventListener('click', function(e) {
    if (e.target.matches('.remove-file-item')) {
      const itemDiv = e.target.closest('.video-file-item');
      if (itemDiv) {
        itemDiv.remove();
      }
    }
  });
</script>
@endpush
@endsection
