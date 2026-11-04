<form method="POST" action="{{ route('rotations.fields.store') }}" class="row g-2">
    @csrf
    <div class="col-12 col-md-6">
        <label for="name" class="form-label small text-muted mb-1">Nombre de la parcela</label>
        <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required maxlength="120">
    </div>
    <div class="col-6 col-md-3">
        <label for="latitude" class="form-label small text-muted mb-1">Latitud</label>
        <input type="number" step="any" name="latitude" id="latitude" class="form-control" value="{{ old('latitude', '-25.2637') }}" required>
    </div>
    <div class="col-6 col-md-3">
        <label for="longitude" class="form-label small text-muted mb-1">Longitud</label>
        <input type="number" step="any" name="longitude" id="longitude" class="form-control" value="{{ old('longitude', '-57.5759') }}" required>
    </div>
    <div class="col-6 col-md-3">
        <label for="area_ha" class="form-label small text-muted mb-1">Área (ha)</label>
        <input type="number" step="0.01" min="0.01" name="area_ha" id="area_ha" class="form-control" value="{{ old('area_ha') }}">
    </div>
    <div class="col-6 col-md-3">
        <label for="soil_type" class="form-label small text-muted mb-1">Tipo de suelo</label>
        <input type="text" name="soil_type" id="soil_type" class="form-control" value="{{ old('soil_type') }}" maxlength="50" placeholder="rojo, arenoso, arcilloso">
    </div>
    <div class="col-6 col-md-2">
        <label for="soil_ph" class="form-label small text-muted mb-1">pH</label>
        <input type="number" step="0.1" min="0" max="14" name="soil_ph" id="soil_ph" class="form-control" value="{{ old('soil_ph') }}">
    </div>
    <div class="col-6 col-md-2">
        <label for="soil_organic_matter_pct" class="form-label small text-muted mb-1">M. orgánica (%)</label>
        <input type="number" step="0.01" min="0" max="100" name="soil_organic_matter_pct" id="soil_organic_matter_pct" class="form-control" value="{{ old('soil_organic_matter_pct') }}">
    </div>
    <div class="col-12 col-md-2">
        <label for="irrigation_type" class="form-label small text-muted mb-1">Riego</label>
        <select name="irrigation_type" id="irrigation_type" class="form-select" required>
            <option value="secano" @selected(old('irrigation_type') === 'secano')>Secano</option>
            <option value="riego" @selected(old('irrigation_type') === 'riego')>Riego</option>
            <option value="mixto" @selected(old('irrigation_type') === 'mixto')>Mixto</option>
        </select>
    </div>
    <div class="col-12 col-md-3 d-flex align-items-end">
        <button class="btn btn-outline-success w-100">Crear parcela</button>
    </div>
</form>
