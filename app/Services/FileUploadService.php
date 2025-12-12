<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    public function upload(UploadedFile $file, string $folder = 'uploads'): string
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');
        // Return the relative path that can be used with Storage::url() or stored in DB
        // Storage::url() will return /storage/folder/filename.ext
        return $path;
    }

    public function uploadMultiple(array $files, string $folder = 'uploads'): array
    {
        $paths = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $this->upload($file, $folder);
            }
        }
        return $paths;
    }

    public function delete(string $path): bool
    {
        $filePath = str_replace('/storage/', '', $path);
        return Storage::disk('public')->delete($filePath);
    }
}

