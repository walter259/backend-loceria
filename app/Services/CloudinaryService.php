<?php

namespace App\Services;

use Cloudinary\Cloudinary;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'api_secret' => env('CLOUDINARY_API_SECRET'),
        ]);
    }

    public function upload($filePath, $options = [])
    {
        return $this->cloudinary->uploadApi()->upload($filePath, $options);
    }

    public function delete($publicId, $options = [])
    {
        return $this->cloudinary->uploadApi()->destroy($publicId, $options);
    }

    public function getCloudinary()
    {
        return $this->cloudinary;
    }

    public function getPublicIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        $publicIdWithExtension = end($parts);
        $publicId = pathinfo($publicIdWithExtension, PATHINFO_FILENAME);
        $folder = 'novels';
        return $folder . '/' . $publicId;
    }
}