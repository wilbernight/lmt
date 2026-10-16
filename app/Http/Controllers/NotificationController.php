<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->limit(50)->get()->map(fn ($n) => [
            'id' => $n->id,
            ...$n->data,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at->toIso8601String(),
        ]);

        return response()->json($notifications);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
