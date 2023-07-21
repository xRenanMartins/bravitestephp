<?php

namespace App\Rules\Invoice;

use Packk\Core\Models\Invoice;
use Packk\Core\Models\InvoiceItem;
use Packk\Core\Models\Setting;
use Packk\Core\Scopes\DomainScope;

class ChargeInvoice
{

    public string $start_date;
    public string $end_date;
    public string $date_reference;

    public function __construct($month = null, $start = null, $end = null)
    {
        $month = $month ?? Setting::getGlobal("COUNT_ISSUE_MONTH", 0);
        $start = $start ?? Setting::getGlobal("START_ISSUE_DAY", 25);
        $end = $end ?? Setting::getGlobal("END_ISSUE_DAY", 25);

        $end_date = now()->addMonths($month)->day($end);
        if ($start >= $end) {
            $date_reference = $end_date->format("m/Y");
            $start_date = now()->addMonths($month - 1)->day($start);
        } else {
            $start_date = now()->addMonths($month)->day($start);
            $date_reference = $start_date->format("m/Y");
        }
        $this->end_date = $end_date->format("Y-m-d");
        $this->date_reference = $date_reference;
        $this->start_date = $start_date->format("Y-m-d");
    }

    public function execute($type): void
    {
        switch ($type) {
            case 'shopkeeperTax':
                $this->runShopkeeperTax();
                break;
            case 'shopkeeperService':
                $this->runShopkeeperService();
                break;
            case 'shopkeeperRetention':
                $this->runShopkeeperRetention();
                break;
            case 'shopkeeperAdhesion':
                $this->runShopkeeperAdhesion();
                break;
            case 'conciergeTax':
                $this->runConciergeTax();
                break;
            case 'serviceTax':
                $this->runServiceTax();
                break;
            case 'runMarketTax':
                $this->runMarketTax();
                break;
        }
    }

    private function runMarketTax(): void
    {
        $remotes = LoadInvoice::marketTax($this->start_date, $this->end_date);
        $this->createInvoice($remotes, "marketTax");
    }

    private function runServiceTax(): void
    {
        $remotes = LoadInvoice::serviceTax($this->start_date, $this->end_date);
        $this->createInvoice($remotes, "serviceTax");
    }

    private function runConciergeTax(): void
    {
        $remotes = LoadInvoice::conciergeTax($this->start_date, $this->end_date);
        $this->createInvoice($remotes, "conciergeTax");
    }

    private function runShopkeeperTax(): void
    {
        $remotes = LoadInvoice::shopkeeperTax($this->start_date, $this->end_date);
        $this->createInvoice($remotes, "shopkeeperTax");
    }

    private function runShopkeeperRetention(): void
    {
        $remotes = LoadInvoice::shopkeeperRetention($this->start_date, $this->end_date);
        $this->createInvoice($remotes, "shopkeeperRetention");
    }

    private function runShopkeeperAdhesion(): void
    {
        $remotes = LoadInvoice::shopkeeperAdhesion($this->start_date, $this->end_date);
        $this->createInvoice($remotes, "shopkeeperAdhesion");
    }

    private function runShopkeeperService(): void
    {
        $remotes = LoadInvoice::shopkeeperService($this->start_date, $this->end_date);
        $this->createInvoice($remotes, "shopkeeperService");
    }

    public function createInvoice($remotes, $function): void
    {
        foreach ($remotes as $remote) {
            $invoice = Invoice::withoutGlobalScope(DomainScope::class)
                ->where("reference_id", $remote["owner_id"])
                ->where("reference_provider", $remote["owner_provider"])
                ->where("date_reference", $this->date_reference)->first();
            if (!isset($invoice)) {
                $invoice = Invoice::create([
                    'tipo' => $function,
                    'reference_id' => $remote["owner_id"],
                    'reference_provider' => $remote["owner_provider"],
                    'domain_id' => $remote["domain_id"],
                    'date_reference' => $this->date_reference,
                    'amount' => $remote["amount"],
                    'payout_amount' => $remote["payout_amount"]
                ]);
            } else {
                $invoice->amount = $remote["amount"];
                $invoice->payout_amount = $remote["payout_amount"];
                $invoice->status = 'pending';
                $invoice->save();
                $invoice->invoice_items()->forceDelete();
            }
            $this->vinculeFranchise($invoice);

            $items = LoadInvoice::$function($this->start_date, $this->end_date, [$remote["owner_id"]]);
            $invoice_items = [];
            foreach ($items as $item) {
                $invoice_items[] = [
                    'reference_id' => $item["reference_id"],
                    'reference_provider' => $item["reference_provider"],
                    'invoice_id' => $invoice->id,
                    'amount' => $item["amount"],
                    'payout_amount' => $item["payout_amount"],
                    'created_at' => now(),
                    'updated_at' => now(),
                    'domain_id' => $invoice->domain_id
                ];
            }
            InvoiceItem::insert($invoice_items);
        }
    }

    public function vinculeFranchise($invoice)
    {
        try {
            $store = $invoice->customer();
            if (isset($store->franchise_id)) {
                $invoice->franchise_id = $store->franchise_id;
                $invoice->save();
            }
        } catch (\Exception $e) {
        }
    }
}