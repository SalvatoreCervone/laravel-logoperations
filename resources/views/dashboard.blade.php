<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ function_exists('csrf_token') ? csrf_token() : '' }}">
    <title>LogOperations — {{ $appName }} Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    @include('logoperations::partials.styles')
</head>
<body>
<div id="app">
    @include('logoperations::partials.navbar')

    <div class="dash-container">
        @include('logoperations::partials.tabs-nav')
        @include('logoperations::partials.kpi-grid')

        @include('logoperations::partials.tab-logs')
        @include('logoperations::partials.tab-storyboard')
        @include('logoperations::partials.tab-studio')
    </div>

    @include('logoperations::partials.modal-detail')
</div>

@include('logoperations::partials.scripts')
</body>
</html>
