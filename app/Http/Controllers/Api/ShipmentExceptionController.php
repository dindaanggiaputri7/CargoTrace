<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentExceptionController extends Controller
{
    public function index(Request $request, Shipment $shipment)
    {
        if ($request->user()->isCustomer()) abort_unless((int) $shipment->customer_id === (int) $request->user()->id, 404);
        return response()->json(['data' => $shipment->exceptions()->with('reporter:id,name')->latest('detected_at')->get()]);
    }

    public function store(Request $request, Shipment $shipment, ExceptionService $service)
    {
        $data = $request->validate([
            'exception_type' => ['required', Rule::in(ShipmentException::TYPES)],
            'severity' => ['required', Rule::in(ShipmentException::SEVERITIES)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'detected_at' => ['nullable', 'date'],
        ]);
        $exception = $service->create($shipment, $data, $request->user());
        return response()->json(['data' => $exception], 201);
    }

    public function updateStatus(Request $request, ShipmentException $exception, ExceptionService $service)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(ShipmentException::STATUSES)],
            'resolution_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $updated = $service->updateStatus($exception, $data['status'], $data['resolution_notes'] ?? null, $request->user());
        return response()->json(['data' => $updated]);
    }
}
