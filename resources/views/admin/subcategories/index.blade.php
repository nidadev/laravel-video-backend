@extends('admin.layout')

@section('content')
<h1>Subcategories</h1>
<a href="{{ route('admin.subcategories.create') }}" class="btn btn-primary mb-3">Add Subcategory</a>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-bordered">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Category</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($subcategories as $sub)
        <tr>
            <td>{{ $sub->id }}</td>
            <td>{{ $sub->name }}</td>
            <td>{{ $sub->slug }}</td>
            <td>{{ $sub->category->name ?? '-' }}</td>
            <td>
                <a href="{{ route('admin.subcategories.edit', $sub->id) }}" class="btn btn-sm btn-warning">Edit</a>
                <form action="{{ route('admin.subcategories.destroy', $sub->id) }}" method="POST" style="display:inline-block;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger"
                        onclick="return confirm('Are you sure you want to delete this subcategory?')">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
