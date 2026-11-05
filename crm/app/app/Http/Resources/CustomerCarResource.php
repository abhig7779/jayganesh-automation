<?php

namespace App\Http\Resources;

use App\Models\CustomerCar;
use App\Support\RegNo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerCar */
class CustomerCarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'make' => $this->make,
            'model' => $this->model,
            'variant' => $this->variant,
            'label' => $this->label(),
            'year' => $this->year,
            'reg_no' => $this->reg_no,
            'reg_no_pretty' => RegNo::pretty($this->reg_no),
            'colour' => $this->colour,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
