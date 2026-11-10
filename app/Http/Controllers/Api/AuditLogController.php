<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = AuditLog::query()->with('user:id,name,email');
        foreach (['action', 'entity_type', 'entity_id', 'user_id'] as $field) {
            if (isset($data[$field])) $query->where($field, $data[$field]);
        }
        if (!empty($data['date_from'])) $query->whereDate('created_at', '>=', $data['date_from']);
        if (!empty($data['date_to'])) $query->whereDate('created_at', '<=', $data['date_to']);
        return response()->json($query->latest('created_at')->paginate($data['per_page'] ?? 30)->withQueryString());
    }
}
