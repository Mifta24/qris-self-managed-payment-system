<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrisMerchants;

use App\Filament\Resources\QrisMerchants\Pages\CreateQrisMerchant;
use App\Filament\Resources\QrisMerchants\Pages\EditQrisMerchant;
use App\Filament\Resources\QrisMerchants\Pages\ListQrisMerchants;
use App\Filament\Resources\QrisMerchants\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\QrisMerchants\Schemas\QrisMerchantForm;
use App\Filament\Resources\QrisMerchants\Tables\QrisMerchantsTable;
use App\Models\QrisMerchant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QrisMerchantResource extends Resource
{
    protected static ?string $model = QrisMerchant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return QrisMerchantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QrisMerchantsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQrisMerchants::route('/'),
            'create' => CreateQrisMerchant::route('/create'),
            'edit' => EditQrisMerchant::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ProjectsRelationManager::class,
        ];
    }
}
