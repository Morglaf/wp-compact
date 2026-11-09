# WPsquoosh

Plugin WordPress qui compresse les images de la médiathèque **dans le navigateur** (WebAssembly / [jSquash](https://github.com/jamsinclair/jSquash), codecs dérivés de [Squoosh](https://github.com/GoogleChromeLabs/squoosh)). Les fichiers ne sont pas envoyés à un serveur tiers : seule la version compressée est renvoyée à WordPress via l’API REST.

Auteur : **morglaf** — licence Apache-2.0.

## Fonctionnalités

- Compression par lot de toute la médiathèque (JPEG, PNG, WebP, AVIF)
- Compression à l’upload (même format ou conversion WebP)
- Backup optionnelle avant remplacement
- Migration WebP avec réécriture des URLs en base
- Nettoyage des fichiers legacy après migration
- Mode avancé (redimensionnement / conversion de format) — à utiliser avec prudence

## Prérequis

- WordPress 6.0+
- PHP 7.4+
- Navigateur récent avec support WebAssembly (pour l’admin)

## Installation

1. Copier le dossier du plugin dans `wp-content/plugins/wpsquoosh`  
   (ou installer le zip `wpsquoosh.zip` : le dossier interne doit s’appeler `wpsquoosh`).
2. Activer **WPsquoosh** dans Extensions.
3. Ouvrir **Médias → Compresser (WPsquoosh)**.

## Développement

```bash
npm install
npm run build
```

Mode watch : `npm run watch`.

Sur Windows, le script local `build-zip.ps1` (ignoré par git) lance le build puis produit `dist/wpsquoosh.zip` sans `node_modules` ni sources JS.

## Crédits

Codecs et outils basés sur Squoosh / jSquash. WPsquoosh est un plugin indépendant, non affilié à Google.
