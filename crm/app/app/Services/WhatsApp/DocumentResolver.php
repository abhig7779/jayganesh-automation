<?php

namespace App\Services\WhatsApp;

use App\Models\MessageLog;

/**
 * Makes the PDF for a log whose document is still pending (the e-bill PDF is rendered after the
 * response, inside the job, so saving a bill stays fast). Today it knows invoices; quotations can
 * add their own branch here after the merge.
 */
class DocumentResolver
{
    public function resolve(MessageLog $log): ?string
    {
        $related = $log->related;
        if ($related === null) {
            return null;
        }

        if (class_basename($related) === 'Invoice') {
            return InvoiceMessage::fromInvoice($related)->pdfPath();
        }

        return null;
    }
}
