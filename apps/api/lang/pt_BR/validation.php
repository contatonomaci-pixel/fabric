<?php

return [
    'required' => 'O campo :attribute é obrigatório.', 'email' => 'Informe um e-mail válido.',
    'string' => 'O campo :attribute deve ser um texto.', 'integer' => 'O campo :attribute deve ser um número inteiro.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'prohibited' => 'O campo :attribute não deve ser informado nesta operação.',
    'max' => ['string' => 'O campo :attribute deve ter no máximo :max caracteres.', 'numeric' => 'O campo :attribute deve ser no máximo :max.'],
    'min' => ['string' => 'O campo :attribute deve ter pelo menos :min caracteres.', 'numeric' => 'O campo :attribute deve ser pelo menos :min.'],
    'password' => ['letters' => 'A senha deve conter letras.', 'mixed' => 'A senha deve conter maiúsculas e minúsculas.', 'numbers' => 'A senha deve conter um número.', 'symbols' => 'A senha deve conter um símbolo.'],
    'attributes' => ['email' => 'e-mail', 'password' => 'senha', 'token' => 'token de recuperação', 'page' => 'página', 'per_page' => 'itens por página'],
];
