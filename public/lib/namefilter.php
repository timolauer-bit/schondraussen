<?php
// Prüfung von Benutzernamen: Verwechslung mit Betreiber/Support, anstößige oder strafbare Begriffe.
// Heuristik mit Wortliste. Sie fängt die üblichen Fälle, ersetzt aber keine Kontrolle durch den Admin.

/** Vereinfachte Schreibweise: klein, ohne Akzente, Leetspeak aufgelöst, ohne Trennzeichen, Doppelbuchstaben gekürzt. */
function name_fold(string $n): string {
    $n = mb_strtolower($n);
    $n = strtr($n, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ç' => 'c']);
    $n = strtr($n, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b', '@' => 'a', '$' => 's', '!' => 'i', '|' => 'i']);
    $n = preg_replace('/[^a-z]/', '', $n);
    return (string) preg_replace('/(.)\1+/', '$1', $n); // fuuuck -> fuck
}

/** Gibt eine Fehlermeldung zurück oder null. */
function name_policy_error(string $name): ?string {
    $f = name_fold($name);
    // Verwechslung mit Betreiber und Support (auch Varianten wie 4dmin, adm1n, sup-port, supp0rt)
    foreach (['admin', 'support', 'moderator', 'schondraussen', 'betreiber', 'helpdesk', 'kundendienst', 'inhaber', 'timolauer'] as $r) {
        if (strpos($f, name_fold($r)) !== false) return 'Dieser Name ist reserviert oder könnte mit dem Betreiber verwechselt werden.';
    }
    // Anstößig, sexuell, beleidigend, verfassungsfeindlich oder diskriminierend (gefaltete Schreibweise)
    static $bad = [
        'hitler', 'nazi', 'siegheil', 'sieghei', 'heilhitler', 'hakenkreuz', 'swastika', 'sturmabteilung', 'waffenss', 'reichsbuerger', 'reichsburger',
        'whitepower', 'whitepride', 'rassenhass', 'judenhass', 'holocaust', 'auschwitz', 'goebbels', 'himmler', 'mussolini', 'isis', 'alqaida', 'daesh',
        'nigger', 'niger', 'nigga', 'neger', 'kanake', 'zigeuner', 'schwuchtel', 'tranny', 'faggot', 'fagot', 'spast', 'mongo', 'untermensch', 'fotze', 'votze',
        'fuck', 'fick', 'wichs', 'wixx', 'porn', 'porno', 'pedo', 'paedo', 'padophil', 'kinderporn', 'vergewalt', 'rape', 'blowjob', 'cumshot', 'dildo', 'hure', 'nutte', 'schlampe',
        'arschloch', 'scheis', 'shit', 'bitch', 'whore', 'slut', 'pussy', 'penis', 'vagina', 'titten', 'hurensohn', 'wichser', 'missgeburt', 'drecksau',
        'terror', 'amok', 'cunt', 'massaker', 'selbstmord', 'suizid', 'kill', 'toeten', 'tot',
    ];
    foreach ($bad as $w) {
        $wf = name_fold($w);
        if (in_array($wf, ['kill', 'tot', 'isis', 'rape', 'niger', 'mongo', 'penis', 'amok', 'cunt'], true)) { // kurze Begriffe nur, wenn sie der ganze Name sind
            if ($f === $wf) return 'Dieser Name ist nicht erlaubt. Bitte wähle einen anderen.';
            continue;
        }
        if (strpos($f, $wf) !== false) return 'Dieser Name ist nicht erlaubt. Bitte wähle einen anderen.';
    }
    if (preg_match('/k\s*k\s*k/i', $name) || preg_match('/(?<!\d)(1488|14\/88)(?!\d)/', $name)) return 'Dieser Name ist nicht erlaubt. Bitte wähle einen anderen.';
    return null;
}
