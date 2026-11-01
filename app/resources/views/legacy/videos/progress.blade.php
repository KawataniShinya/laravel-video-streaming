<div class="video-progress">
    @if ($item['progress_percent'] !== null)
        <div class="video-progress-track" role="progressbar" aria-label="再生位置" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $item['progress_percent'] }}">
            <div class="video-progress-fill" style="width: {{ $item['progress_percent'] }}%"></div>
        </div>
        <span>{{ $item['progress_percent'] }}%</span>
    @else
        <div class="video-progress-track" aria-hidden="true"></div>
        <span>進捗不明（総時間未取得）</span>
    @endif
</div>
