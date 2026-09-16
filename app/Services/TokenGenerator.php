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
        // في حالة نادرة جداً يتولّد نفس الـ token من طلبين متزامنين،
        // نُعيد المحاولة بدلاً من رمي exception.
        for ($i = 0; $i < 5; $i++) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            if (! DB::table($table)->where($column, $token)->exists()) {
                return $token;
            }
        }

        // احتمال ضئيل جداً — إذا فشلت 5 محاولات نرمي exception واضحة
        throw new \RuntimeException('فشل توليد token فريد بعد 5 محاولات. يُرجى المحاولة مجدداً.');
    }
}
