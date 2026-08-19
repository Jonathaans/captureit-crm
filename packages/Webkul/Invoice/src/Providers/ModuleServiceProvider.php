<?php

namespace Webkul\Invoice\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\InvoiceItem;
use Webkul\Invoice\Models\Payment;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Invoice::class,
        InvoiceItem::class,
        Payment::class,
    ];
}