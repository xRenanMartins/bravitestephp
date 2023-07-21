<?php
namespace App\Http\Controllers;

use Packk\Core\Models\Invoice;
use Packk\Core\Models\InvoiceItem;
use Packk\Core\Integration\Omie\StoreInvoiceOmie;
use Illuminate\Http\Request;

class InvoiceItemController extends Controller
{
    public function __construct()
    {

    }

    public function index(Request $request, $invoice_id)
    {
        if(isset($request->start) and isset($request->length)){
            $total = $request->start/$request->length;
            $page = ($total+1) > 0 ? ceil($total) + 1 : 1;

            $request->merge([
                'page' => $page
            ]);
        }
        $invoice = Invoice::findOrFail($invoice_id);
            
        return $invoice->invoice_items()
        ->paginate($request->length);
    }

    public function store(Request $request,$invoice_id){
        $payload = $this->validate($request, self::storeRule());
        $invoice = InvoiceItem::create($payload);
        return $invoice;
    }

    public function show($id){
        return InvoiceItem::findOrFail($id);
    }

    public function update(Request $request,$invoice_id, $id){
        $payload = $this->validate($request, self::updateRule());
        $invoice = InvoiceItem::find($id);
        $invoice->update($payload);

        return $invoice;
    }

    public function destroy($invoice_id,$id){
        InvoiceItem::destroy($id);
        return ['success' => true];
    }

    private static function storeRule(){
        return [
            'domain_id' => 'required',
            'reference_id' => 'required',
            'reference_provider' => 'required|in:pedido,cliente',
            'amount' => 'required',
        ];
    }
    private static function updateRule(){
        return [
            'amount' => 'sometimes',
        ];
    }
}