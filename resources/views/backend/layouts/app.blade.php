<!-- filepath: resources/views/backend/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CoreUI Backend</title>
    <!-- CoreUI CSS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/coreui@4.3.5/dist/css/coreui.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/icons@3.0.1/css/all.min.css">
</head>
<body class="c-app">
    {{-- Sidebar, Header có thể thêm sau --}}
    <div class="c-wrapper">
        <div class="c-body">
            <main class="c-main">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    <!-- CoreUI JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@4.3.5/dist/js/coreui.bundle.min.js"></script>
</body>
</html>