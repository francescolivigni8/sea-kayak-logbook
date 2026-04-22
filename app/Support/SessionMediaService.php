<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
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

    public function storeSanitizedImage(UploadedFile $file, string $directory): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Image sanitization needs the GD extension.');
        }

        $source = file_get_contents($file->getRealPath() ?: $file->path());

        if ($source === false) {
            throw new RuntimeException('Uploaded image could not be read.');
        }

        $image = imagecreatefromstring($source);

        if ($image === false) {
            throw new RuntimeException('Uploaded image could not be sanitized.');
        }

        imagepalettetotruecolor($image);

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($image);

            throw new RuntimeException('Sanitized image canvas could not be created.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 86);
        $contents = ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Sanitized image could not be encoded.');
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.jpg';
        $this->disk()->put($path, $contents);

        return $path;
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

                if ($this->shouldFailClosed()) {
                    return null;
                }
            }
        }

        if ($this->shouldFailClosed()) {
            return null;
        }

        return $this->disk()->url($path);
    }

    private function shouldFailClosed(): bool
    {
        $diskName = $this->diskName();

        if ($diskName === 'public') {
            return false;
        }

        return $diskName === 's3'
            || config("filesystems.disks.{$diskName}.visibility") === 'private';
    }
}
