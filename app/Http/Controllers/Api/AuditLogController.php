<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->with('user:id,name,email');
        $query->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')));
        $query->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->input('entity_type')));
        $query->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')));
        return response()->json($query->latest('created_at')->paginate(min($request->integer('per_page', 30), 100)));
    }
}
