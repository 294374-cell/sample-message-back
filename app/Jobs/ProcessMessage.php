<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job que "envia" uma mensagem. Aqui só simulamos com um sleep — em uma
 * aplicação real, este seria o ponto onde você chamaria um serviço externo
 * (SMTP, SMS, push, etc.) que tipicamente é lento e por isso deve rodar
 * fora do ciclo HTTP.
 */
class ProcessMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(public int $messageId)
    {
    }

    public function handle(): void
    {
        $message = Message::find($this->messageId);

        if (! $message) {
            Log::warning('ProcessMessage: mensagem não encontrada', [
                'message_id' => $this->messageId,
            ]);
            return;
        }

        Log::info('ProcessMessage: iniciando envio', [
            'message_id' => $message->id,
            'recipient' => $message->recipient,
        ]);

        // Simula trabalho lento (API externa, SMTP, etc.).
        sleep(3);

        $message->update([
            'status' => Message::STATUS_SENT,
            'sent_at' => now(),
        ]);

        Log::info('ProcessMessage: envio concluído', [
            'message_id' => $message->id,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $message = Message::find($this->messageId);

        if ($message) {
            $message->update([
                'status' => Message::STATUS_FAILED,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
