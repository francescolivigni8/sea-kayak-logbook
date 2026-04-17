<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SessionMediaService
{
    public function diskName(): string
    {
        return (string) config('kayak.media_disk', 'public');
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }

    public function storeUploadedFile(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, $this->diskName());
    }

    public function putContents(string $path, string $contents): void
    {
        $this->disk()->put($path, $contents);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            $this->disk()->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (config('kayak.media_temporary_urls')) {
            try {
                return $this->disk()->temporaryUrl(
                    $path,
                    now()->addMinutes((int) config('kayak.media_temporary_url_minutes', 30)),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $this->disk()->url($path);
    }
}
