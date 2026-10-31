<?php

namespace App\Filament\Resources\AiModelResource\Pages;

use App\Filament\Resources\AiModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAiModels extends ManageRecords
{
    protected static string $resource = AiModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
