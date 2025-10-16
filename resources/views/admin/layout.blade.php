<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .sidebar {
      width: 240px; background: #343a40; color: white; height: 100vh; position: fixed; top: 0; left: 0;
    }
    .sidebar a { color: #fff; text-decoration: none; display: block; padding: 10px 20px; }
    .sidebar a:hover { background: #495057; }
    .content { margin-left: 240px; padding: 20px; }
  </style>
</head>
<body>

  <div class="sidebar">
    <h4 class="p-3">Admin Panel</h4>
    <a href="{{ route('admin.dashboard') }}">🏠 Dashboard</a>
    <a href="{{ route('admin.videos') }}">🎬 Videos</a>
        <a href="{{ route('admin.users.index') }}"> 👥 Manage User</a>
            <a href="{{ route('admin.logout') }}">🚪 Logout</a>


    <!--a href="{{ route('admin.videos.presigned.create') }}">☁️ Presigned Upload</a-->

  </div>

  <div class="content">
    @yield('content')
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
