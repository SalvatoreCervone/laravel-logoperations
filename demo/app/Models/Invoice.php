<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use SalvatoreCervone\LogOperations\Traits\HasOperationLogs;

class Invoice extends Model
{
    use HasOperationLogs;

    protected $guarded = [];

    protected array $logParents = ['order'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
