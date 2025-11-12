@extends('admin.layout')

@section('content')
<div class="container">
  <h2 class="mb-4">✏️ Edit Video (with Episodes / Variants)</h2>

  <form id="presignedEditForm">
    @csrf
    <input type="hidden" name="video_id" value="{{ $video->id }}">

    <div class="mb-3">
      <label>Title</label>
      <input type="text" name="title" class="form-control" value="{{ $video->title }}" required>
    </div>

    <div class="mb-3">
      <label>Description</label>
      <textarea name="description" class="form-control" rows="3">{{ $video->description }}</textarea>
    </div>

    <div class="mb-3">
      <label>Category</label>
      <select name="category_id" class="form-select" id="category-select" required>
        <option value="">Select Category</option>
        @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" {{ $video->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label>Subcategory</label>
      <select name="subcategory_id" class="form-select" id="subcategory-select">
        <option value="">Select Subcategory</option>
      </select>
    </div>

    <!-- Main Thumbnail -->
    <div class="mb-3">
      <label>Thumbnail</label>
      @if($video->thumbnail)
        <div class="mb-2">
          <img id="thumbnail-preview" src="{{ $video->thumbnail }}" width="200" class="rounded border shadow-sm">
        </div>
      @endif
      <input type="file" id="thumbnailFile" class="form-control" accept="image/*">
      <input type="hidden" name="thumbnail" id="thumbnail-url" value="{{ $video->thumbnail }}">
      <div class="progress mt-2 d-none" id="thumbProgressWrapper">
        <div id="thumbProgress" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
      </div>
    </div>

    <hr>

    <h4>Video Files / Episodes</h4>
    <div id="video-files-container">
      @foreach($video->files as $file)
      <div class="video-file-item mb-4 border p-3 rounded">
        <input type="hidden" name="existing_files[{{ $file->id }}][id]" value="{{ $file->id }}">
        <div class="row g-3 align-items-end">

          <div class="col-md-4">
            <label>Video File</label>
            <video width="100%" controls class="mb-2">
              <source src="{{ $file->file_url }}" type="video/mp4">
            </video>
            <input type="file" class="form-control video-file" accept="video/*">
            <input type="hidden" name="existing_files[{{ $file->id }}][file_url]" value="{{ $file->file_url }}">
          </div>

          <div class="col-md-3">
            <label>Variant / Label</label>
            <input type="text" name="existing_files[{{ $file->id }}][variant]" value="{{ $file->variant }}" class="form-control">
          </div>

          <div class="col-md-2">
            <label>Season</label>
            <input type="text" name="existing_files[{{ $file->id }}][season]" value="{{ $file->season }}" class="form-control">
          </div>

          <div class="col-md-2">
            <label>Duration</label>
            <input type="text" name="existing_files[{{ $file->id }}][duration]" value="{{ $file->duration }}" class="form-control">
          </div>

          <div class="col-md-2">
            <label>DRM?</label>
            <select name="existing_files[{{ $file->id }}][drm]" class="form-select">
              <option value="0" {{ !$file->drm ? 'selected' : '' }}>No</option>
              <option value="1" {{ $file->drm ? 'selected' : '' }}>Yes</option>
            </select>
          </div>

          <div class="col-md-3">
            <label>Episode Image</label>
            @if($file->image)
              <div class="mb-2">
                <img src="{{ $file->image }}" width="120" class="rounded mb-1">
              </div>
            @endif
            <input type="file" class="form-control image-file" data-file-id="{{ $file->id }}">
            <input type="hidden" name="existing_files[{{ $file->id }}][image]" value="{{ $file->image }}">
          </div>

          <div class="col-md-1 text-end">
            <button type="button" class="btn btn-danger remove-file-item">X</button>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    <button type="button" class="btn btn-secondary mb-3" id="add-video-file">+ Add Another File</button>

    <div id="progressContainer"></div>
    <button type="submit" class="btn btn-success mt-3">Update Video</button>
  </form>
