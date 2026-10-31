<?php

namespace App\Filament\Resources\RoleLimitResource\Pages;

use App\Filament\Resources\RoleLimitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRoleLimits extends ManageRecords
{
    protected static string $resource = RoleLimitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
