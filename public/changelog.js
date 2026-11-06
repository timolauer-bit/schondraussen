window.CHANGELOG = [
  { v: 7, d: '06.11.2026', t: [
    'Konto löschen in den Einstellungen: Die Daten bleiben noch eine Woche gespeichert, in dieser Zeit lässt sich die Löschung abbrechen, danach wird alles endgültig gelöscht',
    'Abmelden steht jetzt oben rechts neben der Versionsangabe',
    'Versionsangabe mit Rahmen'
  ] },
  { v: 6, d: '05.11.2026', t: [
    'Umzug auf einen eigenen Server (schondraussen.de): eigene Anmeldung mit E-Mail-Bestätigung und Passwort-Zurücksetzen, Mitgliedsnummern',
    'Testbetrieb: Neue Konten sehen erst Inhalte, wenn der Admin sie freigeschaltet hat. Der Admin bekommt bei jedem neuen Konto eine Mail und verwaltet alle Nutzer unter „Admin“',
    'Echte Datenquellen statt Claudes Wissen: TVmaze für Serien (Staffeln, kommende Folgen), MusicBrainz für Künstler:innen (Alben, angekündigte Veröffentlichungen), Open Library für Autor:innen (Bücher). Alle Einträge werden automatisch alle 12 Stunden aktualisiert',
    'Einträge hinzufügen mit Suche und Auswahl des richtigen Treffers; Entfernen über das Menü „…“',
    'Mail-Benachrichtigungen mit Abmeldelink: bei neu angekündigten Terminen und für Termine der nächsten 7 Tage, ein- und ausschaltbar in den Einstellungen, mit Testmail',
    'Design der Claude-Version übernommen: Kopf mit Versionsangabe, Reiterleiste, Listenspalte mit Filter, Eintragsseiten, Übersichten mit Zahlen, Kalender mit Monatsübersicht und Oberflächen (Hell, Dunkel, Papier, Kino, Minze, 80er)',
    'Neue Reiter „Admin“ (Nutzerverwaltung, Amazon-Partner-ID und Shop), „Info“ und „Kontakt“ (Chat mit dem Admin und roter Zahl für Ungelesenes)',
    'Versionsverlauf für alle: Die Versionsnummer oben rechts öffnet ihn, eine rote Zahl zeigt neue Änderungen seit dem letzten Besuch'
  ] },
  { v: 5, d: '04.11.2026', t: [
    'Neues Aussehen aus der Alternativversion: warmes Hellgrau, orangefarbener Akzent, Logo mit orangem „.de“, farbige Punkte für die Kategorien in der Leiste, rundere Ecken, Kalender- und Nutzer-Icon, eine abgesetzte Reiterleiste sowie eine neu gestaltete Listenspalte links (helle Karte, Suchsymbol, gewählter Eintrag mit Streifen in der Kategoriefarbe, runde Zahl für kommende Veröffentlichungen). Sortierung und Genre-Filter liegen jetzt hinter einem Filter-Knopf neben der Suche, aktive Filter erscheinen als Chip. Die Kategorie hat ein eigenes Symbol. Kommende Veröffentlichungen auf den Eintragsseiten und im Kalender sind kompakter. Der Kopf der Eintragsseite wurde angepasst: Streifen in der Kategoriefarbe, großer Titel, Genre-Chips, Symbole an den Knöpfen, Entfernen im Menü „…“. Die Kategoriefarben gelten auch in der Monatsübersicht des Kalenders'
  ] },
  { v: 4, d: '03.11.2026', t: [
    'Die obere Leiste mit den Reitern bleibt beim Scrollen samt Trennlinie oben stehen',
    'Kalender: Vor der Terminliste zeigt eine Monatsübersicht den aktuellen und die nächsten 3 Monate. Tage mit Veröffentlichungen haben eine Kreismarkierung wie ein Tortendiagramm, je Kategorie in einer eigenen Farbe',
    'Info, To-Do / Ideen: Kalender-Abo (.ics) als Idee ergänzt',
    'Linke Leiste: neuer Filter „Genre“ mit den wichtigsten Genres je Kategorie und Anzahl der Einträge',
    'Neu auf jeder Eintragsseite unten: „Ähnliche Autor:innen/Künstler:innen/Filme/Serien“ mit Vorschlägen von Claude, jeder Vorschlag lässt sich mit einem Klick zur Liste hinzufügen',
    'Neuer Reiter „Admin“ (nur für den Betreiber) links von „Einstellungen“: Amazon-Partner-ID und Shop, Nutzerverwaltung, gesperrte E-Mail-Adressen und „Als normaler Nutzer ansehen“',
    'Info: Hinweiszeile zu Aktualität, Quellen und Serverumzug steht jetzt unter „Quellen je Kategorie“',
    'Titel mit künftigem Jahr erscheinen auch bei den kommenden Veröffentlichungen in Kalender und Übersichten, wenn nur das Jahr oder Monat und Jahr bekannt ist',
    'Angekündigte Veröffentlichungen ohne bekanntes Datum werden mit „offen“ im Kalender und in den Übersichten angezeigt, ganz am Ende der Liste',
    'Filme: Die Abfrage liefert nur noch echte Filme (auch Anime-Filme), keine Romane, Comics oder Graphic Novels mehr',
    'Kommende Veröffentlichungen (Übersicht und Kalender): Der Name von Autor:in, Künstler:in, Film oder Serie ist anklickbar und öffnet die Seite',
    'Info: neuer Abschnitt „To-Do / Ideen“ mit den Punkten: weitere Shops als Alternative zu Amazon, mehr Auswahl im Kalender, gesehene Folgen abhaken',
    'Einstellungen: Buttons „Beispieleinträge hinzufügen“ und „Beispieleinträge entfernen“',
    'Neu angemeldete Nutzer ohne Einträge werden nach dem Willkommenstext gefragt, ob sie Beispieleinträge möchten'
  ] },
  { v: 3, d: '02.11.2026', t: [
    'Die Seite heißt jetzt Schondraussen.de (Titel, Überschrift, Willkommenstext und Info)',
    'Ein Klick auf eine Kategorie öffnet jetzt eine Übersicht mit Statistiken (Anzahl der Einträge, Bücher, Alben, Filme, Staffeln und Folgen) und den nächsten 10 kommenden Veröffentlichungen statt direkt den ersten Eintrag; ein Link führt von jedem Eintrag zurück',
    'Info: einheitliche Textbreite, Einleitungstext oben und Abschnitt „Zum Testen“ mit Hinweisen und geprüften Beispielen',
    'Neue Oberfläche „80er“ (Neon-Pink und Cyan auf Dunkelviolett) in den Einstellungen',
    'Info: Quellen sind jetzt mit Links zu den jeweiligen Seiten versehen',
    'Neuer Reiter „Info“ zwischen „Einstellungen“ und „Kontakt“: wann Einträge erneuert werden, welche Quellen genutzt werden, was sich beim Umzug auf einen Server ändert',
    'Nur Admin: „Als normaler Nutzer ansehen“, um die Seite so zu prüfen, wie andere sie sehen',
    'Versionsnummer zählt nur noch täglich hoch, die Änderungen eines Tages stehen gesammelt in einer Liste',
    'Hinweis unter „Neu hinzufügen“: Daten sind nur bis Ende Juni 2026 aktuell, bessere Ergebnisse nach dem Umzug auf einen echten Server',
    'Autor:innen und Künstler:innen: Angabe, wie viele offizielle Bücher bzw. Alben es ungefähr insgesamt gibt',
    'Autor:innen und Künstler:innen: deutlich mehr Werke pro Eintrag und neuer Button „Mehr Werke laden“ für fehlende Titel',
    'Meintest-du-Vorschläge: Die Auswahl wird gespeichert, die Vorschläge stehen untereinander',
    'Versionsverlauf für alle: Die Versionsnummer oben rechts öffnet ihn, eine rote Zahl zeigt neue Änderungen seit dem letzten Besuch',
    'Kontakt-Chat: Haken wie bei WhatsApp (gesendet, zugestellt, gelesen in Blau)',
    'Einstellungen öffnen sich wie die anderen Bereiche im Hauptfenster und stehen als Button in der oberen Leiste neben „Kontakt“',
    '„Mail bei Neuem“ und „Mail zum Termin“: Haken links, × zum Zurücknehmen'
  ] },
  { v: 2, d: '23.10.2026', t: [
    'Support-Chat mit Button in der oberen Leiste (später in „Kontakt“ umbenannt), roter Zahl für Ungelesenes und Unterhaltungsliste für den Admin',
    'Obere Leiste ohne Scrollbalken, sie bricht bei Platzmangel um',
    'Kalender: Buttons rechts entfernt, kleiner Pfeil-Button hinter dem Titel öffnet die Seite des Eintrags'
  ] },
  { v: 1, d: 'bis 22.10.2026', base: true, t: [
    'Kalender und Suche, Serien mit Streaminganbietern, Kinostarttermine und Kinos am Ort',
    'Einstellungen als eigene Ansicht, Rahmen um die linke Leiste',
    'Mehrere Nutzer mit Anmeldung, deine Partner-ID in allen Links und Admin-Übersicht',
    'Meintest-du-Vorschläge, Limit von 100 pro Liste, Sortierung der linken Leiste',
    'Farbvarianten und schmalere Seitenränder'
  ] }
];
