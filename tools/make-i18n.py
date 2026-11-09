#!/usr/bin/env python3
"""Extract strings and build pot/po/mo for wp-compact."""

from __future__ import annotations

import datetime
import pathlib
import re
import subprocess
from collections import OrderedDict

ROOT = pathlib.Path(__file__).resolve().parents[1]
LANG = ROOT / "languages"
PAT = re.compile(
    r"(?:__|esc_html__|_e|esc_attr__)\(\s*'((?:\\'|[^'])*)'\s*,\s*'wp-compact'\s*\)"
)

T = {
    "wp-compact": {
        "fr": "wp-compact",
        "es": "wp-compact",
        "de": "wp-compact",
    },
    "Compress (wp-compact)": {
        "fr": "Compresser (wp-compact)",
        "es": "Comprimir (wp-compact)",
        "de": "Komprimieren (wp-compact)",
    },
    "Advanced mode can change format or dimensions and break themes / CSS / hardcoded URLs.": {
        "fr": "Le mode avancé peut changer le format ou les dimensions et casser des thèmes / CSS / URLs hardcodées.",
        "es": "El modo avanzado puede cambiar el formato o las dimensiones y romper temas / CSS / URLs fijas.",
        "de": "Der erweiterte Modus kann Format oder Abmessungen ändern und Themes / CSS / fest codierte URLs beschädigen.",
    },
    "Compressing…": {"fr": "Compression…", "es": "Comprimiendo…", "de": "Komprimieren…"},
    "Done": {"fr": "Terminé", "es": "Listo", "de": "Fertig"},
    "Skipped": {"fr": "Ignoré", "es": "Omitido", "de": "Übersprungen"},
    "Error": {"fr": "Erreur", "es": "Error", "de": "Fehler"},
    "Stopped.": {"fr": "Arrêté.", "es": "Detenido.", "de": "Gestoppt."},
    "Migrate the entire Media Library to WebP and rewrite links in the database? Previous files will be kept until cleanup.": {
        "fr": "Migrer toute la médiathèque vers WebP et réécrire les liens en base ? Les anciens fichiers seront conservés jusqu’au nettoyage.",
        "es": "¿Migrar toda la biblioteca de medios a WebP y reescribir los enlaces en la base de datos? Los archivos anteriores se conservarán hasta la limpieza.",
        "de": "Gesamte Mediathek nach WebP migrieren und Links in der Datenbank umschreiben? Vorherige Dateien bleiben bis zur Bereinigung erhalten.",
    },
    "Permanently delete the selected legacy files?": {
        "fr": "Supprimer définitivement les fichiers legacy sélectionnés ?",
        "es": "¿Eliminar definitivamente los archivos legacy seleccionados?",
        "de": "Ausgewählte Legacy-Dateien endgültig löschen?",
    },
    "Purge ALL tagged legacy files? This cannot be undone.": {
        "fr": "Purger TOUS les fichiers legacy tagués ? Action irréversible.",
        "es": "¿Purgar TODOS los archivos legacy etiquetados? Acción irreversible.",
        "de": "ALLE markierten Legacy-Dateien löschen? Dies kann nicht rückgängig gemacht werden.",
    },
    "Settings saved.": {
        "fr": "Réglages enregistrés.",
        "es": "Ajustes guardados.",
        "de": "Einstellungen gespeichert.",
    },
    "Save failed": {
        "fr": "Échec de l’enregistrement",
        "es": "Error al guardar",
        "de": "Speichern fehlgeschlagen",
    },
    "Stats": {"fr": "Stats", "es": "Estadísticas", "de": "Statistik"},
    "images": {"fr": "images", "es": "imágenes", "de": "Bilder"},
    "already compressed": {
        "fr": "déjà compressées",
        "es": "ya comprimidas",
        "de": "bereits komprimiert",
    },
    "same settings": {
        "fr": "mêmes réglages",
        "es": "mismos ajustes",
        "de": "gleiche Einstellungen",
    },
    "already WebP": {"fr": "déjà WebP", "es": "ya WebP", "de": "bereits WebP"},
    "no savings": {"fr": "pas de gain", "es": "sin ahorro", "de": "keine Einsparung"},
    "Loading Media Library…": {
        "fr": "Chargement de la médiathèque…",
        "es": "Cargando la biblioteca de medios…",
        "de": "Mediathek wird geladen…",
    },
    "WebP migration: loading Media Library…": {
        "fr": "Migration WebP : chargement médiathèque…",
        "es": "Migración WebP: cargando biblioteca de medios…",
        "de": "WebP-Migration: Mediathek wird geladen…",
    },
    "image(s) to process.": {
        "fr": "image(s) à traiter.",
        "es": "imagen(es) a procesar.",
        "de": "Bild(er) zu verarbeiten.",
    },
    "Failed": {"fr": "Échec", "es": "Error", "de": "Fehlgeschlagen"},
    "skipped": {"fr": "ignoré", "es": "omitido", "de": "übersprungen"},
    "DB replacements": {
        "fr": "remplacements BDD",
        "es": "reemplazos en BD",
        "de": "DB-Ersetzungen",
    },
    "legacy tagged": {
        "fr": "legacy tagué(s)",
        "es": "legacy etiquetado(s)",
        "de": "Legacy markiert",
    },
    "to clean": {"fr": "à nettoyer", "es": "por limpiar", "de": "zu bereinigen"},
    "No tagged legacy files.": {
        "fr": "Aucun fichier legacy tagué.",
        "es": "No hay archivos legacy etiquetados.",
        "de": "Keine markierten Legacy-Dateien.",
    },
    "Nothing selected to purge.": {
        "fr": "Aucune sélection à purger.",
        "es": "Nada seleccionado para purgar.",
        "de": "Nichts zum Löschen ausgewählt.",
    },
    "Full purge is limited to administrators.": {
        "fr": "Purge globale réservée aux administrateurs.",
        "es": "La purga completa está limitada a administradores.",
        "de": "Vollständige Bereinigung ist Administratoren vorbehalten.",
    },
    "Purge": {"fr": "Purge", "es": "Purga", "de": "Bereinigung"},
    "Full purge": {"fr": "Purge totale", "es": "Purga total", "de": "Vollständige Bereinigung"},
    "media items": {"fr": "médias", "es": "elementos multimedia", "de": "Medienelemente"},
    "files": {"fr": "fichiers", "es": "archivos", "de": "Dateien"},
    "freed": {"fr": "libérés", "es": "liberados", "de": "freigegeben"},
    "Worker": {"fr": "Worker", "es": "Worker", "de": "Worker"},
    "threshold": {"fr": "seuil", "es": "umbral", "de": "Schwelle"},
    "wp-compact: %s not replaced (no savings, %s).": {
        "fr": "wp-compact: %s non remplacé (pas de gain, %s).",
        "es": "wp-compact: %s no reemplazado (sin ahorro, %s).",
        "de": "wp-compact: %s nicht ersetzt (keine Einsparung, %s).",
    },
    "wp-compact: %1$s skipped (savings %2$s%% < %3$s%%).": {
        "fr": "wp-compact: %1$s ignoré (gain %2$s%% < %3$s%%).",
        "es": "wp-compact: %1$s omitido (ahorro %2$s%% < %3$s%%).",
        "de": "wp-compact: %1$s übersprungen (Einsparung %2$s%% < %3$s%%).",
    },
    "wp-compact: %1$s %2$s%3$s → %4$s (−%5$s%%).": {
        "fr": "wp-compact: %1$s %2$s%3$s → %4$s (−%5$s%%).",
        "es": "wp-compact: %1$s %2$s%3$s → %4$s (−%5$s%%).",
        "de": "wp-compact: %1$s %2$s%3$s → %4$s (−%5$s%%).",
    },
    "wp-compact: cannot read %s — uploading original without compression.": {
        "fr": "wp-compact: lecture impossible pour %s — upload original sans compression.",
        "es": "wp-compact: no se puede leer %s — subiendo el original sin compresión.",
        "de": "wp-compact: %s kann nicht gelesen werden — Original ohne Kompression wird hochgeladen.",
    },
    "file": {"fr": "fichier", "es": "archivo", "de": "Datei"},
    "wp-compact: compressing %d image(s)…": {
        "fr": "wp-compact: compression de %d image(s)…",
        "es": "wp-compact: comprimiendo %d imagen(es)…",
        "de": "wp-compact: %d Bild(er) werden komprimiert…",
    },
    "wp-compact: failed on %1$s (%2$s) — sending original.": {
        "fr": "wp-compact: échec sur %1$s (%2$s) — envoi de l’original.",
        "es": "wp-compact: error en %1$s (%2$s) — enviando el original.",
        "de": "wp-compact: Fehler bei %1$s (%2$s) — Original wird gesendet.",
    },
    "wp-compact: %s": {"fr": "wp-compact: %s", "es": "wp-compact: %s", "de": "wp-compact: %s"},
    "Access denied.": {
        "fr": "Accès refusé.",
        "es": "Acceso denegado.",
        "de": "Zugriff verweigert.",
    },
    "wp-compact — Image compression": {
        "fr": "wp-compact — Compression d’images",
        "es": "wp-compact — Compresión de imágenes",
        "de": "wp-compact — Bildkompression",
    },
    "Compression runs in your browser (WebAssembly). Images are not sent to a third-party server.": {
        "fr": "La compression s’exécute dans votre navigateur (WebAssembly). Les images ne sont pas envoyées à un serveur tiers.",
        "es": "La compresión se ejecuta en su navegador (WebAssembly). Las imágenes no se envían a un servidor de terceros.",
        "de": "Die Kompression läuft in Ihrem Browser (WebAssembly). Bilder werden nicht an einen Drittanbieter-Server gesendet.",
    },
    "Settings (safe mode)": {
        "fr": "Réglages (mode sûr)",
        "es": "Ajustes (modo seguro)",
        "de": "Einstellungen (sicherer Modus)",
    },
    "JPEG quality": {"fr": "Qualité JPEG", "es": "Calidad JPEG", "de": "JPEG-Qualität"},
    "WebP quality": {"fr": "Qualité WebP", "es": "Calidad WebP", "de": "WebP-Qualität"},
    "Oxipng level (0–6)": {
        "fr": "Niveau Oxipng (0–6)",
        "es": "Nivel Oxipng (0–6)",
        "de": "Oxipng-Stufe (0–6)",
    },
    "Minimum savings (%)": {
        "fr": "Gain minimum (%)",
        "es": "Ahorro mínimo (%)",
        "de": "Mindesteinsparung (%)",
    },
    "Concurrency": {"fr": "Concurrence", "es": "Concurrencia", "de": "Parallelität"},
    "Create a backup before replacing": {
        "fr": "Créer une backup avant remplacement",
        "es": "Crear una copia de seguridad antes de reemplazar",
        "de": "Vor dem Ersetzen ein Backup erstellen",
    },
    "On image upload": {
        "fr": "À l’ajout d’images (upload)",
        "es": "Al subir imágenes",
        "de": "Beim Hochladen von Bildern",
    },
    "Disabled": {"fr": "Désactivé", "es": "Desactivado", "de": "Deaktiviert"},
    "Compress only (same format)": {
        "fr": "Compression seule (même format)",
        "es": "Solo comprimir (mismo formato)",
        "de": "Nur komprimieren (gleiches Format)",
    },
    "Convert to WebP": {
        "fr": "Convertir en WebP",
        "es": "Convertir a WebP",
        "de": "Nach WebP konvertieren",
    },
    "Allow a larger file (not recommended)": {
        "fr": "Autoriser un fichier plus gros (non recommandé)",
        "es": "Permitir un archivo más grande (no recomendado)",
        "de": "Größere Datei erlauben (nicht empfohlen)",
    },
    "Advanced mode (risky)": {
        "fr": "Mode avancé (risqué)",
        "es": "Modo avanzado (arriesgado)",
        "de": "Erweiterter Modus (riskant)",
    },
    "Changing format or dimensions can break the site (themes, CSS, hardcoded URLs). Use only if you know what you are doing.": {
        "fr": "Changer le format ou les dimensions peut casser le site (thèmes, CSS, URLs). Utilisez uniquement si vous savez ce que vous faites.",
        "es": "Cambiar el formato o las dimensiones puede romper el sitio (temas, CSS, URLs). Úselo solo si sabe lo que hace.",
        "de": "Format- oder Größenänderungen können die Website beschädigen (Themes, CSS, URLs). Nur verwenden, wenn Sie wissen, was Sie tun.",
    },
    "Enable advanced mode for this job": {
        "fr": "Activer le mode avancé pour ce job",
        "es": "Activar el modo avanzado para este trabajo",
        "de": "Erweiterten Modus für diesen Auftrag aktivieren",
    },
    "Max width (0 = keep)": {
        "fr": "Largeur max (0 = garder)",
        "es": "Ancho máx. (0 = mantener)",
        "de": "Max. Breite (0 = behalten)",
    },
    "Max height (0 = keep)": {
        "fr": "Hauteur max (0 = garder)",
        "es": "Alto máx. (0 = mantener)",
        "de": "Max. Höhe (0 = behalten)",
    },
    "Convert to": {"fr": "Convertir vers", "es": "Convertir a", "de": "Konvertieren nach"},
    "None (keep format)": {
        "fr": "Aucun (garder le format)",
        "es": "Ninguno (mantener formato)",
        "de": "Keines (Format behalten)",
    },
    "AVIF quality (0–63 legacy → mapped to %)": {
        "fr": "Qualité AVIF (0–63 legacy → mappé en %)",
        "es": "Calidad AVIF (0–63 legacy → mapeado a %)",
        "de": "AVIF-Qualität (0–63 Legacy → als % abgebildet)",
    },
    "Save settings": {
        "fr": "Enregistrer les réglages",
        "es": "Guardar ajustes",
        "de": "Einstellungen speichern",
    },
    "Compress entire Media Library": {
        "fr": "Compresser toute la médiathèque",
        "es": "Comprimir toda la biblioteca de medios",
        "de": "Gesamte Mediathek komprimieren",
    },
    "Migrate to WebP + update links": {
        "fr": "Migrer vers WebP + maj liens",
        "es": "Migrar a WebP + actualizar enlaces",
        "de": "Nach WebP migrieren + Links aktualisieren",
    },
    "Stop": {"fr": "Arrêter", "es": "Detener", "de": "Stoppen"},
    "Migration converts to WebP, rewrites URLs in the database, and keeps previous files tagged for later cleanup.": {
        "fr": "La migration convertit en WebP, réécrit les URLs en base, et conserve les anciens fichiers tagués pour nettoyage ultérieur.",
        "es": "La migración convierte a WebP, reescribe las URLs en la base de datos y conserva los archivos anteriores etiquetados para una limpieza posterior.",
        "de": "Die Migration konvertiert nach WebP, schreibt URLs in der Datenbank um und behält vorherige Dateien für spätere Bereinigung markiert.",
    },
    "Progress": {"fr": "Progression", "es": "Progreso", "de": "Fortschritt"},
    "Legacy file cleanup": {
        "fr": "Nettoyage des fichiers legacy",
        "es": "Limpieza de archivos legacy",
        "de": "Bereinigung von Legacy-Dateien",
    },
    "After a WebP migration, previous JPG/PNG files (and thumbnails) remain on disk. Review the site, then purge them here.": {
        "fr": "Après une migration WebP, les anciens JPG/PNG (et miniatures) restent sur le disque. Vérifiez le site, puis purgez-les ici.",
        "es": "Tras una migración WebP, los JPG/PNG anteriores (y miniaturas) permanecen en el disco. Revise el sitio y luego purgue aquí.",
        "de": "Nach einer WebP-Migration bleiben vorherige JPG/PNG-Dateien (und Miniaturen) auf der Festplatte. Prüfen Sie die Website und bereinigen Sie sie hier.",
    },
    "Refresh": {"fr": "Actualiser", "es": "Actualizar", "de": "Aktualisieren"},
    "Delete selected": {
        "fr": "Supprimer la sélection",
        "es": "Eliminar selección",
        "de": "Auswahl löschen",
    },
    "Purge all": {"fr": "Tout purger", "es": "Purgar todo", "de": "Alles bereinigen"},
    "ID": {"fr": "ID", "es": "ID", "de": "ID"},
    "Title": {"fr": "Titre", "es": "Título", "de": "Titel"},
    "Files": {"fr": "Fichiers", "es": "Archivos", "de": "Dateien"},
    "Size": {"fr": "Poids", "es": "Tamaño", "de": "Größe"},
    "Loading…": {"fr": "Chargement…", "es": "Cargando…", "de": "Laden…"},
    "Unable to create the backup folder.": {
        "fr": "Impossible de créer le dossier de backup.",
        "es": "No se puede crear la carpeta de copia de seguridad.",
        "de": "Backup-Ordner konnte nicht erstellt werden.",
    },
    "Backup source file not found.": {
        "fr": "Fichier source introuvable pour la backup.",
        "es": "No se encuentra el archivo de origen para la copia de seguridad.",
        "de": "Backup-Quelldatei nicht gefunden.",
    },
    "Backup copy failed.": {
        "fr": "Échec de la copie de backup.",
        "es": "Error al copiar la copia de seguridad.",
        "de": "Backup-Kopie fehlgeschlagen.",
    },
    "File not found.": {
        "fr": "Fichier introuvable.",
        "es": "Archivo no encontrado.",
        "de": "Datei nicht gefunden.",
    },
    "Unsupported MIME type.": {
        "fr": "Type MIME non supporté.",
        "es": "Tipo MIME no admitido.",
        "de": "Nicht unterstützter MIME-Typ.",
    },
    "No compressed file received.": {
        "fr": "Aucun fichier compressé reçu.",
        "es": "No se recibió ningún archivo comprimido.",
        "de": "Keine komprimierte Datei empfangen.",
    },
    "Compressed file is empty.": {
        "fr": "Fichier compressé vide.",
        "es": "El archivo comprimido está vacío.",
        "de": "Komprimierte Datei ist leer.",
    },
    "Compressed file is not smaller; replacement cancelled.": {
        "fr": "Le fichier compressé n’est pas plus petit ; remplacement annulé.",
        "es": "El archivo comprimido no es más pequeño; reemplazo cancelado.",
        "de": "Komprimierte Datei ist nicht kleiner; Ersetzung abgebrochen.",
    },
    "Uploaded file MIME type is not supported.": {
        "fr": "Type MIME du fichier envoyé non supporté.",
        "es": "El tipo MIME del archivo subido no es compatible.",
        "de": "MIME-Typ der hochgeladenen Datei wird nicht unterstützt.",
    },
    "WebP migration requires a WebP file.": {
        "fr": "La migration WebP nécessite un fichier WebP.",
        "es": "La migración WebP requiere un archivo WebP.",
        "de": "WebP-Migration erfordert eine WebP-Datei.",
    },
    "Unable to write the compressed file.": {
        "fr": "Impossible d’écrire le fichier compressé.",
        "es": "No se puede escribir el archivo comprimido.",
        "de": "Komprimierte Datei konnte nicht geschrieben werden.",
    },
    "Invalid attachment.": {
        "fr": "Pièce jointe invalide.",
        "es": "Adjunto no válido.",
        "de": "Ungültiger Anhang.",
    },
    "You are not allowed to edit this attachment.": {
        "fr": "Vous n’êtes pas autorisé à modifier cette pièce jointe.",
        "es": "No tiene permiso para editar este adjunto.",
        "de": "Sie dürfen diesen Anhang nicht bearbeiten.",
    },
    "No IDs provided.": {
        "fr": "Aucun ID fourni.",
        "es": "No se proporcionaron IDs.",
        "de": "Keine IDs angegeben.",
    },
    "Unable to read the file.": {
        "fr": "Lecture du fichier impossible.",
        "es": "No se puede leer el archivo.",
        "de": "Datei konnte nicht gelesen werden.",
    },
    "Missing file parameter.": {
        "fr": "Paramètre file manquant.",
        "es": "Falta el parámetro file.",
        "de": "Datei-Parameter fehlt.",
    },
    "File too large.": {
        "fr": "Fichier trop volumineux.",
        "es": "Archivo demasiado grande.",
        "de": "Datei zu groß.",
    },
    "WebP migration requires administrator privileges.": {
        "fr": "La migration WebP nécessite les droits administrateur.",
        "es": "La migración WebP requiere privilegios de administrador.",
        "de": "WebP-Migration erfordert Administratorrechte.",
    },
}


