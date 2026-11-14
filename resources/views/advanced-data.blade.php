<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('messages.advanced_title') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
<div class="max-w-6xl mx-auto px-6 py-8">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold">{{ __('messages.advanced_nasa_data') }}</h1>
    @include('partials.language-switch')
  </div>
  <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-6">
    <select name="servicio" class="bg-slate-800 rounded px-3 py-2">
      @foreach(['ecostress'=>'ECOSTRESS','srtm'=>'SRTM','modis_lst'=>'MODIS LST','modis_et'=>'MODIS ET','landsat'=>'Landsat'] as $k=>$v)
        <option value="{{ $k }}" @selected($service===$k)>{{ $v }}</option>
      @endforeach
    </select>
    <input name="lat" value="{{ $lat }}" class="bg-slate-800 rounded px-3 py-2" placeholder="{{ __('messages.latitude') }}" aria-label="{{ __('messages.latitude') }}">
    <input name="lon" value="{{ $lon }}" class="bg-slate-800 rounded px-3 py-2" placeholder="{{ __('messages.longitude') }}" aria-label="{{ __('messages.longitude') }}">
    <input type="date" name="inicio" value="{{ $start }}" class="bg-slate-800 rounded px-3 py-2" aria-label="{{ __('messages.start') }}">
    <input type="date" name="fin" value="{{ $end }}" class="bg-slate-800 rounded px-3 py-2" aria-label="{{ __('messages.end') }}">
    <button class="bg-indigo-600 rounded px-4 py-2 md:col-span-5">{{ __('messages.query') }}</button>
  </form>
  <div class="bg-slate-800 rounded p-4 overflow-auto">
    <table class="w-full text-sm">
      <thead><tr class="text-left border-b border-slate-700"><th class="py-2">ID</th><th>{{ __('messages.start') }}</th><th>{{ __('messages.end') }}</th><th>{{ __('messages.links') }}</th></tr></thead>
      <tbody>
      @foreach($results as $g)
        <tr class="border-b border-slate-700/50">
          <td class="py-2">{{ $g['id'] ?? $g['producer_granule_id'] ?? '-' }}</td>
          <td>{{ $g['time_start'] ?? '-' }}</td>
          <td>{{ $g['time_end'] ?? '-' }}</td>
          <td class="truncate max-w-xs">{{ collect($g['links'] ?? [])->pluck('href')->first() }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
    @if(empty($results)) <p class="text-slate-400 mt-4">{{ __('messages.no_results') }}</p> @endif
  </div>
</div>
</body>
</html>
" filePath="/var/www/html/ProyectoNasa/ProyectoNASA/resources/views/advanced-data.blade.php