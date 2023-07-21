<?php

namespace App\Traits;

trait FilesTrait
{
    protected function convertUri($url)
    {
        if (!empty($url)) {
            $bucket = explode('//', $url);
            $bucket = explode('.s3', array_last($bucket));
            $bucket = array_first($bucket);

            $path = explode('.com', $url);
            $path = array_last($path);

            $filename = explode('/', $path);
            $filename = array_last($filename);
            $path = str_replace($filename, '', $path);

            $newUrl = url("api/media/{$filename}?path={$path}&storage={$bucket}");
            return str_replace('http', 'https', $newUrl);
        }

        return $url;
    }
}