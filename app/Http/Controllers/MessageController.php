<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMessage;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(): JsonResponse
    {
        $messages = Message::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = Message::create([
            'recipient' => $data['recipient'],
            'body' => $data['body'],
            'status' => Message::STATUS_PENDING,
        ]);

        // Despacha para a fila do Redis. A API responde imediatamente
        // (status 201) sem esperar o "envio" terminar — o worker faz isso.
        ProcessMessage::dispatch($message->id);

        return response()->json($message, 201);
    }

    public function show(Message $message): JsonResponse
    {
        return response()->json($message);
    }
}
