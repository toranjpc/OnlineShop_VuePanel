<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Product\Models\Invoice;

class AccountingController extends Controller
{
    public function nextInvoiceNumber(Request $request)
    {
        try {
            $query = Invoice::withTrashed();

            if ($request->filled('kind')) {
                $query->where('kind', $request->input('kind'));
            }

            $lastId = (int) $query->max('id');
            $invoiceNumber = (string) ($lastId + 1);
            $invoiceNumber = jdate()->format('ymd') . "_" . $invoiceNumber;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'invoice_number' => $invoiceNumber,
                    // 'last_id' => $lastId,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در دریافت شماره فاکتور',
            ], 500);
        }
    }
}
