<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('requires and persists a damage reason during batch return inspection', function (): void {
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderReturnController.php')
    );
    $service = file_get_contents(
        base_path('packages/Webkul/Invoice/src/Services/DeliveryOrderReturnService.php')
    );
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/delivery-orders/return.blade.php')
    );

    expect($controller)
        ->toContain('RETURN DAMAGED NOTE V1')
        ->toContain("'return_notes.*'")
        ->toContain('Alasan kerusakan wajib diisi');

    expect($service)
        ->toContain('RETURN DAMAGED NOTE V1')
        ->toContain('$returnNotes')
        ->toContain('$damageNote')
        ->toContain(
            "if (\$condition === 'damaged' && (\$notes === null || \$notes === ''))"
        );

    expect($view)
        ->toContain('RETURN DAMAGED NOTE V1')
        ->toContain('data-damage-note-container')
        ->toContain('name="return_notes[{{ $allocation->id }}]"')
        ->toContain('syncDamageNoteField');
});
