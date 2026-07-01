<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LocationSeeder extends Seeder
{
    /**
     * Number of rows per insert chunk.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Seed the states, municipalities and parishes catalog for Venezuela.
     *
     * Source data: database/seeders/data/venezuela.json
     * Uses the query builder (DB::table) instead of Eloquent for bulk-insert
     * performance — there are no model events to worry about since Eloquent
     * models are never instantiated here.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/venezuela.json');

        $states = File::json($path);

        $now = now();

        $stateRows = [];
        $municipalityRows = [];
        $parishRows = [];

        // Pre-assign incremental IDs so we can wire up the FKs without
        // round-tripping to the database after each insert.
        $stateId = 1;
        $municipalityId = 1;
        $parishId = 1;

        foreach ($states as $state) {
            $currentStateId = $stateId++;

            $stateRows[] = [
                'id' => $currentStateId,
                'name' => $state['name'],
                'code' => $state['code'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($state['municipalities'] ?? [] as $municipality) {
                $currentMunicipalityId = $municipalityId++;

                $municipalityRows[] = [
                    'id' => $currentMunicipalityId,
                    'state_id' => $currentStateId,
                    'name' => $municipality['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($municipality['parishes'] ?? [] as $parish) {
                    $parishRows[] = [
                        'id' => $parishId++,
                        'municipality_id' => $currentMunicipalityId,
                        'name' => $parish['name'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // ~24 rows — a single insert is fine.
        DB::table('states')->insert($stateRows);

        // ~335 rows — still well under the chunk size, but chunk anyway to
        // keep the pattern consistent and safe if the dataset grows.
        foreach (array_chunk($municipalityRows, self::CHUNK_SIZE) as $chunk) {
            DB::table('municipalities')->insert($chunk);
        }

        // ~1100+ rows — must be chunked to stay within bind-parameter limits.
        foreach (array_chunk($parishRows, self::CHUNK_SIZE) as $chunk) {
            DB::table('parishes')->insert($chunk);
        }
    }
}
