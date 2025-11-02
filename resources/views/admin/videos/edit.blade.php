@extends('admin.layout')

@section('content')
<div class="container mt-4">
  <h2 class="mb-4">✏️ Edit Video</h2>
  <a href="{{ route('admin.videos') }}" class="btn btn-secondary mb-3">← Back to Videos</a>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.videos.update', $video->id) }}" id="editVideoForm">
        @csrf

        <!-- Title -->
        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" value="{{ $video->title }}" required>
        </div>

        <!-- Description -->
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4" required>{{ $video->description }}</textarea>
        </div>

        <!-- Category -->
        <div class="mb-3">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select" required>
            <option value="">Select Category</option>
            @foreach($categories as $category)
              <option value="{{ $category->id }}" {{ $video->category_id == $category->id ? 'selected' : '' }}>
                {{ $category->name }}
              </option>
            @endforeach
          </select>
        </div>

        <!-- Subcategory -->
        <div class="mb-3">
          <label class="form-label">Subcategory</label>
          <select name="subcategory" class="form-select" required>
            <option value="">Select Subcategory</option>
            @foreach(['Drama', 'Action', 'Comedy', 'Adventure', 'Romance', 'Thriller', 'Horror'] as $sub)
              <option value="{{ $sub }}" {{ $video->subcategory == $sub ? 'selected' : '' }}>
                {{ $sub }}
              </option>
            @endforeach
          </select>
        </div>

        <!-- Status -->
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            @foreach(['processing', 'ready', 'published', 'disabled'] as $status)
              <option value="{{ $status }}" {{ $video->status == $status ? 'selected' : '' }}>
                {{ ucfirst($status) }}
              </option>
            @endforeach
          </select>
        </div>

        <!-- Thumbnail -->
        <div class="mb-3">
          <label class="form-label">Thumbnail</label>
          @if($video->thumbnail)
            <div class="mb-2">
              <img id="thumbnail-preview" src="{{ $video->thumbnail }}" alt="Thumbnail" width="200" class="rounded shadow-sm border">
            </div>
          @else
            <img id="thumbnail-preview" src="" alt="" style="display:none; max-width:200px;">
          @endif
          <input type="file" id="thumbnail-file" class="form-control" accept="image/*">
          <input type="hidden" name="thumbnail" id="thumbnail-url" value="{{ $video->thumbnail }}">
        </div>

        <hr>

        <!-- Existing Files -->
        <h4>🎞 Existing Video Files</h4>
        <div id="existing-video-files-container">
          @foreach($video->files as $file)
            <div class="video-file-item mb-3 border rounded p-3">
              <input type="hidden" name="existing_files[{{ $file->id }}][id]" value="{{ $file->id }}">
              <video width="320" height="180" controls class="mb-2">
                <source src="{{ $file->file_url }}" type="video/mp4">
              </video>
              <div class="row g-2">
                 <div class="col-md-3">
                  <label>Season</label>
                  <input type="text" name="existing_files[{{ $file->id }}][season]" value="{{ $file->season }}" class="form-control" placeholder="e.g. Season 1">
                </div>
                <div class="col-md-4">
                  <label>Variant</label>
                  <input type="text" name="existing_files[{{ $file->id }}][variant]" value="{{ $file->variant }}" class="form-control">
                </div>
                <div class="col-md-3">
                  <label>Duration</label>
                  <input type="text" name="existing_files[{{ $file->id }}][duration]" value="{{ $file->duration }}" class="form-control">
                </div>
                <div class="col-md-3">
                  <label>DRM?</label>
                  <select name="existing_files[{{ $file->id }}][drm]" class="form-select">
                    <option value="0" {{ !$file->drm ? 'selected' : '' }}>No</option>
                    <option value="1" {{ $file->drm ? 'selected' : '' }}>Yes</option>
                  </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button type="button" class="btn btn-danger btn-sm remove-existing-file">Remove</button>
                </div>
              </div>
            </div>
          @endforeach
        </div>

        <input type="hidden" name="videos" id="videos-json">

        <h4 class="mt-4">⬆️ Add New Video Files</h4>
        <div id="presigned-upload-list" class="mb-3"></div>
        <button type="button" class="btn btn-outline-primary" id="add-presigned-upload">+ Upload Video via S3</button>

        <hr>

        <div class="text-end">
          <button type="submit" class="btn btn-success">💾 Update Video</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
const uploadedVideos = [];

document.getElementById('add-presigned-upload').addEventListener('click', async () => {
  const file = await selectFile();
  if (!file) return;

  const res = await fetch('{{ route('admin.videos.presigned.url') }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      filename: file.name,
      content_type: file.type
    })
  });

  const data = await res.json();
  if (!data.url) return alert('Failed to get presigned URL');

  await fetch(data.url, { method: 'PUT', headers: { 'Content-Type': file.type }, body: file });

  uploadedVideos.push({
    file_url: data.file_url,
    original_name: file.name,
    size: file.size,
    mime: file.type,
    variant: 'Default',
    drm: false,
    duration: null,
    season: '',
  });

  document.getElementById('videos-json').value = JSON.stringify(uploadedVideos);
  const div = document.createElement('div');
  div.className = 'border rounded p-2 mb-2';
  div.innerHTML = `<strong>${file.name}</strong><br><video width="320" height="180" controls src="${data.file_url}"></video>`;
  document.getElementById('presigned-upload-list').appendChild(div);
});

document.getElementById('existing-video-files-container').addEventListener('click', e => {
  if (e.target.classList.contains('remove-existing-file')) {
    const item = e.target.closest('.video-file-item');
    const id = item.querySelector('input[name*="[id]"]').value;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'delete_files[]';
    input.value = id;
    document.getElementById('editVideoForm').appendChild(input);
    item.remove();
  }
});

async function selectFile() {
  return new Promise(resolve => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'video/*';
    input.onchange = e => resolve(e.target.files[0]);
    input.click();
  });
}

// ✅ Thumbnail upload via presigned URL
document.getElementById('thumbnail-file').addEventListener('change', async e => {
  const file = e.target.files[0];
  if (!file) return;

  const res = await fetch('{{ route('admin.videos.presigned.url') }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      filename: file.name,
      content_type: file.type,
      type: 'thumbnail'
    })
  });

  const data = await res.json();
  if (!data.url) return alert('Failed to get presigned URL');

  await fetch(data.url, { method: 'PUT', headers: { 'Content-Type': file.type }, body: file });

  document.getElementById('thumbnail-url').value = data.file_url;
  document.getElementById('thumbnail-preview').src = data.file_url;
  document.getElementById('thumbnail-preview').style.display = 'block';
  alert('✅ Thumbnail uploaded successfully!');
});
</script>
@endpush
@endsection
