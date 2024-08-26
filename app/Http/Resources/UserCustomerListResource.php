<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserCustomerListResource extends JsonResource
{
    public static $wrap = false ;
    public $collects = Customer::class;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ,
            'email' => $this->email ,
            'name' => $this->name,
            // 'first_name' => $this->customer->first_name,
            // 'last_name' => $this->customer->last_name,
             'customer' => $this->customer ,
             'phone' => $this->customer->phone,
             'shippingAddress' => $this->customer->shippingAddress,
             'billingAddress' => $this->customer->billingAddress,
             'address1' => $this->customer->address1,
             'country' => $this->customer->country->name
        ];
    }
}
