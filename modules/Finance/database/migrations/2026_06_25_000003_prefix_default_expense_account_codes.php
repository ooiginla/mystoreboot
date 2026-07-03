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
            ['old' => '5000', 'new' => 'EXP-COGS'],
            ['old' => '5100', 'new' => 'EXP-FREIGHT-DELIVERY'],
            ['old' => '5200', 'new' => 'EXP-TAX'],
            ['old' => '6000', 'new' => 'EXP-GENERAL-OPERATING'],
            ['old' => '6010', 'new' => 'EXP-RENT'],
            ['old' => '6020', 'new' => 'EXP-UTILITIES'],
            ['old' => '6030', 'new' => 'EXP-SALARIES-WAGES'],
            ['old' => '6040', 'new' => 'EXP-MARKETING'],
            ['old' => '6100', 'new' => 'EXP-RENT-LEASE'],
            ['old' => '6110', 'new' => 'EXP-PROPERTY-TAXES'],
            ['old' => '6120', 'new' => 'EXP-INTERNET-PHONE'],
            ['old' => '6130', 'new' => 'EXP-UTILITIES-ELECTRICITY'],
            ['old' => '6140', 'new' => 'EXP-UTILITIES-WATER'],
            ['old' => '6150', 'new' => 'EXP-UTILITIES-FUEL-GAS'],
            ['old' => '6160', 'new' => 'EXP-UTILITIES-WASTE-DISPOSAL'],
            ['old' => '6170', 'new' => 'EXP-UTILITIES-CLEANING'],
            ['old' => '6180', 'new' => 'EXP-WAGES-SALARIES'],
            ['old' => '6190', 'new' => 'EXP-COMPENSATION-TAXES'],
            ['old' => '6200', 'new' => 'EXP-EMPLOYEE-BENEFITS'],
            ['old' => '6210', 'new' => 'EXP-CONTRACTOR-FEES'],
            ['old' => '6220', 'new' => 'EXP-REPAIRS-MAINTENANCE'],
            ['old' => '6230', 'new' => 'EXP-IT-SOFTWARE-SUBSCRIPTION'],
            ['old' => '6240', 'new' => 'EXP-OFFICE-SUPPLIES'],
            ['old' => '6250', 'new' => 'EXP-INSURANCE'],
            ['old' => '6260', 'new' => 'EXP-OFFICE-GENERAL-ADMIN'],
            ['old' => '6270', 'new' => 'EXP-ADVERTISING'],
            ['old' => '6280', 'new' => 'EXP-MARKETING-CAMPAIGNS'],
            ['old' => '6290', 'new' => 'EXP-SALES-COMMISSIONS'],
            ['old' => '6300', 'new' => 'EXP-TRAVEL-ENTERTAINMENT'],
            ['old' => '6310', 'new' => 'EXP-LOAN-INTEREST'],
            ['old' => '6320', 'new' => 'EXP-AMORTIZATION'],
            ['old' => '6330', 'new' => 'EXP-DEPRECIATION'],
            ['old' => '6340', 'new' => 'EXP-INCOME-TAXES'],
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
