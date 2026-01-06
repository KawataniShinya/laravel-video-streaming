<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watching {{ $filename }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
</head>
<body class="bg-black h-screen flex flex-col items-center justify-center">
    <div class="w-full max-w-6xl">
        <div class="mb-4 text-white">
            <a href="{{ route('videos.index') }}" class="text-blue-400 hover:text-blue-300">&larr; Back to Library</a>
            <h1 class="text-2xl font-bold mt-2">{{ $filename }}</h1>
            <p class="text-sm text-gray-400">Transcoding and streaming via HLS...</p>
        </div>
        <video id="video" controls autoplay class="w-full shadow-lg rounded"></video>
    </div>

    <script>
        var video = document.getElementById('video');
        var videoSrc = "{{ route('videos.hls', ['filename' => $filename, 'file' => 'index.m3u8']) }}";

        if (Hls.isSupported()) {
            var hls = new Hls();
            hls.loadSource(videoSrc);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                video.play();
            });
            // Handle 404s (while transcoding) by retrying
            hls.on(Hls.Events.ERROR, function (event, data) {
                if (data.fatal) {
                    switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        // try to recover network error
                        console.log("fatal network error encountered, try to recover");
                        hls.startLoad();
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        console.log("fatal media error encountered, try to recover");
                        hls.recoverMediaError();
                        break;
                    default:
                        // cannot recover
                        hls.destroy();
                        break;
                    }
                }
            });
        }
        else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = videoSrc;
            video.addEventListener('loadedmetadata', function() {
                video.play();
            });
        }
    </script>
</body>
</html>
