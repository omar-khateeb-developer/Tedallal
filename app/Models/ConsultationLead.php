<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultationLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'restaurant_name',
        'phone',
        'city',
        'service_type',
        'payment_model',
        'kitchen_setup',
        'desired_features',
        'notes',
        'ip_address',
        'user_agent',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'desired_features' => 'array',
        ];
    }
}
