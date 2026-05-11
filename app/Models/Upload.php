<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Upload extends Model
{
    use HasFactory;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key type is a UUID string.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'ticket_number',
        'type',
        'amount',
        'branch_number',
        'voucher_number',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Boot method to auto-generate UUIDs.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto-generate UUID if not set
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            // Auto-assign the authenticated user's ID
            if (empty($model->user_id) && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }

    /**
     * Get the user who uploaded the record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch by branch_number.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_number', 'branch_number');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'ticket_number', 'ticket_number');
    }
}
