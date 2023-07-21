<?php

namespace App\Http\Controllers;

use Packk\Core\Integration\Omie\StoreInvoiceOmie;
use Illuminate\Http\Request;
use Packk\Core\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $date = null;
        if (isset($request->start_date) && isset($request->end_date)) {
            $date = [$request->start_date, $request->end_date];
        }
        return Invoice::identic('id', $request->id)
            ->identic('domain_id', $request->domain_id)
            ->identic('date_reference', $request->competence)
            ->identic('status', $request->status)
            ->identic('tipo', $request->type)
            ->identic('reference_id', $request->reference_id)
            ->identic('reference_provider', $request->reference_provider)
            ->when(!is_null($date), function ($query, $date) {
                return $query->whereBetween("created_at", $date);
            })->orderByDesc('created_at')->simplePaginate($request->length);
    }

    public function generate(Request $request)
    {
        $date = null;
        if (isset($request->start_date) && isset($request->end_date)) {
            $date = [$request->start_date, $request->end_date];
        }

        return Invoice::query()
            ->identic('id', $request->id)
            ->identic('domain_id', $request->domain_id)
            ->identic('date_reference', $request->competence)
            ->identic('status', $request->status)
            ->identic('tipo', $request->type)
            ->identic('reference_id', $request->reference_id)
            ->identic('reference_provider', $request->reference_provider)
            ->when($date, function ($query, $date) {
                return $query->whereBetween("created_at", $date);
            })->orderByDesc('created_at')->get();
    }

    public function store(Request $request)
    {
        $payload = $this->validate($request, [
            'domain_id' => 'required',
            'reference_id' => 'required',
            'reference_provider' => 'required|in:loja,cliente',
            'amount' => 'required',
            'tipo' => 'required|in:marketTax,serviceTax,conciergeTax,shopkeeperTax,shopkeeperRetention,shopkeeperAdhesion,shopkeeperService',
        ]);

        $invoice = Invoice::create($payload);
        return $invoice;
    }

    public function show($id)
    {
        return Invoice::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, ['amount' => 'sometimes']);
        $invoice = Invoice::find($id);
        $invoice->update($payload);

        return $invoice;
    }

    public function destroy($id)
    {
        Invoice::destroy($id);
        return ['success' => true];
    }

    public function process($id, StoreInvoiceOmie $storeInvoiceOmie)
    {
        return $storeInvoiceOmie->execute($id);
    }
}