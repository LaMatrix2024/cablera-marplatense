"""Reconciliación reversible Plantel -> Laboratorio para OCRAS.

La ejecución es de solo lectura por defecto. ``--apply`` exige un corte UTC y
omite conflictos cuando el destino tiene cambios posteriores al corte.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from datetime import datetime, timezone
from typing import Any


TABLES = {
    "control_certificacion_tareas": ("sigest", "etapa"),
    "control_certificacion_asignaciones": ("id",),
    "control_certificacion_novedades": ("id",),
    "control_certificacion_eventos": ("id",),
    "control_certificacion_historial_operativo": ("id",),
    "responsables_nexo": ("id",),
}


def fingerprint(row: dict[str, Any]) -> str:
    payload = json.dumps(row, sort_keys=True, ensure_ascii=False, default=str, separators=(",", ":"))
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def row_time(row: dict[str, Any]) -> datetime | None:
    for key in ("updated_at", "created_at", "fecha_hora", "created"):  # tablas heredadas
        value = row.get(key)
        if not value:
            continue
        try:
            text = str(value).replace("Z", "+00:00")
            parsed = datetime.fromisoformat(text)
            return parsed.replace(tzinfo=parsed.tzinfo or timezone.utc)
        except ValueError:
            continue
    return None


def plan_rows(source_rows: list[dict[str, Any]], destination_rows: list[dict[str, Any]], primary_key: tuple[str, ...], cutover: datetime) -> dict[str, Any]:
    """Calcula inserciones/actualizaciones/conflictos sin tocar una base."""
    source = {tuple(str(row.get(key, "")) for key in primary_key): row for row in source_rows}
    destination = {tuple(str(row.get(key, "")) for key in primary_key): row for row in destination_rows}
    inserts: list[dict[str, Any]] = []
    updates: list[dict[str, Any]] = []
    conflicts: list[dict[str, Any]] = []
    for key, row in source.items():
        current = destination.get(key)
        if current is None:
            inserts.append(row)
            continue
        if fingerprint(row) == fingerprint(current):
            continue
        current_time = row_time(current)
        if current_time is None or current_time > cutover:
            conflicts.append({"key": key, "reason": "destino_posterior_al_corte" if current_time else "destino_sin_fecha"})
        else:
            updates.append(row)
    return {"insert": inserts, "update": updates, "conflicts": conflicts, "preserved_destination_only": [key for key in destination if key not in source]}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Reconcilia OCRAS desde Plantel hacia Laboratorio sin sobrescribir cambios posteriores.")
    parser.add_argument("--cutover-utc", help="Corte ISO-8601 UTC; obligatorio con --apply")
    parser.add_argument("--apply", action="store_true", help="Escribe solo filas no conflictivas")
    parser.add_argument("--dry-run", action="store_true", help="Calcula el plan y no escribe (predeterminado)")
    parser.add_argument("--manifest", required=True, help="Manifest JSON con source_rows y destination_rows sintéticos o exportados")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    if args.apply and not args.cutover_utc:
        raise SystemExit("--apply requiere --cutover-utc")
    cutover = datetime.fromisoformat(args.cutover_utc.replace("Z", "+00:00")) if args.cutover_utc else datetime.now(timezone.utc)
    payload = json.loads(open(args.manifest, encoding="utf-8").read())
    result = {table: plan_rows(payload.get(table, {}).get("source_rows", []), payload.get(table, {}).get("destination_rows", []), keys, cutover) for table, keys in TABLES.items()}
    print(json.dumps({"mode": "apply" if args.apply else "dry_run", "cutover_utc": cutover.isoformat(), "tables": {key: {k: len(v) for k, v in value.items() if isinstance(v, list)} for key, value in result.items()}}, ensure_ascii=False, indent=2))
    if args.apply:
        raise SystemExit("La escritura productiva requiere el adaptador DB explícito y una ventana de corte; el plan no ejecuta SQL implícito.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
