@extends('admin.layout')

@section('content')
<div class="container mt-4">
  <h2 class="mb-4">✏️ Edit Video</h2>

  <!-- Back Button -->
  <a href="{{ route('admin.videos') }}" class="btn btn-secondary mb-3">← Back to Videos</a>

  <!-- Update Form -->
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.videos.update', $video->id) }}" enctype="multipart/form-data">
        @csrf

        <!-- Title -->
        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $video->title) }}" required>
          @error('title') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <!-- Description -->
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4" required>{{ old('description', $video->description) }}</textarea>
          @error('description') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <!-- Category -->
        <div class="mb-3">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select" required>
            <option value="">Select Category</option>
            @foreach($categories as $category)
              <option value="{{ $category->id }}" {{ $category->id == old('category_id', $video->category_id) ? 'selected' : '' }}>
                {{ $category->name }}
              </option>
            @endforeach
          </select>
          @error('category_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <!-- Status -->
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            @foreach(['processing', 'ready', 'published', 'disabled'] as $status)
              <option value="{{ $status }}" {{ $video->status == $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
            @endforeach
          </select>
        </div>

        <!-- Thumbnail -->
        <div class="mb-3">
          <label class="form-label">Thumbnail</label>
          @if($video->thumbnail)
            <div class="mb-2">
              <img src="{{ $video->thumbnail }}" alt="Thumbnail" width="150" class="rounded">
            </div>
          @endif
          <input type="file" name="thumbnail" class="form-control">
        </div>

        <!-- Existing Video Files -->
        <h4 class="mt-4">Existing Video Files / Episodes</h4>
        <div id="existing-video-files-container">
          @foreach($video->files as $index => $file)
            <div class="video-file-item mb-3 border rounded p-3">
              <input type="hidden" name="existing_files[{{ $file->id }}][id]" value="{{ $file->id }}">

              <div class="mb-2">
                <video width="320" height="180" controls>
                  <source src="{{ $file->file_url }}" type="video/mp4">
                  Your browser does not support the video tag.
                </video>
              </div>

              <div class="row g-2">
                <div class="col-md-4">
                  <label>Variant / Label</label>
                  <input type="text" name="existing_files[{{ $file->id }}][variant]" class="form-control" value="{{ old("existing_files.{$file->id}.variant", $file->variant) }}">
                </div>

                <div class="col-md-2">
                  <label>Duration (seconds or hh:mm:ss)</label>
                  <input type="text" name="existing_files[{{ $file->id }}][duration]" class="form-control" value="{{ old("existing_files.{$file->id}.duration", $file->duration) }}">
                </div>

                <div class="col-md-2">
                  <label>DRM?</label>
                  <select name="existing_files[{{ $file->id }}][drm]" class="form-select">
                    <option value="0" {{ old("existing_files.{$file->id}.drm", $file->drm) == 0 ? 'selected' : '' }}>No</option>
                    <option value="1" {{ old("existing_files.{$file->id}.drm", $file->drm) == 1 ? 'selected' : '' }}>Yes</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label>Replace Video File (optional)</label>
                  <input type="file" name="existing_files[{{ $file->id }}][file]" class="form-control" accept="video/*">
                </div>

                <div class="col-md-1 d-flex align-items-end">
                  <button type="button" class="btn btn-danger btn-sm remove-existing-file">Remove</button>
                </div>
              </div>
            </div>
          @endforeach
        </div>

        <!-- Add New Video Files -->
        <h4 class="mt-4">Add New Video Files / Episodes</h4>
        <div id="new-video-files-container">
          <div class="video-file-item mb-3 border rounded p-3">
            <div class="row g-2">
              <div class="col-md-5">
                <label>Video File</label>
                <input type="file" name="video_files[]" class="form-control" accept="video/*" required>
              </div>
              <div class="col-md-3">
                <label>Variant / Label</label>
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
            </div>
          </div>
        </div>

        <button type="button" class="btn btn-secondary mb-3" id="add-new-video-file">+ Add Another File</button>

        <!-- Submit -->
        <div class="text-end">
          <button type="submit" class="btn btn-primary">💾 Update Video</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
  // Add new video file inputs
  document.getElementById('add-new-video-file').addEventListener('click', function() {
    const container = document.getElementById('new-video-files-container');
    const item = container.querySelector('.video-file-item');
    const clone = item.cloneNode(true);

    // Clear inputs
    clone.querySelectorAll('input').forEach(input => {
      if (input.type === 'file') {
        input.value = '';
      } else {
        input.value = '';
      }
    });
    clone.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);

    container.appendChild(clone);
  });

  // Remove existing video file container (mark for deletion or remove UI)
  document.getElementById('existing-video-files-container').addEventListener('click', function(e) {
    if (e.target.matches('.remove-existing-file')) {
      const fileItem = e.target.closest('.video-file-item');
      if (fileItem) {
        // Option 1: Remove from UI and add a hidden input to mark for deletion
        fileItem.style.display = 'none';
        const fileId = fileItem.querySelector('input[name*="[id]"]').value;
        const form = e.target.closest('form');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_files[]';
        input.value = fileId;
        form.appendChild(input);
      }
    }
  });
</script>
@endpush
@endsection
