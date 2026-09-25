<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\RiskAlertNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class N8nAlertController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'risk_type' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:5000'],
            'data_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $alert = Alert::create([
            'risk_type' => $validated['risk_type'],
            'message' => $validated['message'],
            'data_date' => $validated['data_date'] ?? null,
            'source' => $validated['source'] ?? 'n8n-gemini',
        ]);

        $user = User::query()->first();
        if ($user) {
            $user->notify(new RiskAlertNotification($alert));
        }

        return response()->json(['ok' => true, 'id' => $alert->id], 201);
    }
}
