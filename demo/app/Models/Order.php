<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use SalvatoreCervone\LogOperations\Traits\HasOperationLogs;

class Order extends Model
{
    use HasOperationLogs;

    protected $guarded = [];
}
