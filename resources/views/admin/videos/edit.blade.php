@extends('admin.layout')

@section('content')
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-10">
      <h2>Edit Video (with Episodes / Variants)</h2>

      @if($errors->any())
      <div class="alert alert-danger">
          <ul>
              @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
              @endforeach
          </ul>
      </div>
      @endif

      <form id="editVideoForm" method="POST" 
            action="{{ route('admin.videos.update', $video->id) }}" 
            enctype="multipart/form-data">
          @csrf
          @method('PUT')

          {{-- Main Details --}}
          <div class="mb-3">
              <label>Title</label>
              <input type="text" name="title" class="form-control" required value="{{ $video->title }}">
          </div>

          <div class="mb-3">
              <label>Description</label>
              <textarea name="description" class="form-control" rows="3">{{ $video->description }}</textarea>
          </div>

          <div class="mb-3">
              <label>Category</label>
              <select name="category_id" class="form-select" required>
                  <option value="">Select Category</option>
                  @foreach($categories as $cat)
                      <option value="{{ $cat->id }}" {{ $video->category_id == $cat->id ? 'selected' : '' }}>
                          {{ $cat->name }}
                      </option>
                  @endforeach
              </select>
          </div>

          <div class="mb-3">
              <label>Subcategory</label>
              <select name="subcategory_id" class="form-select">
                  <option value="">Select Subcategory</option>
                  @foreach(\App\Models\Subcategory::all() as $subcat)
                      <option value="{{ $subcat->id }}" {{ $video->subcategory_id == $subcat->id ? 'selected' : '' }}>
                          {{ $subcat->name }}
                      </option>
                  @endforeach
              </select>
          </div>

          {{-- Thumbnail --}}
          <div class="mb-3">
              <label>Thumbnail</label>
              @if($video->thumbnail)
                  <img src="{{ $video->thumbnail }}" width="150" class="mb-2" id="thumbnail-preview">
              @endif
              <input type="file" name="thumbnail_file" class="form-control" id="thumbnailFile">
              <input type="hidden" name="thumbnail" id="thumbnail-url" value="{{ $video->thumbnail }}">
          </div>

          <hr>
          <h4>Video Files / Episodes</h4>
          <div id="video-files-container">
              @foreach($video->files as $file)
              <div class="video-file-item mb-4 border rounded p-3">
                  <div class="row g-3 align-items-end">

                      <div class="col-md-3">
                          <label>Video File</label>
                          <input type="hidden" name="videos[{{ $loop->index }}][file_url]" value="{{ $file->file_url }}">
                          <video width="100%" controls class="mb-1">
                              <source src="{{ $file->file_url }}">
                          </video>
                          <input type="file" class="form-control video-file">
                          <div class="progress video-progress mt-1" style="height: 20px; display:none;">
                              <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                          </div>
                      </div>

                      <div class="col-md-3">
                          <label>Variant / Label</label>
                          <input type="text" name="videos[{{ $loop->index }}][variant]" class="form-control" value="{{ $file->variant }}">
                      </div>

                      <div class="col-md-2">
                          <label>Duration</label>
                          <input type="text" name="videos[{{ $loop->index }}][duration]" class="form-control" value="{{ $file->duration }}">
                      </div>

                      <div class="col-md-2">
                          <label>DRM?</label>
                          <select name="videos[{{ $loop->index }}][drm]" class="form-select">
                              <option value="0" {{ !$file->drm ? 'selected' : '' }}>No</option>
                              <option value="1" {{ $file->drm ? 'selected' : '' }}>Yes</option>
                          </select>
                      </div>

                      <div class="col-md-2">
                          <label>Episode Image</label>
                          @if($file->image)
                              <img src="{{ $file->image }}" width="100" class="mb-1">
                          @endif
                          <input type="file" class="form-control image-file">
                          <input type="hidden" name="videos[{{ $loop->index }}][image]" value="{{ $file->image }}">
                          <div class="progress image-progress mt-1" style="height: 20px; display:none;">
                              <div class="progress-bar bg-success" role="progressbar" style="width:0%">0%</div>
                          </div>
                      </div>

                      <input type="hidden" name="videos[{{ $loop->index }}][season]" value="{{ $file->season_id }}">

                      <div class="col-12 text-end">
                          <button type="button" class="btn btn-danger remove-file-item mt-2">Remove</button>
                      </div>
                  </div>
              </div>
              @endforeach
          </div>

          <button type="button" class="btn btn-secondary mb-3" id="add-video-file">+ Add Another File</button>

          <div>
              <button type="submit" class="btn btn-success">Update Video</button>
          </div>

      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
