<?php

namespace App\Utils;

use Illuminate\Support\Facades\Storage;

class Files
{
    public static function saveFromBase64($base64, $path, $imageName = null): string
    {
        if (empty($imageName)) {
            $extension = explode('/', mime_content_type($base64))[1];
            $imageName = sha1(microtime()) . '.' . $extension;
        } else {
            $imageName = time() . $imageName;
        }

        $base64_str = substr($base64, strpos($base64, ",") + 1);
        $base = base64_decode($base64_str);

        Storage::put($path . $imageName, $base);
        return Storage::url($path . $imageName);
    }

    public static function save($image, $path, $imageName = null): string
    {
        if (empty($imageName)) {
            $imageName = sha1(microtime());
        }

        if (is_file($image)) {
            $base64 = \Image::make($image)->stream()->__toString();
            $imageName .= '.' . $image->getClientOriginalExtension();
        } else {
            $base64 = $image;
            $extension = explode('/', mime_content_type($base64))[1];
            $imageName .= $extension;
        }

        $base64_str = substr($base64, strpos($base64, ",") + 1);
        $base = base64_decode($base64_str);

        Storage::put($path . $imageName, $base);
        return Storage::url($path . $imageName);
    }
}