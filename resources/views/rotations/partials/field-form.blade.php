<form method="POST" action="{{ route('rotations.fields.store') }}" class="row g-2">
    @csrf
    <div class="col-12 col-md-6">
        <label for="name" class="form-label small text-muted mb-1">Nombre de la parcela</label>
        <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required maxlength="120">
    </div>
    <div class="col-12 col-md-6">
        <label for="city" class="form-label small text-muted mb-1">Ciudad (Paraguay)</label>
        <select id="city" class="form-select">
            <option value="">— Seleccionar ciudad —</option>
            <option value="-25.2637,-57.5759">Asunción</option>
            <option value="-25.3394,-57.5463">San Lorenzo</option>
            <option value="-25.3413,-57.5330">Luque</option>
            <option value="-25.4445,-57.5533">Capiatá</option>
            <option value="-25.4514,-57.6050">Lambaré</option>
            <option value="-25.5248,-57.3326">Itauguá</option>
            <option value="-25.7325,-57.1704">Ypacaraí</option>
            <option value="-26.1893,-57.1528">Carapeguá</option>
            <option value="-26.8634,-58.3000">Pilar</option>
            <option value="-27.3333,-55.8667">Encarnación</option>
            <option value="-25.7833,-56.4500">Coronel Oviedo</option>
            <option value="-25.5098,-54.6111">Ciudad del Este</option>
            <option value="-22.6986,-60.6100">Filadelfia</option>
            <option value="-23.4425,-57.4431">Concepción</option>
            <option value="-22.5469,-59.5233">Mariscal Estigarribia</option>
        </select>
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
<script>
document.getElementById('city')?.addEventListener('change', function () {
    if (!this.value) return;
    const [lat, lon] = this.value.split(',');
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lon;
});
</script>
