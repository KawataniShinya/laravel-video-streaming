<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Available Videos</h1>
        <div class="bg-white shadow rounded-lg p-6">
            @if(count($files) > 0)
                <ul class="divide-y divide-gray-200">
                    @foreach($files as $file)
                        <li class="py-4 flex justify-between items-center">
                            <span class="text-lg">{{ $file }}</span>
                            <a href="{{ route('videos.watch', ['filename' => $file]) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Watch
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-gray-500">No videos found in /videos.</p>
            @endif
        </div>
    </div>
</body>
</html>
