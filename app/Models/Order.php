<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = ['status', 'total_price', 'created_by', 'updated_by'];

    public function isPaid(){
        return $this->status == OrderStatus::Paid->value ;
    }
    public function payment(){
        return $this->hasOne(Payment::class);
    }
    public function items(){
        return $this->hasMany(OrderItem::class);
    }
}
