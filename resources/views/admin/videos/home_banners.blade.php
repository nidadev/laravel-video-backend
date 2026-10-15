@extends('admin.layout')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Homepage banners</h2>
        <a href="{{ route('admin.videos') }}" class="btn btn-outline-secondary">All videos</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <p class="text-muted">Choose up to five ready videos. Their position below sets the order in the iOS and Android homepage carousel. Leave every position empty to show the five newest ready videos automatically.</p>
            <form method="POST" action="{{ route('admin.videos.homeBanners.update') }}">
                @csrf
                @for($position = 0; $position < 5; $position++)
                    <div class="mb-3">
                        <label class="form-label" for="banner-{{ $position }}">Position {{ $position + 1 }}</label>
                        <select class="form-select" id="banner-{{ $position }}" name="banner_ids[]">
                            <option value="">No video</option>
                            @foreach($videos as $video)
                                <option value="{{ $video->id }}" @selected((string) old('banner_ids.'.$position, $selectedIds[$position] ?? '') === (string) $video->id)>
                                    #{{ $video->id }} — {{ $video->title }}{{ $video->year_of_published ? ' ('.$video->year_of_published.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endfor
                <button type="submit" class="btn btn-primary">Save homepage banners</button>
            </form>
        </div>
    </div>

    <h3 class="h5">Currently shown on the homepage</h3>
    @if(!$selectedIds)
        <p class="text-muted">Automatic: newest ready videos</p>
    @endif
    <div class="row row-cols-2 row-cols-md-5 g-3">
        @foreach($selectedIds ?: $videos->take(5)->pluck('id')->all() as $videoId)
            @php($video = $videos->firstWhere('id', $videoId))
            @if($video)
                <div class="col">
                    <div class="card h-100">
                        <img src="{{ \App\Services\MediaUrlService::cdnUrl($video->thumbnail) }}" alt="" class="card-img-top" style="height: 190px; object-fit: cover;">
                        <div class="card-body p-2">
                            <strong class="small">{{ $loop->iteration }}. {{ $video->title }}</strong>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
@endsection
