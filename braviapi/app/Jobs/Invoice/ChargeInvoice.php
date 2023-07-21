<?php

namespace App\Jobs\Invoice;

use App\Rules\Invoice\ChargeInvoice as InvoiceChargeInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Packk\Core\Jobs\Invoice\SendInvoice;
use Packk\Core\Models\Invoice;
use Packk\Core\Models\InvoiceItem;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Traits\Delayable;
use Packk\Core\Traits\Loggable;

class ChargeInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue,Queueable,Loggable, SerializesModels, Delayable {
        Delayable::delay insteadof Queueable;
    }
    private $type;
    private $id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type)
    {
        $this->setType("invoice_packk");
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->type == 'CREATE_INVOICES') {
            $this->dispatchTypes();
        } else if ($this->type == "DELETE_INVOICES"){
            $invoices = Invoice::whereNull("service_id")->whereNull("log")->withoutGlobalScope('App\Scopes\DomainScope');
            InvoiceItem::withoutGlobalScope(DomainScope::class)->whereIn("invoice_id", $invoices->pluck("id")->toArray())->forceDelete();
            $invoices->forceDelete();
        }else{
            (new InvoiceChargeInvoice())->execute($this->type);
            dispatch(new SendInvoice($this->type));
        }
    }
    private function dispatchTypes(): void
    {
        dispatch(new ChargeInvoice('shopkeeperTax'));
//        dispatch(new ChargeInvoice('shopkeeperService'));
//        dispatch(new ChargeInvoice('shopkeeperRetention'));
//        dispatch(new ChargeInvoice('shopkeeperAdhesion'));
//        dispatch(new ChargeInvoice('conciergeTax'));
//        dispatch(new ChargeInvoice('serviceTax'));
//        dispatch(new ChargeInvoice('marketTax'));
    }
}
