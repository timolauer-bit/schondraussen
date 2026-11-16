<?php
// Text der Einladungsmail: im Adminmenü (Einladen) editierbar, sonst dieser Standardtext.
function invite_text_default(): string {
    return <<<'TXT'
Hallo!

Ich habe eine kleine Webseite gebaut und würde mich freuen, wenn du sie ausprobierst: https://schondraussen.de

Worum geht's
Du legst dir eine Liste mit Autor:innen, Buchreihen, Bands und Serien an, die dich interessieren. Die Seite sagt dir dann, wann etwas Neues erscheint: Es gibt einen Kalender mit allen Terminen, auf Wunsch eine Mail (sofort, täglich oder wöchentlich) und ein Kalender-Abo für dein Handy oder deinen Computer. Zu jedem Eintrag siehst du außerdem die bisherigen Werke (Bücher, Alben, Staffeln).

Was schon gut klappt
Serien und Musik. Bei Büchern funktionieren Autor:innen gut, Mangas erst rudimentär.

Wo es noch hapert
• Bücher und Mangas: Die Daten kommen aus öffentlichen Katalogen und sind nicht immer vollständig. Bei Mangas und Comics kann etwas fehlen oder ein Band falsch einsortiert sein. Bei manchen Autor:innen fehlen die Genres. Teilweise tauchen auch Ergebnisse in anderen Sprachen auf.
• Filme gehen noch nicht. Dafür fehlt mir die Genehmigung des Datendienstes. Sobald die da ist, kommen sie dazu.
• Die Handyansicht habe ich selbst noch nicht richtig geprüft. Am Computer sollte alles passen, am Handy kann noch etwas komisch aussehen.

Dein Feedback
Die Seite ist ganz neu, deshalb ist dein Feedback Gold wert. Unter „Kontakt“ (Auswahl „Idee“ oder „Bugmeldung“) kannst du Wünsche und Fehler eintragen. Am meisten helfen mir Screenshots, wenn etwas komisch aussieht.

Datenschutz und Geld
Du brauchst nur eine E-Mail-Adresse und einen frei gewählten Benutzernamen, kein Klarname. Du kannst dein Konto jederzeit selbst löschen.
Die Seite ist ein privates Projekt ohne Werbung. Die Links zu Amazon bei den Einträgen sind einfache Suchlinks ohne Provision. Wenn dir die Seite gefällt, kannst du freiwillig etwas spenden, ein Muss ist das nicht.

Danke fürs Ausprobieren!
TXT;
}
function invite_text(PDO $pdo): string {
    try { $s = $pdo->prepare("SELECT v FROM app_texts WHERE k='invite_text'"); $s->execute(); $v = $s->fetchColumn(); if (is_string($v) && trim($v) !== '') return $v; } catch (Throwable $e) {}
    return invite_text_default();
}
/** Pflichtteil, den die Seite immer unter den Text hängt. */
function invite_text_footer(string $link): string {
    return "\n\n----------\nDein persönlicher Einladungslink (einmal verwendbar, 14 Tage gültig, bitte mit dieser E-Mail-Adresse registrieren):\n$link\n\nDein Konto ist danach sofort freigeschaltet.\n\nWenn du die Einladung nicht erwartet hast, ignoriere diese Mail einfach.";
}
