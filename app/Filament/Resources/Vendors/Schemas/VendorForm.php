<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
       return $schema->components([
            Section::make('Identitas Vendor')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Vendor')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true),

                    TextInput::make('name')
                        ->label('Nama Vendor')
                        ->required()
                        ->maxLength(150),

                    TextInput::make('npwp')->label('NPWP')->maxLength(30),

                    Toggle::make('is_active')->label('Vendor Aktif')->default(true),
                ]),

            Section::make('Kontak')
                ->columns(2)
                ->schema([
                    TextInput::make('contact_person')->label('Narahubung')->maxLength(120),
                    TextInput::make('phone')->label('Telepon')->tel()->maxLength(20),
                    TextInput::make('email')->label('Email')->email()->maxLength(120),

                    Textarea::make('address')->label('Alamat')->rows(2)->columnSpanFull(),
                    Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}