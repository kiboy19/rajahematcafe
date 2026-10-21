<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Penanganan gambar unggahan pada disk `public`: simpan file baru, hapus file
 * lama saat diganti, dan bersihkan file saat gambar dilepas.
 */
trait HandlesUploadedImages
{
    protected function storeImage(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    protected function replaceImage(?UploadedFile $file, Model $model, string $column, string $directory): void
    {
        if ($file === null) {
            return;
        }

        $this->removeImage($model, $column);
        $model->{$column} = $this->storeImage($file, $directory);
    }

    protected function removeImage(Model $model, string $column): void
    {
        $path = $model->{$column};

        if ($path) {
            Storage::disk('public')->delete($path);
            $model->{$column} = null;
        }
    }
}
