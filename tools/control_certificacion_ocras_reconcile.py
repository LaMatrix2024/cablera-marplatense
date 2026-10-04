"""Conciliación OCRAS entre laboratorio y Plantel, sin borrados.

El modo de aplicación es conservador: las filas nuevas se insertan; las filas
existentes sólo se actualizan si no presentan una modificación posterior al
corte. Las filas exclusivas del destino nunca se eliminan.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from datetime import datetime, timezone
from pathlib import Path

import pymysql

TABLES = [
    "raw_certificacion_ocras", "control_certificacion_tareas",
    "control_certificacion_asignaciones", "control_certificacion_eventos",
    "control_certificacion_fuente_estado", "control_certificacion_historial_operativo",
    "control_certificacion_monitores", "control_certificacion_novedades",
    "control_certificacion_sobrestantes", "control_certificacion_comunicaciones",
    "control_certificacion_snapshots", "responsables_nexo",
    "nexo_usuarios_vinculaciones_operativas",
]
TIME_COLUMNS = ("updated_at", "updated_en", "fecha_actualizacion", "modified_at", "created_at")

def env_file() -> dict[str, str]:
    path = Path(r"C:\plantel\DATOS_LOCALES\plantel.env")
    out: dict[str, str] = {}
    if path.exists():
        for line in path.read_text(encoding="utf-8-sig").splitlines():
            if "=" in line and not line.lstrip().startswith("#"):
                key, value = line.split("=", 1); out[key.strip()] = value.strip().strip("\"'")
    out.update({key: value for key, value in os.environ.items() if key not in out})
    return out

def connect(env: dict[str, str], prefix: str):
    return pymysql.connect(host=env[f"{prefix}HOST"], port=int(env.get(f"{prefix}PORT", "3306")), user=env[f"{prefix}USER"], password=env[f"{prefix}PASSWORD"], database=env[f"{prefix}DATABASE"], charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor, autocommit=False)

def ident(value: str) -> str: return "`" + value.replace("`", "``") + "`"
def fingerprint(row: dict) -> str: return hashlib.sha256(json.dumps(row, ensure_ascii=False, sort_keys=True, default=str, separators=(",", ":")).encode()).hexdigest()

def parse_utc(value) -> datetime | None:
    if value in (None, ""): return None
    text = str(value).replace("Z", "+00:00")
    try: parsed = datetime.fromisoformat(text)
    except ValueError:
        try: parsed = datetime.strptime(str(value)[:19], "%Y-%m-%d %H:%M:%S")
        except ValueError: return None
    return parsed.replace(tzinfo=timezone.utc) if parsed.tzinfo is None else parsed.astimezone(timezone.utc)

def table_info(conn, table: str):
    with conn.cursor() as cur:
        cur.execute("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=%s ORDER BY ordinal_position", (table,)); columns = [row["column_name"] for row in cur.fetchall()]
        cur.execute("SELECT column_name FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=%s AND constraint_name='PRIMARY' ORDER BY ordinal_position", (table,)); primary_key = [row["column_name"] for row in cur.fetchall()]
    return columns, primary_key

def main() -> None:
    parser = argparse.ArgumentParser(); parser.add_argument("--apply", action="store_true", help="aplica sólo cambios seguros"); parser.add_argument("--manifest", required=True, help="ruta fuera del repositorio"); parser.add_argument("--cutover-utc", help="corte ISO-8601; no sobrescribe cambios posteriores"); args = parser.parse_args()
    manifest_path = Path(args.manifest).resolve(); repository = Path(__file__).resolve().parents[1]
    if str(manifest_path).lower().startswith(str(repository).lower()): raise SystemExit("El manifiesto debe guardarse fuera del repositorio")
    cutover = parse_utc(args.cutover_utc) if args.cutover_utc else None
    if args.apply and cutover is None: raise SystemExit("--apply requiere --cutover-utc para evitar sobrescribir cambios posteriores")
    env = env_file(); source = connect(env, "DB_HOSTINGER_LAB_"); destination = connect(env, "DB_HOSTINGER_PLANTEL_"); manifest = {"created_at_utc": datetime.now(timezone.utc).isoformat(), "cutover_utc": args.cutover_utc, "tables": {}}
    try:
        for table in TABLES:
            try: columns, primary_key = table_info(source, table); destination_columns, destination_pk = table_info(destination, table)
            except Exception: print(f"{table}: destino o fuente ausente"); continue
            if not destination_columns: print(f"{table}: destino ausente"); continue
            if columns != destination_columns or primary_key != destination_pk: raise RuntimeError(f"Esquema incompatible en {table}")
            with source.cursor() as cur: cur.execute(f"SELECT * FROM {ident(table)}"); source_rows = cur.fetchall()
            with destination.cursor() as cur: cur.execute(f"SELECT * FROM {ident(table)}"); destination_rows = cur.fetchall()
            if not primary_key: raise RuntimeError(f"{table} no tiene clave primaria; no se actualiza automáticamente")
            source_map = {tuple(row[key] for key in primary_key): row for row in source_rows}; destination_map = {tuple(row[key] for key in primary_key): row for row in destination_rows}
            inserts = [key for key in source_map if key not in destination_map]; differences = [key for key in source_map if key in destination_map and fingerprint(source_map[key]) != fingerprint(destination_map[key])]; conflicts: list[tuple] = []; updates: list[tuple] = []
            for key in differences:
                destination_row = destination_map[key]; destination_time = next((parse_utc(destination_row.get(column)) for column in TIME_COLUMNS if column in destination_row), None)
                if args.apply and (destination_time is None or (cutover and destination_time > cutover)): conflicts.append(key)
                else: updates.append(key)
            manifest["tables"][table] = {"source_rows": len(source_rows), "destination_rows": len(destination_rows), "new": len(inserts), "modified": len(differences), "updates_allowed": len(updates), "conflicts": [list(key) for key in conflicts], "destination_only": len(set(destination_map) - set(source_map)), "primary_key": primary_key}
            print(f"{table}: nuevos={len(inserts)} diferencias={len(differences)} actualizables={len(updates)} conflictos={len(conflicts)} sólo_destino={len(set(destination_map)-set(source_map))}")
            if not args.apply: continue
            names = ",".join(ident(column) for column in columns); placeholders = ",".join(["%s"] * len(columns)); update_sql = ",".join(f"{ident(column)}=VALUES({ident(column)})" for column in columns if column not in primary_key); sql = f"INSERT INTO {ident(table)} ({names}) VALUES ({placeholders}) ON DUPLICATE KEY UPDATE {update_sql}"
            with destination.cursor() as cur: cur.executemany(sql, [tuple(source_map[key][column] for column in columns) for key in inserts + updates])
        if args.apply: destination.commit()
        manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2, default=str) + "\n", encoding="utf-8")
    finally: source.close(); destination.close()

if __name__ == "__main__": main()
