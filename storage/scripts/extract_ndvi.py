"""Extrae el valor NDVI de un HDF/TIF MODIS para una coordenada lat/lon.

Uso:
    python3 extract_ndvi.py <archivo> <lat> <lon>

Imprime JSON: {"ndvi": <float|null>, "file": ..., "lat": ..., "lon": ...}
NDVI MOD13Q1: banda 1 * 0.0001 (fill value -3000 => null).
"""

import json
import sys


def main() -> None:
    if len(sys.argv) != 4:
        print(json.dumps({"error": "Uso: extract_ndvi.py <archivo> <lat> <lon>"}))
        sys.exit(1)

    path, lat_s, lon_s = sys.argv[1], sys.argv[2], sys.argv[3]

    try:
        lat = float(lat_s)
        lon = float(lon_s)
    except ValueError:
        print(json.dumps({"error": "lat/lon inválidos"}))
        sys.exit(1)

    try:
        import rasterio
    except ImportError:
        print(json.dumps({"error": "falta librería rasterio (pip install rasterio)"}))
        sys.exit(1)

    try:
        with rasterio.open(path) as src:
            # MOD13Q1 HDF puede traer subdatasets; usar el primero NDVI si aplica.
            candidates = list(src.subdatasets)
            ds = src
            if candidates:
                ndvi_sd = next((s for s in candidates if "NDVI" in s.upper()), candidates[0])
                ds = rasterio.open(ndvi_sd)
                try:
                    row, col = ds.index(lon, lat)
                    window = ((row, row + 1), (col, col + 1))
                    value = ds.read(1, window=window)[0, 0]
                finally:
                    ds.close()
            else:
                row, col = ds.index(lon, lat)
                if row < 0 or col < 0 or row >= ds.height or col >= ds.width:
                    print(json.dumps({"ndvi": None, "file": path, "lat": lat, "lon": lon}))
                    return
                value = ds.read(1)[row, col]

            raw = int(value)
            ndvi = None if raw == -3000 else round(raw * 0.0001, 4)
            print(json.dumps({"ndvi": ndvi, "file": path, "lat": lat, "lon": lon}))
    except Exception as exc:  # noqa: BLE001
        print(json.dumps({"error": str(exc)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