</div>

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
  // Load subcategories dynamically
  let currentSub = "{{ $video->subcategory_id }}";
  function loadSubcategories(catId){
    const subSelect = $('#subcategory-select');
    subSelect.html('<option>Loading...</option>');
    if(catId){
      $.getJSON(`/api/categories/${catId}/subcategories`, function(resp){
        subSelect.empty().append('<option value="">Select Subcategory</option>');
        resp.data.forEach(sub => subSelect.append(`<option value="${sub.id}" ${sub.id==currentSub?'selected':''}>${sub.name}</option>`));
      });
    }
  }
  loadSubcategories($('#category-select').val());
  $('#category-select').on('change', function(){ loadSubcategories($(this).val()); });

  // Add / Remove video items
  $('#add-video-file').click(function(){
    const clone = $('.video-file-item').first().clone();
    clone.find('input').val('');
    clone.find('select').prop('selectedIndex',0);
    $('#video-files-container').append(clone);
  });
  $('#video-files-container').on('click','.remove-file-item', function(){ $(this).closest('.video-file-item').remove(); });

  // Upload file to S3
  async function uploadFileToS3(file,url,progressBar){
    return new Promise((resolve,reject)=>{
      const xhr = new XMLHttpRequest();
      xhr.open('PUT', url);
      xhr.upload.onprogress = e=>{
        if(e.lengthComputable){
          const percent = Math.round(e.loaded/e.total*100);
          if(progressBar) { progressBar.style.width = percent+'%'; progressBar.textContent = percent+'%'; }
        }
      };
      xhr.onload = ()=>xhr.status===200?resolve():reject('Upload failed');
      xhr.onerror = ()=>reject('Upload error');
      xhr.send(file);
    });
  }

  // Handle thumbnail upload
  $('#thumbnailFile').on('change', async function(){
    const file = this.files[0]; if(!file) return;
    const res = await fetch('{{ route("admin.videos.presigned.url") }}',{
      method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'},
      body: JSON.stringify({filename:file.name, content_type:file.type, type:'thumbnail'})
    });
    const data = await res.json();
    await uploadFileToS3(file,data.url,$('#thumbProgress')[0]);
    $('#thumbnail-url').val(data.file_url);
    $('#thumbnail-preview').attr('src',data.file_url).show();
  });

  // Episode image upload
  $('#video-files-container').on('change','.image-file', async function(){
    const file = this.files[0]; if(!file) return;
    const fileId = $(this).data('file-id');
    const res = await fetch('{{ route("admin.videos.presigned.url") }}',{
      method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'},
      body: JSON.stringify({filename:file.name, content_type:file.type, type:'video_image'})
    });
    const data = await res.json();
    await uploadFileToS3(file,data.url,null);
    $(`input[name="existing_files[${fileId}][image]"]`).val(data.file_url);
    alert('Episode image uploaded!');
  });

  // Submit form
  $('#presignedEditForm').on('submit', async function(e){
    e.preventDefault();
    const formData = new FormData(this);

    // Include newly added video files
    $('.video-file-item').each(function(){
      const fileInput = $(this).find('.video-file')[0];
      if(fileInput && fileInput.files[0]){
        formData.append('new_videos[]', fileInput.files[0]);
        formData.append('new_variants[]', $(this).find('.variant').val());
        formData.append('new_seasons[]', $(this).find('.season').val());
        formData.append('new_durations[]', $(this).find('.duration').val());
        formData.append('new_drms[]', $(this).find('.drm').val());
        const img = $(this).find('.image-file')[0];
        if(img && img.files[0]) formData.append('new_images[]', img.files[0]);
      }
    });

    const res = await fetch(`{{ route('admin.videos.update', $video->id) }}`,{
      method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:formData
    });
    const result = await res.json();
    alert(result.message || result.error || 'Updated!');
    if(result.success) location.reload();
  });
});
</script>
@endpush
@endsection
