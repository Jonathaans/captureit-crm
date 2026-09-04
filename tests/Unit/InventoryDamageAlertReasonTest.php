<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('shows the latest return damage reason in inventory alerts', function (): void {
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryAlertController.php')
    );
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/inventory/alerts/index.blade.php')
    );

    expect($controller)
        ->toContain('INVENTORY DAMAGE ALERT REASON V1')
        ->toContain('latest_damage_notes')
        ->toContain('damage_reason')
        ->toContain('damage_reference');

    expect($view)
        ->toContain('INVENTORY DAMAGE ALERT REASON V1')
        ->toContain('Alasan rusak:')
        ->toContain("\$alert['damage_reason']");
});