def collect_strings() -> OrderedDict[str, str]:
    found: OrderedDict[str, str] = OrderedDict()
    for path in sorted(ROOT.rglob("*.php")):
        if "node_modules" in path.parts:
            continue
        text = path.read_text(encoding="utf-8")
        for match in PAT.finditer(text):
            msgid = match.group(1).replace("\\'", "'")
            if msgid not in found:
                found[msgid] = path.relative_to(ROOT).as_posix()
    return found


def esc(value: str) -> str:
    return value.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


def write_po(
    path: pathlib.Path,
    lang_code: str,
    lang_name: str,
    strings: OrderedDict[str, str],
    key: str | None,
) -> None:
    now = datetime.datetime.utcnow().strftime("%Y-%m-%d %H:%M+0000")
    lines = [
        "# Copyright (C) 2026 morglaf",
        "# This file is distributed under the same license as the wp-compact package.",
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: wp-compact 1.0.0\\n"',
        '"Report-Msgid-Bugs-To: \\n"',
        f'"POT-Creation-Date: {now}\\n"',
        f'"PO-Revision-Date: {now}\\n"',
        '"Last-Translator: morglaf\\n"',
        f'"Language-Team: {lang_name}\\n"',
        f'"Language: {lang_code}\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"X-Domain: wp-compact\\n"',
        "",
    ]
    for msgid, rel in strings.items():
        lines.append(f"#: {rel}")
        lines.append(f'msgid "{esc(msgid)}"')
        if key is None:
            lines.append('msgstr ""')
        else:
            lines.append(f'msgstr "{esc(T[msgid][key])}"')
        lines.append("")
    path.write_text("\n".join(lines), encoding="utf-8")


def main() -> None:
    strings = collect_strings()
    print(f"Found {len(strings)} unique strings")
    missing = [s for s in strings if s not in T]
    if missing:
        print("MISSING translations:")
        for item in missing:
            print(" -", repr(item))
        raise SystemExit(1)

    LANG.mkdir(exist_ok=True)
    write_po(LANG / "wp-compact.pot", "en", "English", strings, None)
    write_po(LANG / "wp-compact-fr_FR.po", "fr_FR", "French", strings, "fr")
    write_po(LANG / "wp-compact-es_ES.po", "es_ES", "Spanish", strings, "es")
    write_po(LANG / "wp-compact-de_DE.po", "de_DE", "German", strings, "de")

    for po_name in ("wp-compact-fr_FR.po", "wp-compact-es_ES.po", "wp-compact-de_DE.po"):
        mo_name = po_name.replace(".po", ".mo")
        result = subprocess.run(
            ["msgfmt", "-o", str(LANG / mo_name), str(LANG / po_name)],
            capture_output=True,
            text=True,
        )
        if result.returncode != 0:
            print(result.stderr)
            raise SystemExit(result.returncode)
        print(f"{po_name} -> {mo_name}")

    print("Done")


if __name__ == "__main__":
    main()
