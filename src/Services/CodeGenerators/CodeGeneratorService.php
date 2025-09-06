<?php

namespace QuickerFaster\CodeGen\Services\CodeGenerators;

use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\error;

class CodeGeneratorService
{
    public static function generateCode($model, $modelName, $field)
    {
        try {
            $prefix = strtoupper(substr($modelName, 0, 3)); // QUO, ORD, etc.
            //$date = now()->format('Ymd'); // 20250216
            $date = now()->format('Y'); // 2025
            //$latest = DB::table('orders')->where('code', 'LIKE', "$prefix-$date-%")->max('code');
            $latest = $model::where($field, 'LIKE', "$prefix-$date-%")->max($field);

            $nextNumber = $latest ? intval(substr($latest, -3)) + 1 : 1;
            return sprintf("%s-%s-%03d", $prefix, $date, $nextNumber);
        } catch (\Exception $e) {
            \Log::error("CodeGeneratorService error: ". $e);
        }
    }
}
