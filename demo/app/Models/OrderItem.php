<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use SalvatoreCervone\LogOperations\Traits\HasOperationLogs;

class OrderItem extends Model
{
    use HasOperationLogs;

    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
