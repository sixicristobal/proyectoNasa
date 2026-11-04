<?php

namespace Tests\Feature;

use App\Models\NasaAgroData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncNasaPowerRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeNasaPower(): void
    {
        Http::fake([
            'power.larc.nasa.gov/*' => Http::response([
                'properties' => ['parameter' => [
                    'T2M' => ['20260915' => 25.0],
                    'PRECTOTCORR' => ['20260915' => 12.0],
                ]],
            ]),
        ]);
    }

    public function test_sync_acepta_rango_start_end_explicito(): void
    {
        $this->fakeNasaPower();

        $this->artisan('nasa:sync', ['--start' => '2026-09-01', '--end' => '2026-09-30'])
            ->assertSuccessful();

        Http::assertSent(fn ($request) => $request['start'] === '20260901' && $request['end'] === '20260930');
        $this->assertSame(1, NasaAgroData::count());
    }

    public function test_sync_con_days_calcula_ventana_hacia_atras(): void
    {
        $this->fakeNasaPower();

        $this->artisan('nasa:sync', ['--days' => 30])->assertSuccessful();

        $sent = null;
        Http::assertSent(function ($request) use (&$sent) {
            $sent = $request;

            return true;
        });

        $end = now()->format('Ymd');
        $start = now()->subDays(29)->format('Ymd');
        $this->assertSame($start, $sent['start']);
        $this->assertSame($end, $sent['end']);
    }

    public function test_sync_rechaza_rango_invertido(): void
    {
        $this->fakeNasaPower();

        $this->artisan('nasa:sync', ['--start' => '2026-10-10', '--end' => '2026-10-01'])
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_sync_rechaza_rango_mayor_al_maximo(): void
    {
        $this->fakeNasaPower();

        $this->artisan('nasa:sync', ['--start' => '2020-01-01', '--end' => '2026-10-10'])
            ->assertFailed();

        Http::assertNothingSent();
    }
}
