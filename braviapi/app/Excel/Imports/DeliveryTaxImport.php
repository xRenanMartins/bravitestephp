<?php

namespace App\Excel\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class DeliveryTaxImport implements ToCollection
{
    private array $rows = [];

    /**
     * @throws \Throwable
     */
    public function collection(Collection $collection)
    {
        $ids = [];
        foreach ($collection as $line) {
            if (is_numeric($line[0])) {
                $ids[] = $line[0];
            }
        }

        throw_if(!count($ids), new \Exception('O arquivo enviado possui dados inválidos'));
        $this->rows = $ids;
    }

    public function getRows()
    {
        return $this->rows;
    }
}