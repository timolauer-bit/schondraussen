# Schondraussen.de

Persönlicher Medien-Tracker: Autor:innen, Künstler:innen, Filme und Serien verfolgen, Neuerscheinungen im Kalender sehen und per Mail benachrichtigt werden.

## Aufbau
- `public/` – wird auf den Webspace geladen (PHP-Backend und Oberfläche)
- `db/schema.sql` – Datenbankentwurf
- `prototype/claude-artifact.html` – der bisherige Prototyp aus Claude (Vorlage für die Oberfläche)
- `.github/workflows/deploy.yml` – lädt `public/` bei jedem Push auf `main` per FTPS auf `test.schondraussen.de`

## Einrichtung des Deployments (einmalig, im Repository unter Settings → Secrets and variables → Actions)
| Name | Inhalt |
|---|---|
| `FTP_SERVER` | FTP-Host aus dem KAS |
| `FTP_USERNAME` | FTP-Benutzer |
| `FTP_PASSWORD` | FTP-Passwort |
| `FTP_DIR` | Zielordner der Subdomain, z. B. `/test.schondraussen.de/` (mit Slash am Ende) |

Die Datei `config.php` (Datenbank, Mail, API-Schlüssel) wird nur auf dem Server angelegt (Vorlage: `public/config.example.php`) und nie ins Repository eingecheckt.
