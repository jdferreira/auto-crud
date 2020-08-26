<?php

namespace Ferreira\AutoCrud;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class PivotSeeder
{
    /**
     * Seed a pivot table with random many-to-many relationships.
     *
     * @param string $table
     */
    public function seed(string $table)
    {
        /**
         * @var DatabaseInformation
         */
        $db = app(DatabaseInformation::class);

        $table = $db->table($table);

        if (! $table->isPivot()) {
            throw new PivotSeederException('Table ' . $table->name() . ' is not a pivot.');
        }

        $references = $table->allReferences();
        $localPivotColumns = array_keys($references);
        $fk1 = $references[$localPivotColumns[0]];
        $fk2 = $references[$localPivotColumns[1]];

        $ids1 = $this->ids($fk1[0]);
        $ids2 = $this->ids($fk2[0]);

        $pairs = [];

        while (count($pairs) < 50) {
            $id1 = $ids1->random();
            $id2 = $ids2->random();

            if (! in_array([$id1, $id2], $pairs)) {
                $pairs[] = [$id1, $id2];
            }
        }

        foreach ($pairs as &$pair) {
            $pair = [
                $localPivotColumns[0] => $pair[0],
                $localPivotColumns[1] => $pair[1],
            ];
        }

        DB::table($table->name())->insert($pairs);
    }

    private function ids(string $tablename)
    {
        $modelClass = 'App\\' . Str::studly(Str::singular($tablename)); // TODO: What about when the models live elsewhere?

        /**
         * @var Model
         */
        $model = new $modelClass;

        return $model->newQuery()->pluck($model->getKeyName());
    }
}
