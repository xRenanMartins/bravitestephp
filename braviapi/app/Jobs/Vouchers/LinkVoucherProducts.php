<?php

namespace App\Jobs\Vouchers;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Models\VoucherProduct;

class LinkVoucherProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private $voucher, private $payload)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        if (isset($this->payload['type']) && isset($this->payload['products'])) {
            if ($this->payload['type'] == "id") {
                $this->voucher->products()->saveMany($this->getArrayProductsIds($this->payload['products']));
            } else {
                $this->voucher->products()->saveMany($this->getArrayProductsEan($this->payload['products']));
            }
        }
    }

    private function getArrayProductsEan($products)
    {
        if (!is_array($products)) {
            $products = explode(',', str_replace("\n", ',', $products));
        }
        $many = collect([]);
        foreach ($products as $remote) {
            $many->push(new VoucherProduct(["ean" => $remote, 'blacklist' => $this->payload['blacklist']]));
        }
        return $many;
    }

    private function getArrayProductsIds($products)
    {
        if (!is_array($products)) {
            $products = explode(',', str_replace("\n", ',', $products));
        }
        $many = collect([]);
        foreach ($products as $remote) {
            $many->push(new VoucherProduct(["product_id" => $remote, 'blacklist' => $this->payload['blacklist']]));
        }
        return $many;
    }
}
