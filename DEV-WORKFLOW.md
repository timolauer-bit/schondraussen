# Live- und Entwicklungsversion

Admins sehen oben in der Leiste einen Schalter „Live" / „Dev". Er setzt nur das Cookie `sd_dev` (wirksam für Admins und für Gruppen mit dem Recht „Entwicklerversion nutzen“; Alphatester haben es standardmäßig).

- Live: `public/app.js`, `public/app.css`
- Entwicklung: `public/dev/app.js`, `public/dev/app.css` (gleiche Datenbank, gleiche API)

Ablauf: Änderungen an Oberfläche erst in `public/dev/` einbauen und pushen, im Dev-Modus prüfen. Zur Freigabe die beiden Dev-Dateien über die Live-Dateien kopieren (`cp public/dev/app.js public/app.js; cp public/dev/app.css public/app.css`) und pushen.

Grenzen: PHP, API und Datenbank-Änderungen sind für beide Versionen gleich. Neue Server-Funktionen daher abwärtskompatibel bauen oder hinter einem Admin-Schalter halten.
