<?php
// Vorschau aller Mailarten für den Admin-Reiter „Infomails“: so wie sie an das Konto des Admins gehen würden.
// Es wird nichts verschickt und nichts gespeichert (außer einem fehlenden Abmelde-Token des Admin-Kontos).
// Wo es geht, laufen die echten Funktionen (mail_html, mail_footer, invite_text, farewell_line). Die festen Texte sind hier
// gespiegelt: Ändert sich ein Mailtext im Code, bitte hier mit ändern (siehe DEV-WORKFLOW.md).
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/invite.php';
require_once __DIR__ . '/privacy.php';

function mail_previews(PDO $pdo, array $u): array {
    date_default_timezone_set('Europe/Berlin');
    $base = base_url(); $uname = (string) ($u['username'] ?? 'Admin');
    $unsub = $base . '/unsubscribe.php?t=' . user_unsub_token($pdo, $u);
    $foot = fn(string $t) => $t . mail_footer($unsub);
    $tok = 'BEISPIELTOKEN0123456789abcdef0123456789abcdef0123456789abcdef';
    $d = fn(int $n) => date('d.m.Y', strtotime("+$n days"));
    $fmt = fn(int $n) => fmt_date(date('Y-m-d', strtotime("+$n days")));
    $L = [];
    $add = function (string $group, string $id, string $label, string $when, string $subject, string $text) use (&$L) {
        $L[] = ['group' => $group, 'id' => $id, 'label' => $label, 'when' => $when, 'subject' => $subject, 'text' => mail_plain($text), 'html' => mail_html($text, $subject)];
    };
    // Benachrichtigungen an Nutzer
    $add('Benachrichtigungen', 'morning', 'Morgenmail (Neuigkeiten)', 'Täglich ab 7 Uhr, wenn es etwas Neues gibt', 'Schondraussen.de: 3 Neuigkeiten',
        $foot("Hallo,\n\nes gibt Neuigkeiten in deinen Listen.\n\nHEUTIGE VERÖFFENTLICHUNGEN\n- {series} Beispiel-Serie – S01E01 Folgentitel\n  Kurzinfo zur Serie, höchstens 8 Zeilen aus den Datenquellen der Seite.\n  Presse: Beispielschlagzeile zur Serie (Beispielquelle)\n  https://example.org/artikel\n\nNEU ERSCHIENEN\n- {artist} Beispiel-Musiker – Album, das schon erschienen ist (" . fmt_date(date('Y-m-d', strtotime('-3 days'))) . ")\n\nNEU ANGEKÜNDIGT\n- {artist} Beispiel-Band – Neues Album (" . $fmt(9) . ")\n- {author} Beispiel-Autorin – Neuer Roman (" . $fmt(30) . ")\n\nNEUE PODCAST-FOLGEN\n- {podcast} Beispiel-Podcast – Folge 128: Beispieltitel (" . $fmt(0) . ")\n\nAlles im Überblick: $base/\n\nDie Schlagzeilen stammen aus der Google-News-Suche, die Kurzinfos aus den Datenquellen der Seite.\n"));
    $add('Benachrichtigungen', 'week', 'Wochenübersicht', 'Am gewählten Wochentag, wenn Termine in den nächsten 14 Tagen anstehen', 'Schondraussen.de: Deine Wochenübersicht (2 Termine)',
        $foot("Hallo,\n\nhier sind die Termine der nächsten 14 Tage aus deinen Listen:\n\n- {artist} Beispiel-Band – Neues Album (" . $fmt(3) . ")\n- {series} Beispiel-Serie – S02E05 Folgentitel (" . $fmt(9) . ")\n\nAlles im Überblick: $base/\n"));
    $add('Benachrichtigungen', 'testmail', 'Testmail der Einstellungen („Vorschau senden“)', 'Auf Knopfdruck in den Einstellungen', 'Schondraussen.de: Vorschau deiner Benachrichtigungen', $foot(build_preview($pdo, $u)[1]));
    $add('Benachrichtigungen', 'admintest', 'Testmail aus dem Admin-Bereich', 'Auf Knopfdruck unter Admin, Tools', 'Neuerscheinungen bei deinen Einträgen (Testmail)',
        $foot("Hallo,\n\nbei deinen Einträgen gibt es Neues:\n\n- Beispiel-Künstler: neues Album „Beispieltitel“ erscheint am 20.11.2026\n- Beispiel-Autorin: neues Buch „Beispielroman“ erscheint am 03.12.2026\n\nAlle Termine siehst du im Kalender: $base/\n\nDas ist eine Testmail im Format der echten Benachrichtigungen.\n"));
    // Konto
    $add('Konto', 'verify', 'E-Mail-Adresse bestätigen', 'Nach der Registrierung', 'Bitte bestätige deine E-Mail-Adresse',
        "Willkommen bei Schondraussen.de!\n\nBitte bestätige deine Adresse mit einem Klick (der Link ist 24 Stunden gültig):\n\n[[E-Mail-Adresse bestätigen|$base/api/auth.php?a=verify&t=$tok]]\n\nWenn du dich nicht angemeldet hast, kannst du diese Mail ignorieren.\n");
    $add('Konto', 'reset', 'Passwort zurücksetzen', 'Bei „Passwort vergessen“', 'Passwort zurücksetzen',
        "Du hast ein neues Passwort angefordert. Der Link ist eine Stunde gültig:\n\n[[Neues Passwort festlegen|$base/?reset=$tok]]\n\nWenn du das nicht warst, kannst du diese Mail ignorieren.\n");
    $add('Konto', 'newmail', 'Neue E-Mail-Adresse bestätigen', 'An die neue Adresse beim Ändern', 'Bitte bestätige deine neue E-Mail-Adresse',
        "Du möchtest bei Schondraussen.de diese Adresse für dein Konto verwenden. Bestätige sie mit einem Klick (der Link ist 24 Stunden gültig):\n\n[[Neue E-Mail-Adresse bestätigen|$base/api/auth.php?a=emailconfirm&t=$tok]]\n\nWenn du das nicht warst, kannst du diese Mail ignorieren, dann ändert sich nichts.\n");
    $add('Konto', 'mailreq', 'Hinweis: Änderung der Adresse beantragt', 'An die alte Adresse beim Ändern', 'Änderung deiner E-Mail-Adresse beantragt',
        "Für dein Konto bei Schondraussen.de wurde eine neue E-Mail-Adresse beantragt. Solange sie nicht bestätigt ist, bleibt diese Adresse aktiv.\n\nWar das nicht deine Absicht, ändere bitte dein Passwort.\n");
    $add('Konto', 'mailchanged', 'Hinweis: Adresse wurde geändert', 'An die alte Adresse nach der Bestätigung', 'Deine E-Mail-Adresse wurde geändert',
        "Die E-Mail-Adresse deines Kontos bei Schondraussen.de wurde auf eine neue Adresse geändert.\n\nWar das nicht deine Absicht, melde dich bitte beim Admin über das Impressum der Seite.\n");
    $add('Konto', 'mailremoved', 'Hinweis: Adresse wurde entfernt', 'Wenn jemand seine Adresse entfernt', 'Deine E-Mail-Adresse wurde entfernt',
        "Von deinem Konto bei Schondraussen.de wurde die E-Mail-Adresse entfernt. Du bekommst keine Mails mehr.\n\nAnmelden kannst du dich mit deinem Benutzernamen und Passwort. Falls du das Passwort vergisst, hilft dir nur noch dein Wiederherstellungscode, der gerade neu erzeugt wurde.\n\nWar das nicht deine Absicht, melde dich bitte beim Admin über das Impressum der Seite.\n");
    $until = date('d.m.Y', time() + 7 * 86400);
    $add('Konto', 'delete', 'Konto wird gelöscht', 'Wenn jemand die Löschung beantragt', 'Dein Konto wird gelöscht',
        farewell_line($pdo, (int) $u['id']) . "\n\nDu hast die Löschung deines Kontos bei Schondraussen.de beantragt.\n\nDeine Daten bleiben noch eine Woche gespeichert und werden nach dem $until endgültig gelöscht. Wenn du dich bis dahin wieder anmeldest, kannst du die Löschung mit einem Klick abbrechen.\n\nWenn du das nicht warst, melde dich an und brich die Löschung ab, und ändere dann dein Passwort.\n");
    $add('Konto', 'rename', 'Neuen Benutzernamen wählen', 'Wenn der Admin einen Namen beanstandet', 'Bitte wähle einen neuen Benutzernamen',
        "Dein Benutzername auf Schondraussen.de entspricht nicht unseren Regeln.\n\nBitte melde dich an und wähle einen neuen Namen. Bis dahin kannst du die Seite nicht nutzen. Erlaubt sind Namen ohne anstößige, beleidigende oder verbotene Inhalte und ohne Verwechslung mit dem Betreiber.\n\n$base/\n");
    $add('Konto', 'invite', 'Einladung', 'Wenn du jemanden per Mail einlädst', 'Einladung zu Schondraussen.de',
        str_replace(["\r\n", "\r"], "\n", trim(invite_text($pdo))) . invite_text_footer($base . '/?invite=' . $tok));
    // An den Admin
    $add('An den Admin', 'newuser', 'Neues Konto registriert', 'Bei jeder Registrierung', 'Neues Konto auf Schondraussen.de',
        "Es hat sich ein neuer Nutzer registriert:\n\nBenutzername: Beispielnutzer\nE-Mail: ausgeblendet (Standard bei neuen Konten; im Notfall in der Nutzerverwaltung unter „E-Mail anzeigen“)\n\nDas Konto sieht keine Inhalte, bis du es freischaltest.\n\nKonto direkt freischalten (Link nur für dich, 14 Tage gültig):\n$base/approve.php?t=$tok\n\nOder in der Nutzerverwaltung:\n$base/\n\nKeine Freischaltung nötig? Dann ignoriere diese Mail einfach.\n");
    $add('An den Admin', 'idea', 'Neue Idee', 'Wenn jemand anderes eine Idee einreicht', 'Neue Idee auf Schondraussen.de',
        "Von Beispielnutzer:\n\nKategorie: Verbesserung einer Funktion\n\nKurzbeschreibung der Idee\n\nBeispieltext der Idee.\n\nZur Liste: $base/\n");
    $add('An den Admin', 'bug', 'Neue Bugmeldung', 'Wenn jemand anderes einen Fehler meldet', 'Neue Bugmeldung auf Schondraussen.de',
        "Von Beispielnutzer:\n\nKategorie: Menü und Darstellung\n\nKurzbeschreibung des Fehlers\n\nBeispieltext der Fehlermeldung.\n\nZur Liste: $base/\n");
    $add('An den Admin', 'msg', 'Neue Nachricht im Kontakt-Chat', 'Wenn ein Nutzer dir schreibt', 'Neue Nachricht auf Schondraussen.de',
        "Von Beispielnutzer:\n\nBeispielnachricht.\n\nAntworten unter Kontakt: $base/\n");
    $add('Konto', 'direct', 'Direktnachricht vom Admin', 'Wenn du einem Nutzer im Kontakt-Chat schreibst', '[Direkt vom Admin] Neue Nachricht auf Schondraussen.de',
        "Der Administrator von Schondraussen.de hat dir geschrieben:\n\nBeispielnachricht.\n\n---\nAuf diese E-Mail kannst du nicht antworten. Bitte antworte auf der Webseite unter „Kontakt“:\n$base/\n");
    $add('An den Admin', 'watchdog', 'Warnung: Cron-Job oder Sicherung', 'Wenn der Cron-Job sich seit Stunden nicht meldet oder die Datenbanksicherung fehlt (höchstens einmal am Tag)', 'Warnung Schondraussen.de: Cron-Job meldet sich nicht',
        "Der Cron-Job hat sich seit 6.5 Stunden nicht gemeldet. Einträge werden nicht aktualisiert und es gehen keine Benachrichtigungen raus.\nPrüfen: all-inkl KAS, Cronjobs, ob der Job aktiv ist und die Adresse mit Token stimmt.\n\nDiese Warnung kommt höchstens einmal am Tag, bis das Problem behoben ist.");
    return $L;
}
