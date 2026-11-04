<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Field;
use App\Models\User;
use App\Services\RotationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class RotationComparisonController extends Controller
{
    public function index(Request $request): View
    {
        $fields = Field::query()->with('farm')->orderBy('name')->get();
        $fieldId = $request->integer('field_id');
        $field = $fieldId > 0
            ? $fields->firstWhere('id', $fieldId)
            : $fields->first();

        $recommendations = $field !== null
            ? $field->recommendations()
                ->with('rotation.steps.crop')
                ->orderBy('rank')
                ->get()
            : collect();

        return view('rotations.index', [
            'fields' => $fields,
            'field' => $field,
            'recommendations' => $recommendations,
            'hasCrops' => Crop::query()->exists(),
        ]);
    }

    public function store(Request $request, RotationEngine $engine): RedirectResponse
    {
        $validated = $request->validate([
            'field_id' => ['required', 'integer', 'exists:fields,id'],
        ]);

        $field = Field::query()->findOrFail($validated['field_id']);

        try {
            $recommendations = $engine->generate($field);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('rotations.index', ['field_id' => $field->id])
                ->withErrors(['rotation' => $e->getMessage()]);
        }

        return redirect()
            ->route('rotations.index', ['field_id' => $field->id])
            ->with('status', 'Se generaron '.$recommendations->count().' estrategias para '.$field->name.'.');
    }

    public function storeField(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'area_ha' => ['nullable', 'numeric', 'min:0.01', 'max:999999'],
            'soil_type' => ['nullable', 'string', 'max:50'],
            'soil_ph' => ['nullable', 'numeric', 'between:0,14'],
            'soil_organic_matter_pct' => ['nullable', 'numeric', 'between:0,100'],
            'irrigation_type' => ['required', 'in:secano,riego,mixto'],
        ]);

        $field = DB::transaction(function () use ($validated): Field {
            $user = User::query()->first();

            if ($user === null) {
                $user = User::create([
                    'name' => 'Productor Demo',
                    'email' => 'demo+'.Str::lower(Str::random(8)).'@fieldshift.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
            }

            $farm = Farm::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => 'Finca principal'],
                ['region' => 'Paraguay', 'country' => 'Paraguay']
            );

            return Field::create([
                'farm_id' => $farm->id,
                'name' => $validated['name'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'area_ha' => $validated['area_ha'] ?? null,
                'soil_type' => $validated['soil_type'] ?? null,
                'soil_ph' => $validated['soil_ph'] ?? null,
                'soil_organic_matter_pct' => $validated['soil_organic_matter_pct'] ?? null,
                'irrigation_type' => $validated['irrigation_type'],
            ]);
        });

        return redirect()
            ->route('rotations.index', ['field_id' => $field->id])
            ->with('status', 'Parcela "'.$field->name.'" creada. Ahora podés generar sus rotaciones.');
    }
}
