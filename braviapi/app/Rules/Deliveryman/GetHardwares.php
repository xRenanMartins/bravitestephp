<?php

namespace App\Rules\Deliveryman;

use Packk\Core\Models\Deliveryman;

class GetHardwares
{
    public function execute($id)
    {
        $deliveryman = Deliveryman::findOrFail($id);
        $hardwares = collect([]);
        $deliveryman->user->hardwares->each(function ($hardware) use ($hardwares) {
            $hardware->pivot->hardware_name = $hardware->description;
            $hardwares->push([
                "id" => $hardware->pivot->id,
                "number" => $hardware->pivot->number,
                "service_id" => $hardware->pivot->service_id,
                "type" => $hardware->name,
                "hardware_name" => $hardware->description,
                "is_with" => $hardware->pivot->is_with,
                "model" => $hardware->pivot->model,
                "brand" => $hardware->pivot->brand
            ]);
        });
        return $hardwares;
    }
}