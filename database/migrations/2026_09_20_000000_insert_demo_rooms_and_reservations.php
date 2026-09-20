<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $day = $now->copy()->addDay()->startOfDay();

        DB::table('rooms')->insert([
            [
                'name' => 'Sala Reunião Norte',
                'capacity' => 8,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'name' => 'Sala Treinamento',
                'capacity' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'name' => 'Sala Diretoria',
                'capacity' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);

        $norteId = DB::table('rooms')->where('name', 'Sala Reunião Norte')->value('id');
        $treinamentoId = DB::table('rooms')->where('name', 'Sala Treinamento')->value('id');

        DB::table('reservations')->insert([
            [
                'room_id' => $norteId,
                'responsible' => 'Gertrudes',
                'title' => 'Reunião da manhã',
                'starts_at' => $day->copy()->setTime(9, 0),
                'ends_at' => $day->copy()->setTime(10, 0),
                'participants' => 4,
                'cancelled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'room_id' => $norteId,
                'responsible' => 'Marcelo',
                'title' => 'Alinhamento seguinte',
                'starts_at' => $day->copy()->setTime(10, 0),
                'ends_at' => $day->copy()->setTime(11, 0),
                'participants' => 6,
                'cancelled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'room_id' => $treinamentoId,
                'responsible' => 'Emerson',
                'title' => 'Treinamento da tarde',
                'starts_at' => $day->copy()->setTime(14, 0),
                'ends_at' => $day->copy()->setTime(16, 0),
                'participants' => 12,
                'cancelled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roomIds = DB::table('rooms')->whereIn('name', [
            'Sala Reunião Norte',
            'Sala Treinamento',
            'Sala Diretoria',
        ])->pluck('id');

        DB::table('reservations')
            ->whereIn('room_id', $roomIds)
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('title', 'Reunião da manhã')
                        ->where('responsible', 'Gertrudes');
                })->orWhere(function ($inner): void {
                    $inner->where('title', 'Alinhamento seguinte')
                        ->where('responsible', 'Marcelo');
                })->orWhere(function ($inner): void {
                    $inner->where('title', 'Treinamento da tarde')
                        ->where('responsible', 'Emerson');
                });
            })
            ->delete();

        DB::table('rooms')->whereIn('name', [
            'Sala Reunião Norte',
            'Sala Treinamento',
            'Sala Diretoria',
        ])->delete();
    }
};
