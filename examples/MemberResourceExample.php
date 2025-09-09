<?php

namespace App\Filament\Admin\Resources;

use App\Models\Member;
use Filament\Resources\Resource;
use Filament\Tables;
use Ihabrouk\Messenger\Actions\SendMessageAction;
use Ihabrouk\Messenger\Actions\BulkMessageAction;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('email'),
                // ... other columns
            ])
            ->actions([
                // Add the SendMessageAction to individual members
                SendMessageAction::make()
                    ->phoneField('phone')  // Column containing phone number
                    ->nameField('name'),   // Column containing member name
                
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Add BulkMessageAction for multiple members
                BulkMessageAction::make()
                    ->phoneField('phone')
                    ->nameField('name'),
                    
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    
    // ... rest of your resource
}
