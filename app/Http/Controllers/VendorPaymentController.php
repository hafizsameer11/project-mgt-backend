<?php

namespace App\Http\Controllers;

use App\Models\VendorPayment;
use App\Models\VendorBill;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = VendorPayment::with('vendor', 'bill', 'creator');

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        $payments = $query->orderBy('payment_date', 'desc')->paginate(15);
        return response()->json($payments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'vendor_bill_id' => 'nullable|exists:vendor_bills,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,check,bank_transfer,card,other',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $paymentNo = 'VPAY-' . strtoupper(Str::random(8));

        $data = $request->all();
        $data['payment_no'] = $paymentNo;
        $data['created_by'] = $request->user()->id;

        $payment = VendorPayment::create($data);

        // Update bill if linked
        if ($request->vendor_bill_id) {
            $bill = VendorBill::find($request->vendor_bill_id);
            if ($bill) {
                $bill->amount_paid += $request->amount;
                $bill->remaining_amount = $bill->total_amount - $bill->amount_paid;
                $bill->updateStatus();
            }
        }

        return response()->json($payment->load('vendor', 'bill', 'creator'), 201);
    }

    public function show(int $id)
    {
        $payment = VendorPayment::with('vendor', 'bill', 'creator')->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        return response()->json($payment);
    }

    public function update(Request $request, int $id)
    {
        $payment = VendorPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $request->validate([
            'payment_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'sometimes|in:cash,check,bank_transfer,card,other',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $oldAmount = $payment->amount;
        $payment->update($request->all());

        // Update bill if amount changed
        if ($payment->vendor_bill_id && $request->has('amount')) {
            $bill = VendorBill::find($payment->vendor_bill_id);
            if ($bill) {
                $bill->amount_paid = $bill->amount_paid - $oldAmount + $request->amount;
                $bill->remaining_amount = $bill->total_amount - $bill->amount_paid;
                $bill->updateStatus();
            }
        }

        return response()->json($payment->load('vendor', 'bill', 'creator'));
    }

    public function destroy(int $id)
    {
        $payment = VendorPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Update bill if linked
        if ($payment->vendor_bill_id) {
            $bill = VendorBill::find($payment->vendor_bill_id);
            if ($bill) {
                $bill->amount_paid -= $payment->amount;
                $bill->remaining_amount = $bill->total_amount - $bill->amount_paid;
                $bill->updateStatus();
            }
        }

        $payment->delete();
        return response()->json(['message' => 'Payment deleted successfully']);
    }
}
