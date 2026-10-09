<?php

namespace App\Services;

/**
 * Imágenes satelitales NASA vía GIBS (Global Imagery Browse Services).
 * Sin key: los tiles WMTS son públicos. Patrones verificados contra
 * https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/ ...
 */
class NasaImageryService
{
    protected string $wmtsBase = 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best';

    protected string $attribution = 'Imágenes NASA GIBS (Terra/MODIS, Suomi NPP/VIIRS) · ESDIS';

    /**
     * @return array<string, array{label: string, desc: string, gibs: string}>
     */
    public function layers(): array
    {
        return [
            'true_color' => [
                'label' => 'Color verdadero (Terra/MODIS)',
                'desc' => 'Como lo vería el ojo humano. Nubes, humo, polvo y vegetación.',
                'gibs' => 'MODIS_Terra_CorrectedReflectance_TrueColor',
            ],
            'false_color' => [
                'label' => 'Falso color 7-2-1 (inundación/vegetación)',
                'desc' => 'Vegetación en verde brillante, agua en azul oscuro/negro. Ideal para ver encharcamientos.',
                'gibs' => 'MODIS_Terra_CorrectedReflectance_Bands721',
            ],
            'viirs' => [
                'label' => 'Color verdadero (Suomi NPP/VIIRS)',
                'desc' => 'Segunda pasada diaria, útil si Terra viene con nubes.',
                'gibs' => 'VIIRS_SNPP_CorrectedReflectance_TrueColor',
            ],
        ];
    }

    public function defaultLayer(): string
    {
        return 'true_color';
    }

    /** Fecha con imagen casi segura (GIBS NRT tiene lag de 1-3 días). */
    public function defaultDate(): string
    {
        return now()->subDays(4)->format('Y-m-d');
    }

    /**
     * Normaliza la fecha pedida: formato válido, no futura, no anterior a Terra (2000-02-24).
     */
    public function normalizeDate(?string $date): string
    {
        if ($date === null) {
            return $this->defaultDate();
        }

        try {
            $d = new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return $this->defaultDate();
        }

        $today = new \DateTimeImmutable('today');

        if ($d > $today) {
            return $this->defaultDate();
        }

        if ($d < new \DateTimeImmutable('2000-02-24')) {
            return $this->defaultDate();
        }

        return $d->format('Y-m-d');
    }

    public function normalizeLayer(?string $layer): string
    {
        return array_key_exists((string) $layer, $this->layers())
            ? (string) $layer
            : $this->defaultLayer();
    }

    /** Plantilla de tiles WMTS lista para Leaflet (maxZoom 9 según GIBS). */
    public function tileUrlTemplate(string $layerKey, string $date): string
    {
        $gibs = $this->layers()[$this->normalizeLayer($layerKey)]['gibs'];

        return "{$this->wmtsBase}/{$gibs}/default/{$date}/GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpg";
    }

    /** PNG estático vía Worldview Snapshot API (para descargar/ver sin mapa). */
    public function snapshotUrl(string $layerKey, string $date, float $lat, float $lon, float $span = 4.0): string
    {
        $gibs = $this->layers()[$this->normalizeLayer($layerKey)]['gibs'];
        $bbox = implode(',', [$lon - $span, $lat - $span, $lon + $span, $lat + $span]);

        return 'https://wvs.earthdata.nasa.gov/api/v1/snapshot?'.http_build_query([
            'REQUEST' => 'GetSnapshot',
            'TIME' => $date.'T00:00:00Z',
            'BBOX' => $bbox,
            'CRS' => 'EPSG:4326',
            'LAYERS' => $gibs,
            'FORMAT' => 'image/jpeg',
            'WIDTH' => 1200,
            'HEIGHT' => 800,
            'AUTOSCALE' => 'TRUE',
        ]);
    }

    /** Abrir la misma vista en la app oficial NASA Worldview. */
    public function worldviewUrl(string $layerKey, string $date, float $lat, float $lon, float $span = 4.0): string
    {
        $gibs = $this->layers()[$this->normalizeLayer($layerKey)]['gibs'];

        return 'https://worldview.earthdata.nasa.gov/?'.http_build_query([
            'v' => implode(',', [$lon - $span, $lat - $span, $lon + $span, $lat + $span]),
            'l' => $gibs,
            't' => $date,
        ]);
    }

    public function attribution(): string
    {
        return $this->attribution;
    }
}
