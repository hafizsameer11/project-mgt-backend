<?php

namespace App\Http\Controllers;

use App\Models\VendorBill;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorBillController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $query = VendorBill::with('vendor', 'purchaseOrder', 'project', 'creator');

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bills = $query->orderBy('bill_date', 'desc')->paginate(15);
        return response()->json($bills);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'project_id' => 'nullable|exists:projects,id',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'nullable|string',
        ]);

        $billNo = 'BILL-' . strtoupper(Str::random(8));

        $data = $request->only([
            'vendor_id',
            'purchase_order_id',
            'project_id',
            'bill_date',
            'due_date',
            'total_amount',
            'notes',
        ]);
        $data['bill_no'] = $billNo;
        $data['amount_paid'] = 0;
        $data['remaining_amount'] = $request->total_amount;
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('invoice_file')) {
            $data['invoice_file_path'] = $this->fileUploadService->upload($request->file('invoice_file'), 'vendor_bills');
        }

        $bill = VendorBill::create($data);
        return response()->json($bill->load('vendor', 'purchaseOrder', 'project'), 201);
    }

    public function show(int $id)
    {
        $bill = VendorBill::with('vendor', 'purchaseOrder', 'project', 'creator', 'approver', 'payments')->find($id);
        if (!$bill) {
            return response()->json(['message' => 'Vendor bill not found'], 404);
        }
        return response()->json($bill);
    }

    public function update(Request $request, int $id)
    {
        $bill = VendorBill::find($id);
        if (!$bill) {
            return response()->json(['message' => 'Vendor bill not found'], 404);
        }

        $request->validate([
            'bill_date' => 'sometimes|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'sometimes|numeric|min:0',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'nullable|string',
        ]);

        $data = $request->only(['bill_date', 'due_date', 'total_amount', 'notes']);

        if ($request->hasFile('invoice_file')) {
            $data['invoice_file_path'] = $this->fileUploadService->upload($request->file('invoice_file'), 'vendor_bills');
        }

        if (isset($data['total_amount'])) {
            $data['remaining_amount'] = $data['total_amount'] - $bill->amount_paid;
        }

        $bill->update($data);
        $bill->updateStatus();
        return response()->json($bill->load('vendor', 'purchaseOrder', 'project'));
    }

    public function approve(Request $request, int $id)
    {
        $bill = VendorBill::find($id);
        if (!$bill) {
            return response()->json(['message' => 'Vendor bill not found'], 404);
        }

        $bill->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json($bill->load('approver'));
    }

    public function destroy(int $id)
    {
        $bill = VendorBill::find($id);
        if (!$bill) {
            return response()->json(['message' => 'Vendor bill not found'], 404);
        }
        $bill->delete();
        return response()->json(['message' => 'Vendor bill deleted successfully']);
    }
}