let newIndex = {{ $video->files->count() }};

// Helper: Upload to S3 with progress
async function uploadToS3(file, url, progressBarElement) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open("PUT", url);

        xhr.upload.addEventListener("progress", e => {
            if(e.lengthComputable && progressBarElement){
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBarElement.style.display = "block";
                progressBarElement.querySelector(".progress-bar").style.width = percent + "%";
                progressBarElement.querySelector(".progress-bar").innerText = percent + "%";
            }
        });

        xhr.onload = () => {
            if(xhr.status === 200 || xhr.status === 204){
                progressBarElement.querySelector(".progress-bar").style.width = "100%";
                progressBarElement.querySelector(".progress-bar").innerText = "100%";
                resolve();
            } else reject(`Upload failed with status ${xhr.status}`);
        };

        xhr.onerror = () => reject("Upload error");
        xhr.send(file);
    });
}

// Add new video file item
document.getElementById('add-video-file').addEventListener('click', function() {
    const container = document.getElementById('video-files-container');
    const template = document.querySelector('.video-file-item').cloneNode(true);

    // Clear inputs
    template.querySelectorAll('input').forEach(input => {
        if(input.type !== 'hidden') input.value = '';
    });
    template.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
    template.querySelectorAll('video, img').forEach(el => el.remove());

    // Reset progress bars
    template.querySelectorAll('.progress').forEach(p => {
        p.style.display = 'none';
        p.querySelector('.progress-bar').style.width = '0%';
        p.querySelector('.progress-bar').innerText = '0%';
    });

    template.querySelectorAll('input, select').forEach(el => {
        if(!el.name) return;
        el.name = el.name.replace(/\[\d+\]/, `[${newIndex}]`);
    });

    container.appendChild(template);
    newIndex++;
});

// Remove file item
document.getElementById('video-files-container').addEventListener('click', function(e) {
    if(e.target.matches('.remove-file-item')) {
        const item = e.target.closest('.video-file-item');
        if(!item) return;
        const allItems = document.querySelectorAll('.video-file-item');
        if(allItems.length <= 1){ alert('At least one video file is required.'); return; }
        item.remove();

        // Reindex
        let idx = 0;
        document.querySelectorAll('.video-file-item').forEach(div => {
            div.querySelectorAll('input, select').forEach(el => {
                if(!el.name) return;
                el.name = el.name.replace(/\[\w+\]/, `[${idx}]`);
            });
            idx++;
        });
    }
});

// Thumbnail upload
document.getElementById('thumbnailFile').addEventListener('change', async function() {
    const file = this.files[0];
    if(!file) return;

    const res = await fetch("{{ route('admin.videos.presigned.url') }}", {
        method:'POST',
        headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'},
        body: JSON.stringify({ filename:file.name, content_type:file.type, type:'thumbnail' })
    });
    const data = await res.json();
    const progressBar = document.querySelector('.image-progress');
    await uploadToS3(file, data.url, progressBar);

    document.getElementById('thumbnail-url').value = data.file_url;
    document.getElementById('thumbnail-preview').src = data.file_url;
});

// Video / Image upload with per-item progress
document.getElementById('video-files-container').addEventListener('change', async function(e){
    if(!e.target.classList.contains('video-file') && !e.target.classList.contains('image-file')) return;

    const file = e.target.files[0];
    const type = e.target.classList.contains('video-file') ? 'video' : 'video_image';
    const progressBar = e.target.closest('.video-file-item').querySelector(
        type === 'video' ? '.video-progress' : '.image-progress'
    );

    const res = await fetch("{{ route('admin.videos.presigned.url') }}", {
        method:'POST',
        headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'},
        body: JSON.stringify({ filename:file.name, content_type:file.type, type })
    });
    const data = await res.json();

    await uploadToS3(file, data.url, progressBar);

    const hiddenInput = e.target.parentNode.querySelector(
        type === 'video' ? 'input[name*="[file_url]"]' : 'input[name*="[image]"]'
    );
    if(hiddenInput) hiddenInput.value = data.file_url;
});
</script>
@endpush
@endsection
