<?php

// Em produção, o frontend é servido pelo MESMO nginx que faz proxy
// da API. Ou seja, browser e API estão na mesma origem — não há
// requisição cross-origin e este arquivo é praticamente inerte.
//
// Mantido aqui apenas para o modo de desenvolvimento, em que o Vite
// dev server roda em http://localhost:5173 e bate em
// http://localhost:8000. Nesse caso, o navegador faz cross-origin
// (porque as portas são diferentes) e precisa do CORS liberado.

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
