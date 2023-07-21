<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Models\Domain;
use Packk\Core\Util\S3Directory;

class FilesUploadController extends Controller
{

    protected $storage;

    public function __construct(S3Directory $storage)
    {
        $this->storage = $storage;
    }

    public function index(Request $request, $domainId)
    {
        return $this->storage->listData($domainId, $request->directory, $request->search);
    }

    public function store(Request $request, $domainId)
    {
        if ($request->type == 'directory') {
            return $this->storage->createDirectory(
                $this->storage->_prepareDirectory($domainId, $request->directory . '/' . $request->path)
            );
        } else {
            $files = [];
            if ($request->hasFile('uploads')) {
                foreach ($request->file('uploads') as $file) {
                    $fileName = $file->getClientOriginalName();
                    if ($request->fileName) {
                        $fileName = $request->fileName . '.' . $file->getClientOriginalExtension();
                    } else if ($request->randFileName) {
                        $fileName = time() . '.' . $file->getClientOriginalExtension();
                    }

                    $directory = $this->storage->_prepareDirectory($domainId, $request->directory . (!empty($request->directory) ? '/' : '') . $fileName);
                    $this->storage->sendFile(
                        $directory,
                        $file
                    );
                    $files[] = $this->storage->getPublicPath($directory);
                }
            }
            return response($files);
        }
    }

    public function destroy(Request $request, $domainId)
    {
        if ($request->type == 'directory') {
            return $this->storage->deleteDirectory(
                $this->storage->_prepareDirectory($domainId, $request->path)
            );
        } else {
            return $this->storage->deleteFiles([
                $this->storage->_prepareDirectory($domainId, $request->path)
            ]);
        }
    }

    public function domains(Request $request)
    {
        $user = Auth::user();
        if($user->hasRole('admin-domain|operator-domain|operator-after-sale')){
            $request->id = currentDomain();
        }
        $domains = Domain::identic('id', $request->id)->get()->toArray();
        if($user->hasRole('operator|master|owner|manager-s3')){
            array_unshift($domains, ['id' => 'root', 'title' => 'ROOT', 'name'=> 'ROOT']);
        }
        return $domains;
    }
}
