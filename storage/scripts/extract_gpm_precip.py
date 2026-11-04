#!/usr/bin/env python3
"""Extrae precipitación GPM IMERG en un punto.

Uso:
    python3 extract_gpm_precip.py <archivo.HDF5> <lat> <lon>

Salida (stdout): JSON {"precip_mm": <float|null>, "error": <str|null>, ...}

Requiere h5py y numpy. Si no están instalados, devuelve un JSON con error
para que Laravel registre la laguna de información sin romper el Job.
"""
import json
import sys


def emit(payload):
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(0)


def grid_indices(lat, lon):
    """GPM IMERG 0.1°: 3600 columnas (lon) x 1800 filas (lat)."""
    lat_idx = int((90.0 - lat) / 0.1)
    lon_idx = int((lon + 180.0) / 0.1)
    return lat_idx, lon_idx


def find_precip_dataset(handle):
    candidates = [
        "/Grid/precipitation",
        "/Grid/precipitationCal",
        "/precipitation",
        "/Grid/precipitation_unweighted",
    ]
    for name in candidates:
        if name in handle:
            return handle[name]

    found = []

    def visit(name, obj):
        if obj.__class__.__name__ == "Dataset" and "precip" in name.lower():
            found.append(name)

    handle.visititems(visit)

    for name in found:
        dataset = handle[name]
        if dataset.ndim >= 2:
            return dataset

    return None


def value_at(dataset, lat, lon):
    lat_idx, lon_idx = grid_indices(lat, lon)
    shape = dataset.shape

    if dataset.ndim == 2:
        rows, cols = shape
        if rows == 1800 and cols == 3600:
            lat_idx = min(max(lat_idx, 0), rows - 1)
            lon_idx = min(max(lon_idx, 0), cols - 1)
            return dataset[lat_idx, lon_idx]
        if rows == 3600 and cols == 1800:
            lon_idx = min(max(lon_idx, 0), rows - 1)
            lat_idx = min(max(lat_idx, 0), cols - 1)
            return dataset[lon_idx, lat_idx]
        lat_idx = min(max(lat_idx, 0), rows - 1)
        lon_idx = min(max(lon_idx, 0), cols - 1)
        return dataset[lat_idx, lon_idx]

    if dataset.ndim == 3:
        _, second, third = shape
        if second == 3600 and third == 1800:
            lon_idx = min(max(lon_idx, 0), second - 1)
            lat_idx = min(max(lat_idx, 0), third - 1)
            return dataset[0, lon_idx, lat_idx]
        lat_idx = min(max(lat_idx, 0), second - 1)
        lon_idx = min(max(lon_idx, 0), third - 1)
        return dataset[0, lat_idx, lon_idx]

    raise ValueError("El dataset de precipitación tiene dimensiones no soportadas: %s" % (shape,))


def main():
    if len(sys.argv) < 4:
        emit({"precip_mm": None, "error": "Uso: extract_gpm_precip.py <archivo> <lat> <lon>"})

    path = sys.argv[1]
    lat = float(sys.argv[2])
    lon = float(sys.argv[3])

    try:
        import h5py
        import numpy as np
    except Exception as exc:  # noqa: BLE001
        emit({"precip_mm": None, "error": "Faltan h5py/numpy: %s" % exc})

    try:
        with h5py.File(path, "r") as handle:
            dataset = find_precip_dataset(handle)
            if dataset is None:
                emit({"precip_mm": None, "error": "No se encontró un dataset de precipitación."})

            raw = value_at(dataset, lat, lon)

            if np.ma.isMaskedArray(raw) and np.ma.is_masked(raw):
                emit({"precip_mm": None, "error": None, "masked": True})

            value = float(raw)

            if value < 0 or np.isnan(value):
                emit({"precip_mm": None, "error": None, "invalid_value": value})

            emit({
                "precip_mm": value,
                "error": None,
                "dataset": dataset.name,
                "shape": list(dataset.shape),
            })
    except Exception as exc:  # noqa: BLE001
        emit({"precip_mm": None, "error": "%s: %s" % (type(exc).__name__, exc)})


if __name__ == "__main__":
    main()
