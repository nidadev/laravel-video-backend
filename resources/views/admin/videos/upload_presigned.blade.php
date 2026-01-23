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
    <label>Year of Published</label>
    <input type="number" name="year_of_published" class="form-control" placeholder="2024" min="1900" max="2100">
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
      <label>Subcategory</label>
      <select name="subcategory_id" class="form-select" id="subcategory-select">
          <option value="">Select Subcategory</option>
      </select>
      @error('subcategory_id')
          <div class="text-danger small">{{ $message }}</div>
      @enderror
    </div>
<div class="mb-3">
    <label>Season</label>
    <select name="season_id" class="form-select">
        <option value="">Select Season</option>
        @foreach($seasons as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
        @endforeach
    </select>
</div>

    <!-- Main Thumbnail -->
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
    <select class="form-select season">
        <option value="">Select Season</option>
        @foreach($seasons as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
        @endforeach
    </select>
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
          <div class="col-md-3">
            <label>Episode Image</label>
            <input type="file" class="form-control image-file" accept="image/*">
          </div>
          <div class="col-md-1 text-end">
            <button type="button" class="btn btn-danger remove-file-item">X</button>
          </div>
        </div>
      </div>
    </div>

    <button type="button" class="btn btn-secondary mb-3" id="add-video-file">+ Add Another File</button>
    <div id="progressContainer"></div>
<button type="submit" id="submitBtn" class="btn btn-success mt-3">Upload All</button>
  </form>
</div>

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  let totalUploads = 0;
let completedUploads = 0;
let isUploading = false;

function updateUploadState(start = false) {
    if (start) {
        isUploading = true;
        $('#submitBtn').prop('disabled', true).text('Uploading...');
    } else if (completedUploads >= totalUploads) {
        isUploading = false;
        $('#submitBtn').prop('disabled', false).text('Upload All');
    }
}
$(document).ready(function() {
  // Load subcategories dynamically
  $('#category-select').on('change', function() {
    let categoryId = $(this).val();
    let subcategorySelect = $('#subcategory-select');
    subcategorySelect.html('<option value="">Loading...</option>');
    if(categoryId) {
      $.getJSON(`/api/categories/${categoryId}/subcategories`, function(response){
        subcategorySelect.empty().append('<option value="">Select Subcategory</option>');
        if(response.success && response.data.length > 0){
          response.data.forEach(sub => subcategorySelect.append(`<option value="${sub.id}">${sub.name}</option>`));
        }
      }).fail(() => subcategorySelect.html('<option value="">Error loading subcategories</option>'));
    } else subcategorySelect.html('<option value="">Select Subcategory</option>');
  });
});

// Add / Remove video items
$('#add-video-file').click(function() {
  const clone = $('.video-file-item').first().clone();
  clone.find('input').val('');
  clone.find('select').prop('selectedIndex',0);
  $('#video-files-container').append(clone);
});

$('#video-files-container').on('click','.remove-file-item', function() {
  if($('.video-file-item').length > 1) $(this).closest('.video-file-item').remove();
});

// Form submit
$('#presignedUploadForm').on('submit', async function(e){
  e.preventDefault();

if (isUploading) {
    alert("Files are still uploading. Please wait until uploads finish.");
    return;
}


  const title = $('[name="title"]').val();
  const description = $('[name="description"]').val();
  const category_id = $('[name="category_id"]').val();
  const subcategory_id = $('[name="subcategory_id"]').val();
  const thumbnail = $('#thumbnailFile')[0].files[0];
  const videoItems = $('.video-file-item');
  const year_of_published = $('[name="year_of_published"]').val();
    const season_id = $('[name="season_id"]').val();

  if(!videoItems.length){ alert('Add at least one video'); return; }

  const uploadedVideos = [];
  const progressContainer = $('#progressContainer');
  progressContainer.html('');


  totalUploads = 0;
completedUploads = 0;

// Count thumbnail
if (thumbnail) totalUploads++;

// Count each video file + image (if exists)
videoItems.each(function(){
    const video = $(this).find('.video-file')[0].files[0];
    const image = $(this).find('.image-file')[0].files[0];
    if(video) totalUploads++;
    if(image) totalUploads++;
});

updateUploadState(true);

  // Upload main thumbnail
  let thumbnailUrl = null;
  if(thumbnail){
    $('#thumbProgressWrapper').removeClass('d-none');
    const presignThumbData = await (await fetch(`{{ route('admin.videos.presigned.url') }}`,{
      method:'POST',
      headers:{
        'X-CSRF-TOKEN': $('input[name="_token"]').val(),
        'Content-Type':'application/json'
      },
      body: JSON.stringify({filename: thumbnail.name, content_type: thumbnail.type, type:'thumbnail'})
    })).json();
    await uploadFileToS3(thumbnail, presignThumbData.url, $('#thumbProgress')[0]);
    thumbnailUrl = presignThumbData.file_url;
  }

  // Upload video files + episode images
  for(const item of videoItems){
    const file = $(item).find('.video-file')[0].files[0];
    if(!file) continue;
    const variant = $(item).find('.variant').val();
    const season = $(item).find('.season').val();
    const duration = $(item).find('.duration').val();
    const drm = $(item).find('.drm').val();


    // Video upload
    const presignVideoData = await (await fetch(`{{ route('admin.videos.presigned.url') }}`,{
      method:'POST',
      headers:{'X-CSRF-TOKEN': $('input[name="_token"]').val(),'Content-Type':'application/json'},
      body: JSON.stringify({filename:file.name, content_type:file.type, type:'video'})
    })).json();

    const progressBar = $('<div class="progress mb-2"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">Uploading '+file.name+'</div></div>');
    progressContainer.append(progressBar);
    await uploadFileToS3(file, presignVideoData.url, progressBar.find('.progress-bar')[0]);

    // Episode image upload
    const image = $(item).find('.image-file')[0].files[0];
    let imageUrl = null;
    if(image){
      const presignImageData = await (await fetch(`{{ route('admin.videos.presigned.url') }}`,{
        method:'POST',
        headers:{'X-CSRF-TOKEN': $('input[name="_token"]').val(),'Content-Type':'application/json'},
        body: JSON.stringify({filename:image.name, content_type:image.type, type:'video_image'})
      })).json();
      await uploadFileToS3(image, presignImageData.url, progressBar.find('.progress-bar')[0]);
      imageUrl = presignImageData.file_url;
    }

    uploadedVideos.push({
      variant,
      season,
      duration,
      drm,
      file_url: presignVideoData.file_url,
      image: imageUrl,
      original_name: file.name,
      size: file.size,
      mime: file.type,
      
    });
  }

  // Store metadata
  const storeRes = await fetch(`{{ route('admin.videos.presigned.store') }}`,{
    method:'POST',
    headers:{'X-CSRF-TOKEN': $('input[name="_token"]').val(),'Content-Type':'application/json'},
    body: JSON.stringify({title, description,season_id,year_of_published, category_id, subcategory_id, thumbnail:thumbnailUrl, videos:uploadedVideos})
  });

  const result = await storeRes.json();
  alert(result.message || result.error || 'Upload complete!');
});

// S3 upload function
// async function uploadFileToS3(file,url,progressBar){
//   return new Promise((resolve,reject)=>{
//     const xhr = new XMLHttpRequest();
//     xhr.open('PUT', url);
//     xhr.upload.addEventListener('progress', e=>{
//       if(e.lengthComputable){
//         const percent = Math.round((e.loaded / e.total)*100);
//         progressBar.style.width = percent+'%';
//         progressBar.textContent = `${file.name} - ${percent}%`;
//       }
//     });
//     xhr.onload = ()=>xhr.status===200?resolve():reject(xhr.responseText);
//     xhr.onerror = ()=>reject('Upload failed');
//     xhr.send(file);
//   });
// }

async function uploadFileToS3(file, url, progressBar){
  return new Promise((resolve,reject)=>{
    const xhr = new XMLHttpRequest();
    xhr.open('PUT', url);

    xhr.upload.addEventListener('progress', e=>{
      if(e.lengthComputable){
        const percent = Math.round((e.loaded / e.total)*100);
        progressBar.style.width = percent+'%';
        progressBar.textContent = `${file.name} - ${percent}%`;
      }
    });

    xhr.onload = ()=>{
      if(xhr.status === 200){
        completedUploads++;
        updateUploadState();
        resolve();
      } else reject(xhr.responseText);
    };

    xhr.onerror = ()=>reject('Upload failed');
    xhr.send(file);
  });
}
window.addEventListener('beforeunload', function (e) {
    if (isUploading) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>
@endpush
@endsection
