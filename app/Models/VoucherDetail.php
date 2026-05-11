<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class VoucherDetail extends Model
{
    use HasFactory;

    /**
     * Indicates that the primary key is not auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket_number',
        'voucher_number',
        'account_code',
        'account_title',
        'type',
        'amount',
        'series_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'id' => 'string',
        'series_id' => 'string',
    ];

    /**
     * Boot function to auto-generate UUID on creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Optionally associate via ticket_number as well.
     */
    public function ticket()
    {
        return $this->belongsTo(Voucher::class, 'ticket_number', 'ticket_number');
    }
}
