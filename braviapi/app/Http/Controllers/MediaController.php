<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController
{
    public function show(Request $request, $filename)
    {
        $path = $request->get('path', '');
        $item = Storage::disk("s3_{$request->storage}")->get($path.$filename);
        return base64_encode($item);
    }
}