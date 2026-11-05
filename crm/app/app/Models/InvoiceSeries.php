<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Written only by App\Services\Billing\NumberSeries, under a row lock. */
class InvoiceSeries extends Model
{
    protected $table = 'invoice_series';

    protected $fillable = ['branch_id', 'financial_year', 'type', 'prefix', 'next_number'];

    protected $casts = ['next_number' => 'integer'];
}
