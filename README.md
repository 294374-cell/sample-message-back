# Backend — Sistema de Envio de Mensagens

API em **Laravel 12** que:

- Persiste mensagens no **PostgreSQL**.
- Despacha o "envio" para uma **fila** rodando em **Redis**.
- É consumida por um **worker** (`php artisan queue:work`).

> Este é **um dos dois repositórios** do trabalho. O outro é o
> [`frontend`](../frontend) (React + Vite + nginx). Para o
> `docker-compose` funcionar, ambos devem ser clonados como pastas
> **irmãs** (mesmo diretório pai).

## Arquitetura geral

```
                              ┌──────────────┐
                              │   Browser    │
                              └──────┬───────┘
                                     │  http://localhost
                                     ▼
                              ┌──────────────┐
                              │    NGINX     │  (definido no repo frontend)
                              │  (gateway)   │
                              │              │
                              │  /      ───► serve React (dist/)
                              │  /api/* ───► proxy → backend:8000
                              └──────┬───────┘
                                     │
                                     ▼
                              ┌──────────────┐
                              │   Backend    │   ← este repositório
                              │  (Laravel)   │
                              └──────┬───────┘
                       ┌─────────────┼─────────────┐
                       ▼             ▼             ▼
                ┌──────────┐  ┌──────────┐  ┌──────────┐
                │ Postgres │  │  Redis   │ ◄│  Worker  │
                │          │  │  (fila)  │  │ (artisan)│
                └──────────┘  └──────────┘  └────┬─────┘
                       ▲                         │
                       └─────────────────────────┘
                          grava status final
```

O backend **não fica exposto pro browser**. Quem expõe é o nginx (no repo
frontend), que faz proxy de `/api/*` pra cá.

## O que tem aqui

```
backend/
├── app/
│   ├── Http/Controllers/MessageController.php   # POST/GET /api/messages
│   ├── Jobs/ProcessMessage.php                  # job que vai pra fila
│   └── Models/Message.php
├── config/
│   ├── database.php                # conexões pgsql e redis
│   ├── queue.php                   # fila sobre redis
│   └── ...
├── database/migrations/
│   ├── 2026_01_01_000000_create_messages_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   └── 0001_01_01_000002_create_jobs_table.php
├── routes/api.php
├── .env.example
└── composer.json
```

## API

| Método | Rota                  | Descrição                              |
|--------|-----------------------|----------------------------------------|
| GET    | `/api/messages`       | Lista as últimas 50 mensagens          |
| POST   | `/api/messages`       | Cria mensagem e despacha job pra fila  |
| GET    | `/api/messages/{id}`  | Detalha uma mensagem específica        |

### `POST /api/messages`

```json
{
  "recipient": "joao@exemplo.com",
  "body": "Olá mundo"
}
```

Resposta `201 Created`:

```json
{
  "id": 1,
  "recipient": "joao@exemplo.com",
  "body": "Olá mundo",
  "status": "pending",
  "sent_at": null,
  "created_at": "2026-06-03T12:00:00.000000Z",
  "updated_at": "2026-06-03T12:00:00.000000Z"
}
```

Em ~3 segundos (com worker rodando) o status na próxima leitura será `sent`.

## Pré-requisitos para rodar local

- PHP 8.2+ com as extensões: `pdo_pgsql`, `mbstring`, `openssl`,
  `tokenizer`, `xml`, `ctype`, `json`, `pcntl`.
- Composer.
- PostgreSQL 14+ acessível (crie o database `mensagens`).
- Redis acessível.

## Rodando local (sem Docker)

Este modo serve pra você desenvolver e depurar **sem** o nginx. O proxy
fica por conta do Vite dev server do frontend (veja o README de lá).

```bash
# 1) Instalar dependências
composer install

# 2) Configurar ambiente
cp .env.example .env
php artisan key:generate

# 3) Subir Postgres/Redis (locais), ajustar .env, migrar
php artisan migrate

# 4) Servidor HTTP da API
php artisan serve            # http://localhost:8000
```

**Em outro terminal**, suba o worker:

```bash
php artisan queue:work
```

> O `queue:work` é um processo de longa duração — deixe-o aberto. Ele
> conecta no Redis, faz `BLPOP` em loop esperando jobs, e processa cada
> um. Se você matar esse processo, novas mensagens ficam paradas em
> `status=pending` até alguém subir o worker de novo.

## Verificando que a fila funciona

```bash
# Em uma aba:
php artisan queue:work --verbose

# Em outra aba:
curl -X POST http://localhost:8000/api/messages \
  -H "Content-Type: application/json" \
  -d '{"recipient":"teste@exemplo.com","body":"oi"}'
```

Você verá o worker imprimir algo como:

```
App\Jobs\ProcessMessage ........... RUNNING
App\Jobs\ProcessMessage ........... DONE
```

Inspecionando a fila no Redis:

```bash
redis-cli LRANGE laravel_database_queues:default 0 -1
```

## Falhas

Se o job lançar exceção, o Laravel tenta `tries=3` vezes (definido em
`app/Jobs/ProcessMessage.php`) com `backoff=5s`. Se todas falharem, o job
vai pra tabela `failed_jobs`:

```bash
php artisan queue:failed       # lista
php artisan queue:retry all    # tenta de novo
```

E o método `failed()` do job marca a mensagem com `status=failed`.

## O que você precisa entregar (parte da tarefa)

A entrega final do trabalho exige que tudo rode em containers, com o
**nginx como ponto único de entrada**. Você precisa criar:

- [ ] **`Dockerfile`** neste repositório (backend + worker compartilham).
- [ ] **`docker-compose.yml`** orquestrando os 5 serviços. A convenção
      sugerida é deixar este arquivo aqui mesmo (no repo do backend),
      apontando para `../frontend` como contexto do nginx — desse jeito,
      basta o aluno clonar os dois repos como pastas irmãs e rodar
      `docker compose up --build` daqui.
- [ ] Garantir que o **worker** use a MESMA imagem do backend, só com
      `command:` diferente (`php artisan queue:work`).
- [ ] Subir Postgres com **volume persistente**.
- [ ] Subir Redis (broker da fila + cache).
- [ ] Conectar tudo numa rede docker comum — backend acessa
      `postgres` e `redis` pelo nome do serviço; nginx (no repo
      frontend) acessa este backend como `backend:8000`.

> Não exponha porta do backend, nem do Postgres, nem do Redis para o
> host. **Só o nginx** deve mapear `80:80`.

### Diretório de trabalho esperado

```
algum-pai/
├── backend/    ← este repositório, onde fica o docker-compose.yml
└── frontend/   ← o outro repositório (clone como irmão)
```

## Observações úteis para containerizar

- backend e worker precisam das MESMAS variáveis de ambiente (inclusive
  `APP_KEY`). Gere uma vez e use em ambos.
- `DB_HOST` e `REDIS_HOST` devem apontar para os nomes dos serviços do
  compose (não `127.0.0.1`).
- A pasta `storage/` precisa ser **gravável** dentro do container.
- Não confie na ordem de boot: backend pode subir antes do Postgres
  estar pronto. Use `depends_on` com `healthcheck` ou um wrapper de espera.
- Como o nginx fica na frente, configure `TRUSTED_PROXIES=*` no `.env`
  para o Laravel respeitar `X-Forwarded-*`.
- Logs do Laravel só aparecem em `docker compose logs` se você direcionar
  para `stderr` (ex.: `LOG_STACK=stderr`).
