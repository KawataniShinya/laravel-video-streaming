<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watching {{ $filename }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black h-screen flex flex-col items-center justify-center">
    <div class="w-full max-w-6xl">
        <div class="mb-4 text-white">
            <a href="{{ route('videos.index') }}" class="text-blue-400 hover:text-blue-300">&larr; Back to Library</a>
            <h1 class="text-2xl font-bold mt-2">{{ $filename }}</h1>
        </div>
        <video controls autoplay class="w-full shadow-lg rounded">
            <source src="{{ route('videos.stream', ['filename' => $filename]) }}" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
</body>
</html>
