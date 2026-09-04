<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('keeps the inventory movement ledger fresh and newest first', function (): void {
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryMovementController.php')
    );
    $dataGrid = file_get_contents(
        base_path('packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php')
    );
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/inventory/movements/index.blade.php')
    );

    expect($controller)
        ->toContain('INVENTORY MOVEMENT LIVE V1')
        ->toContain('no-store, no-cache, must-revalidate');

    expect($dataGrid)
        ->toContain("protected \$sortColumn = 'occurred_at'")
        ->toContain("protected \$sortOrder = 'desc'");

    expect($view)
        ->toContain('INVENTORY MOVEMENT LIVE V1')
        ->toContain('reload-datagrids')
        ->toContain('10000')
        ->toContain('resetView');
});
