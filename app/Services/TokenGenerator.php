<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TokenGenerator
{
    public function generatePublicToken(): string
    {
        return $this->generateUniqueToken('restaurant_tables', 'public_token');
    }

    public function generateSessionToken(): string
    {
        return $this->generateUniqueToken('table_sessions', 'session_token');
    }

    private function generateUniqueToken(string $table, string $column): string
    {
        do {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } while (DB::table($table)->where($column, $token)->exists());

        return $token;
    }
}
