<?php

namespace App\Filament\Resources\AiCreditPackageResource\Pages;

use App\Filament\Resources\AiCreditPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAiCreditPackages extends ManageRecords
{
    protected static string $resource = AiCreditPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
