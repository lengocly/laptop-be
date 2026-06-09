<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

// Fillable: chỉ những cột này được phép mass assignment (User::create($data)) → tránh lỗi MassAssignmentException
#[Fillable(['name', 'email', 'password'])]

// Hidden: những cột này sẽ không xuất ra JSON khi trả về API (password, remember_token).
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
// HasFactory → dùng factory để tạo mẫu test.
// Notifiable → gửi thông báo/email cho user.
// HasApiTokens → tích hợp Sanctum API tokens.
    use HasFactory, Notifiable , HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Laravel biết is_admin là true/false, không phải 0/1.
            'is_admin' => 'boolean',
        ];
    }
}
