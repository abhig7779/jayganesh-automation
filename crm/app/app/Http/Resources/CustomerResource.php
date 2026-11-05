<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'mobile' => $this->mobile,                       // raw E.164: +919823107676
            'mobile_pretty' => Phone::pretty($this->mobile), // +91 98231 07676
            'alt_mobile' => $this->alt_mobile,
            'alt_mobile_pretty' => $this->alt_mobile ? Phone::pretty($this->alt_mobile) : null,
            'email' => $this->email,
            'area' => $this->area,
            'source' => $this->source,
            'source_label' => Customer::SOURCE_LABELS[$this->source] ?? $this->source,
            'whatsapp_opt_in' => $this->whatsapp_opt_in,
            'opted_out_at' => $this->opted_out_at?->toIso8601String(),
            'tags' => $this->tags ?? [],
            'notes' => $this->notes,
            'total_spent_paise' => $this->total_spent_paise,
            'last_visit_at' => $this->last_visit_at?->toIso8601String(),
            'archived' => $this->isArchived(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'cars' => CustomerCarResource::collection($this->whenLoaded('cars')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
