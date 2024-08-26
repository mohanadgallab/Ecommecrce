<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'address1', 'address2', 'city', 'state', 'country_code', 'zipcode', 'customer_id'];

    public function customer() {
        return $this->hasOne(Customer::class, 'user_id', 'customer_id');  //Updating 13-Aug-2024
    }

    public function country() {
        return $this->belongsTo(Country::class, 'country_code');
    }
}
