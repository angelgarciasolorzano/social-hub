<?php

namespace App\MediaLibrary;

use App\Post\Models\Post;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    /**
     * Returns the storage path for the given media item.
     * Organizes files by model type and media ID for better structure.
     *
     * @param  Media  $media  The media item being stored.
     * @return string The relative path where the media file will be saved.
     */
    public function getPath(Media $media): string
    {
        return match ($media->model_type) {
            Post::MORPH_NAME => Post::PATH.DIRECTORY_SEPARATOR.$media->id.DIRECTORY_SEPARATOR,
            default => $media->id.DIRECTORY_SEPARATOR,
        };
    }

    /**
     * Returns the path for storing conversions of the given media item.
     * Conversion files (e.g., thumbnails) are placed in a dedicated subfolder.
     *
     * @param  Media  $media  The media item for which conversions are generated.
     * @return string The relative path for conversion files.
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions'.DIRECTORY_SEPARATOR;
    }

    /**
     * Returns the path for storing responsive images of the given media item.
     * Responsive images are placed in a dedicated subfolder for optimal delivery.
     *
     * @param  Media  $media  The media item for which responsive images are generated.
     * @return string The relative path for responsive image files.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive-images'.DIRECTORY_SEPARATOR;
    }
}
