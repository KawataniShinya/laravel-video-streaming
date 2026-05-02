<?php

namespace App\ValueObjects\Video;

final class VideoPath
{
    public function __construct(
        private readonly string $path
    ) {
    }

    public function value(): string
    {
        return trim($this->path, '/');
    }

    public function basename(): string
    {
        return basename($this->value());
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->basename(), PATHINFO_EXTENSION));
    }

    public function directory(): string
    {
        $parts = explode('/', $this->value());
        array_pop($parts);

        return implode('/', $parts);
    }

    public function parent(): ?string
    {
        $dir = $this->directory();

        return $dir === '' ? null : $dir;
    }

    public function isEmpty(): bool
    {
        return $this->value() === '';
    }

    public function isVob(): bool
    {
        return $this->extension() === 'vob';
    }

    public function isVobMainEntry(): bool
    {
        return strtoupper($this->basename()) === 'VTS_01_1.VOB';
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
