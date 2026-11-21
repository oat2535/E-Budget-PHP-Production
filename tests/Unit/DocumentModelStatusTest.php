<?php

namespace Tests\Unit;

use App\Models\BudgetPlanItem;
use App\Models\Car;
use App\Models\ComputerEquipment;
use App\Models\Furniture;
use App\Models\GlActualTemplate;
use App\Models\GlEntry;
use App\Models\MedicalEquipment;
use App\Models\NonVetManpower;
use App\Models\PositionAdjustment;
use App\Models\Renovate;
use App\Models\Revenue;
use App\Models\Software;
use App\Models\ToolsEquipment;
use App\Models\VetManpower;
use Tests\TestCase;

class DocumentModelStatusTest extends TestCase
{
    /**
     * @return array<class-string>
     */
    protected function getDocumentModelClasses(): array
    {
        return [
            VetManpower::class,
            NonVetManpower::class,
            PositionAdjustment::class,
            MedicalEquipment::class,
            ComputerEquipment::class,
            Furniture::class,
            ToolsEquipment::class,
            Car::class,
            Renovate::class,
            Software::class,
            GlEntry::class,
            Revenue::class,
            BudgetPlanItem::class,
            GlActualTemplate::class,
        ];
    }

    public function test_all_14_document_models_have_status_constants_and_default_pending(): void
    {
        foreach ($this->getDocumentModelClasses() as $modelClass) {
            $this->assertSame('PENDING', $modelClass::STATUS_PENDING, "{$modelClass}::STATUS_PENDING should be 'PENDING'");
            $this->assertSame('APPROVED', $modelClass::STATUS_APPROVED, "{$modelClass}::STATUS_APPROVED should be 'APPROVED'");
            $this->assertSame('CANCELLED', $modelClass::STATUS_CANCELLED, "{$modelClass}::STATUS_CANCELLED should be 'CANCELLED'");

            $model = new $modelClass();
            $this->assertSame('PENDING', $model->status, "{$modelClass} default status should be 'PENDING'");
        }
    }
}
