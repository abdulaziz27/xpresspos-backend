<?php

namespace App\Filament\Owner\Resources\Expenses\Pages;

use App\Filament\Owner\Resources\Expenses\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;
}
