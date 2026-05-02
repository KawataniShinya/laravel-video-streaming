<?php

namespace App\ValueObjects\Video;

enum HlsCacheStatus: string
{
    case Completed = 'completed';
    case Transcoding = 'transcoding';
    case Failed = 'failed';
}
