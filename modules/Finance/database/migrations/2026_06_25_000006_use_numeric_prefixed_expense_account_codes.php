<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @return list<array{old: string, new: string}>
     */
    private function codes(): array
    {
        return [
            ['old' => 'EXP-COGS', 'new' => 'EXP-5000'],
            ['old' => 'EXP-FREIGHT-DELIVERY', 'new' => 'EXP-5100'],
            ['old' => 'EXP-TAX', 'new' => 'EXP-5200'],
            ['old' => 'EXP-GENERAL-OPERATING', 'new' => 'EXP-6000'],
            ['old' => 'EXP-RENT', 'new' => 'EXP-6010'],
            ['old' => 'EXP-UTILITIES', 'new' => 'EXP-6020'],
            ['old' => 'EXP-SALARIES-WAGES', 'new' => 'EXP-6030'],
            ['old' => 'EXP-MARKETING', 'new' => 'EXP-6040'],
            ['old' => 'EXP-RENT-LEASE', 'new' => 'EXP-6100'],
            ['old' => 'EXP-PROPERTY-TAXES', 'new' => 'EXP-6110'],
            ['old' => 'EXP-INTERNET-PHONE', 'new' => 'EXP-6120'],
            ['old' => 'EXP-UTILITIES-ELECTRICITY', 'new' => 'EXP-6130'],
            ['old' => 'EXP-UTILITIES-WATER', 'new' => 'EXP-6140'],
            ['old' => 'EXP-UTILITIES-FUEL-GAS', 'new' => 'EXP-6150'],
            ['old' => 'EXP-UTILITIES-WASTE-DISPOSAL', 'new' => 'EXP-6160'],
            ['old' => 'EXP-UTILITIES-CLEANING', 'new' => 'EXP-6170'],
            ['old' => 'EXP-WAGES-SALARIES', 'new' => 'EXP-6180'],
            ['old' => 'EXP-COMPENSATION-TAXES', 'new' => 'EXP-6190'],
            ['old' => 'EXP-EMPLOYEE-BENEFITS', 'new' => 'EXP-6200'],
            ['old' => 'EXP-CONTRACTOR-FEES', 'new' => 'EXP-6210'],
            ['old' => 'EXP-REPAIRS-MAINTENANCE', 'new' => 'EXP-6220'],
            ['old' => 'EXP-IT-SOFTWARE-SUBSCRIPTION', 'new' => 'EXP-6230'],
            ['old' => 'EXP-OFFICE-SUPPLIES', 'new' => 'EXP-6240'],
            ['old' => 'EXP-INSURANCE', 'new' => 'EXP-6250'],
            ['old' => 'EXP-OFFICE-GENERAL-ADMIN', 'new' => 'EXP-6260'],
            ['old' => 'EXP-ADVERTISING', 'new' => 'EXP-6270'],
            ['old' => 'EXP-MARKETING-CAMPAIGNS', 'new' => 'EXP-6280'],
            ['old' => 'EXP-SALES-COMMISSIONS', 'new' => 'EXP-6290'],
            ['old' => 'EXP-TRAVEL-ENTERTAINMENT', 'new' => 'EXP-6300'],
            ['old' => 'EXP-LOAN-INTEREST', 'new' => 'EXP-6310'],
            ['old' => 'EXP-AMORTIZATION', 'new' => 'EXP-6320'],
            ['old' => 'EXP-DEPRECIATION', 'new' => 'EXP-6330'],
            ['old' => 'EXP-INCOME-TAXES', 'new' => 'EXP-6340'],
        ];
    }

    public function up(): void
    {
        foreach ($this->codes() as $code) {
            DB::table('finance_accounts')
                ->where('type', 'expense')
                ->where('code', $code['old'])
                ->update(['code' => $code['new']]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->codes()) as $code) {
            DB::table('finance_accounts')
                ->where('type', 'expense')
                ->where('code', $code['new'])
                ->update(['code' => $code['old']]);
        }
    }
};
