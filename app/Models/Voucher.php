<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Voucher extends Model
{
    use HasFactory;

    /**
     * Indicates if the model's primary key is not auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
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
        'voucher_number',
        'ticket_number',
        'payee',
        'ck_number',
        'description',
        'branch_name',
        'branch_address',
        'prepared_by',
        'prepared_designation',
        'checked_by',
        'checked_designation',
        'approved_by',
        'approved_designation',
        // 'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
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



    public function details()
    {
        return $this->hasMany(VoucherDetail::class, 'ticket_number', 'ticket_number');
    }

    public function upload()
    {
        return $this->hasOne(Upload::class, 'ticket_number', 'ticket_number');
    }

    public function voucherDetails()
    {
        return $this->hasMany(VoucherDetail::class, 'ticket_number', 'ticket_number');
    }





    // Optional relationship stubs

    // public function preparedByUser()
    // {
    //     return $this->belongsTo(User::class, 'prepared_by');
    // }

    // public function branch()
    // {
    //     return $this->belongsTo(Branch::class, 'branch_id');
    // }
}
