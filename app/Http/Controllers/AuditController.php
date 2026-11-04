<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate(['q' => 'nullable|string|max:150', 'action' => 'nullable|string|max:100', 'user_id' => 'nullable|integer', 'subject_id' => 'nullable|integer', 'subject_type' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $logs = AuditLog::with('user')
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where(fn ($query) => $query->where('subject_type', 'like', '%'.$term.'%')->orWhere('subject_id', $term)->orWhere('action', 'like', '%'.$term.'%')))
            ->when($data['subject_id'] ?? null, fn ($query, $id) => $query->where('subject_id', $id))
            ->when($data['subject_type'] ?? null, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($data['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($data['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')->paginate(30)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
