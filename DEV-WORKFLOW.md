# Live- und Entwicklungsversion

Admins sehen oben in der Leiste einen Schalter „Live" / „Dev". Er setzt nur das Cookie `sd_dev` (wirksam für Admins und für Gruppen mit dem Recht „Entwicklerversion nutzen“; Alphatester haben es standardmäßig).

- Live: `public/app.js`, `public/app.css`
- Entwicklung: `public/dev/app.js`, `public/dev/app.css` (gleiche Datenbank, gleiche API)

Ablauf: Änderungen an Oberfläche erst in `public/dev/` einbauen und pushen, im Dev-Modus prüfen. Zur Freigabe die beiden Dev-Dateien über die Live-Dateien kopieren (`cp public/dev/app.js public/app.js; cp public/dev/app.css public/app.css`) und pushen.

Grenzen: PHP, API und Datenbank-Änderungen sind für beide Versionen gleich. Neue Server-Funktionen daher abwärtskompatibel bauen oder hinter einem Admin-Schalter halten.

## Markierung der Unterschiede

- `public/dev/changes.json` listet kurz, wo die Dev-Version abweicht, z. B. `["Serien → Seite einer einzelnen Serie"]`. Wer das Recht „Entwicklerversion nutzen“ hat, sieht die Anzahl als rote Zahl am Live/Dev-Schalter, der Hover-Text nennt die Bereiche.
- Betroffene Elemente in `dev/app.js` mit `devMark(element, 'kurzname')` kennzeichnen. Im Dev-Modus bekommen sie einen schmalen gelben Rahmen (`.devmode [data-dev]`).
- Bei jeder Freigabe: `public/dev/changes.json` auf `[]` zurücksetzen (und die Dateien wie oben kopieren).

## Design-Varianten (parallel zu Live und Dev)

Zum Ausprobieren von Umgestaltungen gibt es zusätzlich Design-Varianten. Der Schalter oben (Live / Dev / Name) öffnet ein Menü mit allen Versionen; gewählt wird per Cookie `sd_dev` (`d:<kennung>`), wirksam nur mit dem Recht „Entwicklerversion nutzen“.

- Jede Variante liegt in `public/designs/<kennung>/app.js` und `app.css` (vollständige Kopien, z. B. von `dev/`). Kennung: Kleinbuchstaben, Ziffern, Bindestrich, höchstens 30 Zeichen.
- Eingetragen wird sie in `public/designs/index.json`: `[{"slug":"nav-b","name":"Navigation B","desc":"Kurzbeschreibung"}]`. Nur eingetragene Varianten mit beiden Dateien erscheinen im Menü.
- Gleiche Datenbank und API wie Live und Dev. Eine Variante wird gelöscht, indem Ordner und Eintrag entfernt werden und der Ordner in `deploy-remove.txt` eingetragen wird (der Upload selbst löscht nichts auf dem Webspace; `scripts/remove_ftps.py` löscht die dort genannten Pfade nach dem Upload).
- Gefällt eine Variante: Dateien nach `public/dev/` und `public/` kopieren (wie bei der Freigabe), Eintrag und Ordner danach entfernen.

## Mailtexte und Admin-Reiter „Infomails“
Alle Mails laufen über `mail_html()` in `public/lib/mail.php` (Design „Karte“ seit 19.11.2026, serifenlos, Tabellenlayout mit Inline-CSS, Dark Mode). Die Klartext-Regeln (Einträge `- {series} Name – Titel (TT.MM.JJJJ)`, Überschriften in GROSSBUCHSTABEN, Knöpfe `[[Text|URL]]`, `--` vor dem Fußbereich) stehen oben in mail.php. Die Kategorie-Markierung `{…}` entfernt `mail_plain()` aus der Textfassung.
Die Vorschau (`public/lib/mailpreview.php`) spiegelt die festen Mailtexte aus auth.php, admin.php, chat.php, feedback.php und notify.php. Ändert sich dort ein Text, bitte in `mailpreview.php` mitändern.

## Einheitliche Knöpfe
Gleiche Aktion, gleiche Beschriftung in allen Kategorien: Suchfeld mit „Suchen“, Hinzufügen eines gefundenen Treffers mit „Hinzufügen“, Abbrechen mit „Abbrechen“. Neue Knöpfe vorher mit `grep -n "'Suchen'\|'Hinzufügen'" public/app.js` auf vorhandene Beschriftungen prüfen.

Erlaubte Knopfklassen (Stand 17.11.2026):
- **Aktionen:** `btn` (Hauptaktion), `btn ghost` (Nebenaktion), Zusatz `small` für kleine Knöpfe in Listen und Karten.
- **Warnen und Löschen:** `btn warn small` für zurückhaltende Lösch- und Warnknöpfe (z. B. „Entfernen“, „Konto löschen“). `btn danger` nur für endgültige, nicht umkehrbare Aktionen.
- **Auswahl statt Aktion:** `pill` (Status und kleine Umschalter), `admtab` für Reiterleisten (Admin, Info, Community-Empfehlungen), `cchip`/`lchip` für Filterfelder (Kategorien, Kalender), `kindbtn` für die Art der Nachricht im Kontakt, `dmi` für Einträge im Versionsmenü.
- **Symbolknöpfe:** `favbtn`, `bellbtn`, `pgbtn` und `attach` sind `btn ghost small` mit festem Zusatz für Größe und Farbe.

Neue Klassen nur, wenn keine dieser Arten passt, und dann hier eintragen. Bei einer kompletten Neugestaltung des Designs diese Liste mit Timo durchgehen (er möchte dann daran erinnert werden).

## Pushen nur auf Freigabe (ab 16.11.2026)
Jeder Push löst einen Deploy aus (seit 17.11.2026 ohne Wartungsseite; während der 3–4 Minuten können einzelne Bereiche kurz Fehler zeigen, vor allem wenn neue Dateien dazukommen). Änderungen werden deshalb nur lokal committet und gesammelt. Gepusht wird erst, wenn Timo es ausdrücklich freigibt („hochladen“, „pushen“).
