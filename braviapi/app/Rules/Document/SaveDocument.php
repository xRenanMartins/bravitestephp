<?php

namespace App\Rules\Document;

use Packk\Core\Models\AppIdentifier;
use Packk\Core\Models\DocumentStore;
use Packk\Core\Models\Document;
use Illuminate\Support\Facades\Storage;

class SaveDocument
{
    public function execute($payload)
    {
        try {
            $document = isset($payload["id"]) ? Document::findOrFail($payload["id"]) : new Document();
            $document->name = $payload["name"];
            $document->description = $payload["description"];
            $document->type = $payload["type"];
            if (isset($payload["file"])) {
                if (isset($document->file)) {
                    $prefix = substr(Storage::url('/'), 0, -1);
                    Storage::delete(str_replace($prefix, '', $document->file));
                }
                $uri = $this->storeImage($payload["file"]);
                $document->file = $uri;
            }
            $document->save();

            if (!empty($payload["lojas"])) {
                DocumentStore::where("document_id", $document->id)->delete();
                foreach ($payload["lojas"] as $loja_id) {
                    $document_store = new DocumentStore();
                    $document_store->store_id = $loja_id;
                    $document_store->document_id = $document->id;
                    $document_store->save();
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function storeImage($file)
    {
        $uri = "";
        $domain = currentDomain(true);

        if (is_file($file)) {
            $nameFile = sha1(microtime()) . '.' . $file->getClientOriginalExtension();
            $base = file_get_contents($file);
        } else {
            $base64_str = substr($file, strpos($file, ",") + 1);
            $base = base64_decode($base64_str);
            $extension = explode('/', mime_content_type($file))[1];
            $nameFile = sha1(microtime()) . '.' . $extension;
        }

        Storage::put("domains/{$domain->id}/guides/" . $nameFile, $base);
        $uri = Storage::url("domains/{$domain->id}/guides/" . $nameFile);

        return $uri;
    }
}
