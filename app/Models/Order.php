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
    public function user(){
        return $this->hasOne(User::class, 'id','created_by');
    }
    // public function customer(){
    //     return $this->hasOne(Customer::class, 'user_id','created_by');
    // }
    
    public function items(){
        return $this->hasMany(OrderItem::class);
    }
}
