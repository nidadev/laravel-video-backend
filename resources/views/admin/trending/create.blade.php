@extends('admin.layout')

@section('content')
<div class="container">
  <h2 class="mb-4">🔥 Upload New Trending Video</h2>

  <form id="trendingUploadForm">
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
      <label>Thumbnail</label>
      <input type="file" id="thumbnailFile" class="form-control" accept="image/*" required>
      <div class="progress mt-2 d-none" id="thumbProgressWrapper">
        <div id="thumbProgress" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
      </div>
    </div>

    <div class="mb-3">
      <label>Video File</label>
      <input type="file" id="videoFile" class="form-control" accept="video/*" required>
      <div class="progress mt-2 d-none" id="videoProgressWrapper">
        <div id="videoProgress" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
      </div>
    </div>

    <button type="submit" class="btn btn-success mt-3">Upload Trending Video</button>
  </form>
</div>

@push('scripts')
<script>
document.getElementById('trendingUploadForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const title = document.querySelector('[name="title"]').value;
  const description = document.querySelector('[name="description"]').value;
  const thumbnail = document.getElementById('thumbnailFile').files[0];
  const video = document.getElementById('videoFile').files[0];

  if (!thumbnail || !video) {
    alert('Please select both thumbnail and video file.');
    return;
  }

  let thumbnailUrl = null, videoUrl = null;

  // ✅ Upload Thumbnail
  document.getElementById('thumbProgressWrapper').classList.remove('d-none');
  const thumbRes = await fetch(`{{ route('admin.videos.presigned.url') }}`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      filename: thumbnail.name,
      content_type: thumbnail.type,
      type: 'thumbnail'
    })
  });
  const thumbData = await thumbRes.json();
  await uploadFileToS3(thumbnail, thumbData.url, document.getElementById('thumbProgress'));
  thumbnailUrl = thumbData.file_url;

  // ✅ Upload Video
  document.getElementById('videoProgressWrapper').classList.remove('d-none');
  const videoRes = await fetch(`{{ route('admin.videos.presigned.url') }}`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      filename: video.name,
      content_type: video.type,
      type: 'video'
    })
  });
  const videoData = await videoRes.json();
  await uploadFileToS3(video, videoData.url, document.getElementById('videoProgress'));
  videoUrl = videoData.file_url;

  // ✅ Save Trending Video Metadata
  const storeRes = await fetch(`{{ route('admin.trending.store') }}`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      title,
      description,
      thumbnail: thumbnailUrl,
      video_url: videoUrl
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
