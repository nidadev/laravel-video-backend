@extends('admin.layout')

@section('content')
<div class="container">
  <h2 class="mb-4">☁️ Upload New Video (with Episodes / Variants)</h2>

  <form id="presignedUploadForm">
    @csrf
    <div class="mb-3">
      <label>Title</label>
      <input type="text" name="title" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Description</label>
      <textarea name="description" class="form-control" rows="3"></textarea>
    </div>

    <div class="mb-3">
    <label>Category</label>
    <select name="category_id" class="form-select" id="category-select" required>
        <option value="">Select Category</option>
        @foreach ($categories as $cat)
            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
        @endforeach
    </select>
    @error('category_id')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Subcategory</label>
    <select name="subcategory_id" class="form-select" id="subcategory-select">
        <option value="">Select Subcategory</option>
    </select>
    @error('subcategory_id')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>


    <div class="mb-3">
      <label>Thumbnail</label>
      <input type="file" id="thumbnailFile" class="form-control" accept="image/*">
      <div class="progress mt-2 d-none" id="thumbProgressWrapper">
        <div id="thumbProgress" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
      </div>
    </div>

    <hr>

    <h4>Video Files / Episodes</h4>
    <div id="video-files-container">
      <div class="video-file-item mb-4 border p-3 rounded">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label>Video File</label>
            <input type="file" class="form-control video-file" accept="video/*" required>
          </div>
          <div class="col-md-3">
            <label>Variant / Label</label>
            <input type="text" class="form-control variant" placeholder="Episode 1 / 720p">
          </div>
          <div class="col-md-2">
    <label>Season</label>
    <input type="text" class="form-control season" placeholder="1 / Season 1 / S01">
  </div>
          <div class="col-md-2">
            <label>Duration</label>
            <input type="text" class="form-control duration" placeholder="3600 or 01:00:00">
          </div>
          <div class="col-md-2">
            <label>DRM?</label>
            <select class="form-select drm">
              <option value="0" selected>No</option>
              <option value="1">Yes</option>
            </select>
          </div>
          <div class="col-md-1 text-end">
            <button type="button" class="btn btn-danger remove-file-item">X</button>
          </div>
        </div>
      </div>
    </div>
    <button type="button" class="btn btn-secondary mb-3" id="add-video-file">+ Add Another File</button>

    <div id="progressContainer"></div>

    <button type="submit" class="btn btn-success mt-3">Upload All</button>
  </form>
</div>

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function() {
    $('#category-select').on('change', function() {
        let categoryId = $(this).val();
        let subcategorySelect = $('#subcategory-select');

        subcategorySelect.html('<option value="">Loading...</option>');

        if(categoryId) {
            $.ajax({
                url: '/api/categories/' + categoryId + '/subcategories',
                type: 'GET',
                success: function(response) {
                    subcategorySelect.empty();
                    subcategorySelect.append('<option value="">Select Subcategory</option>');
                    if(response.success && response.data.length > 0) {
                        $.each(response.data, function(index, subcat) {
                            subcategorySelect.append(
                                `<option value="${subcat.id}">${subcat.name}</option>`
                            );
                        });
                    }
                },
                error: function() {
                    subcategorySelect.html('<option value="">Error loading subcategories</option>');
                }
            });
        } else {
            subcategorySelect.html('<option value="">Select Subcategory</option>');
        }
    });
});
document.getElementById('add-video-file').addEventListener('click', function() {
  const container = document.getElementById('video-files-container');
  const firstItem = container.querySelector('.video-file-item');
  const clone = firstItem.cloneNode(true);

  clone.querySelectorAll('input').forEach(i => i.value = '');
  clone.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
  container.appendChild(clone);
});

document.getElementById('video-files-container').addEventListener('click', function(e) {
  if (e.target.classList.contains('remove-file-item')) {
    if (document.querySelectorAll('.video-file-item').length > 1)
      e.target.closest('.video-file-item').remove();
  }
});

document.getElementById('presignedUploadForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const title = document.querySelector('[name="title"]').value;
  const description = document.querySelector('[name="description"]').value;
  const category_id = document.querySelector('[name="category_id"]').value;
  const subcategory_id = document.querySelector('[name="subcategory_id"]').value;
  const thumbnail = document.getElementById('thumbnailFile').files[0];
  const videoItems = document.querySelectorAll('.video-file-item');

  if (!videoItems.length) {
    alert('Please add at least one video file.');
    return;
  }

  const uploadedVideos = [];
  const progressContainer = document.getElementById('progressContainer');
  progressContainer.innerHTML = '';

  // ✅ Upload thumbnail (to thumbnails/ folder)
  let thumbnailUrl = null;
  if (thumbnail) {
    document.getElementById('thumbProgressWrapper').classList.remove('d-none');
    const presignThumbRes = await fetch(`{{ route('admin.videos.presigned.url') }}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        filename: thumbnail.name,
        content_type: thumbnail.type,
        type: 'thumbnail' // 👈 important!
      })
    });

    const presignThumbData = await presignThumbRes.json();
    await uploadFileToS3(thumbnail, presignThumbData.url, document.getElementById('thumbProgress'));
    thumbnailUrl = presignThumbData.file_url;
  }

  // ✅ Upload each video file (to videos/ folder)
  for (const item of videoItems) {
    const file = item.querySelector('.video-file').files[0];
    const variant = item.querySelector('.variant').value;
    const duration = item.querySelector('.duration').value;
    const drm = item.querySelector('.drm').value;
    const season = item.querySelector('.season').value;


    if (!file) continue;

    // Create progress bar
    const progressBar = document.createElement('div');
    progressBar.classList.add('progress', 'mb-2');
    progressBar.innerHTML = `<div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">Uploading ${file.name}</div>`;
    progressContainer.appendChild(progressBar);
    const progress = progressBar.querySelector('.progress-bar');

    // Ask backend for presigned URL
    const presignRes = await fetch(`{{ route('admin.videos.presigned.url') }}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        filename: file.name,
        content_type: file.type,
        type: 'video' // 👈 important!
      })
    });

    const presignData = await presignRes.json();
    await uploadFileToS3(file, presignData.url, progress);

    uploadedVideos.push({
      variant,
      season,
      duration,
      drm,
      file_url: presignData.file_url,
      original_name: file.name,
      size: file.size,
      mime: file.type
    });
  }

  // ✅ Store metadata
  const storeRes = await fetch(`{{ route('admin.videos.presigned.store') }}`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      title,
      description,
      category_id,
      subcategory_id,
      thumbnail: thumbnailUrl,
      videos: uploadedVideos
    })
  });

  const result = await storeRes.json();
  alert(result.message || result.error || 'Upload complete!');
});

async function uploadFileToS3(file, url, progressBar) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('PUT', url);
    xhr.upload.addEventListener('progress', e => {
      if (e.lengthComputable) {
        const percent = Math.round((e.loaded / e.total) * 100);
        progressBar.style.width = percent + '%';
        progressBar.textContent = `${file.name} - ${percent}%`;
      }
    });
    xhr.onload = () => xhr.status === 200 ? resolve() : reject(xhr.responseText);
    xhr.onerror = () => reject('Upload failed');
    xhr.send(file);
  });
}
</script>
@endpush
@endsection
