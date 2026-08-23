<?php
/**
 * Gestore del modulo contatti per hosting Apache/PHP (Hostinger).
 *
 * Alternativa a un servizio esterno tipo Formspree. Per usarlo:
 *   1. imposta MODULO.endpoint = "/invia.php" in src/config/site.ts
 *   2. sostituisci $DESTINATARIO e $MITTENTE qui sotto con indirizzi reali
 *   3. verifica che l'hosting abbia mail() attiva
 *
 * ⚠️ DA VERIFICARE: la funzione mail() di PHP su hosting condiviso finisce
 *    spesso nello spam. Se le richieste dei locali non arrivano, il problema
 *    è quasi sempre questo. In quel caso conviene passare a un servizio
 *    esterno o a SMTP autenticato sulla casella del dominio.
 */

declare(strict_types=1);

// ─────────────────────────────────────────── ⚠️ DA VERIFICARE: indirizzi reali
$DESTINATARIO = 'info@lalma.it';
// Il mittente deve appartenere al dominio del sito, altrimenti i filtri
// antispam scartano il messaggio (SPF/DMARC).
$MITTENTE     = 'no-reply@lalma.it';
$PAGINA_OK    = '/grazie/';
$PAGINA_KO    = '/contatti/?errore=1';

// Solo POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $PAGINA_KO, true, 303);
    exit;
}

/** Legge un campo, taglia gli spazi e limita la lunghezza. */
function campo(string $nome, int $max = 500): string
{
    $valore = trim((string) ($_POST[$nome] ?? ''));
    return mb_substr($valore, 0, $max);
}

/**
 * Toglie ritorni a capo dai valori che finiscono nelle intestazioni della mail.
 * Senza questo, un "a capo" nel campo permetterebbe di iniettare intestazioni
 * arbitrarie e trasformare il modulo in un rilanciatore di spam.
 */
function pulisci_intestazione(string $valore): string
{
    return str_replace(["\r", "\n", "%0a", "%0d"], ' ', $valore);
}

// Trappola per i bot: un umano non vede questo campo, quindi non lo compila.
// Rispondiamo con un successo finto per non insegnare al bot come passare.
if (campo('sito_web') !== '') {
    header('Location: ' . $PAGINA_OK, true, 303);
    exit;
}

$nome      = campo('nome', 120);
$email     = campo('email', 180);
$locale    = campo('locale', 160);
$citta     = campo('citta', 120);
$telefono  = campo('telefono', 60);
$richiesta = campo('richiesta', 60);
$messaggio = campo('messaggio', 4000);
$consenso  = campo('consenso', 10);

// Validazione minima lato server: non ci si fida mai del solo `required` HTML.
$errori = [];
if ($nome === '') {
    $errori[] = 'nome';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errori[] = 'email';
}
if ($consenso === '') {
    $errori[] = 'consenso';
}

if ($errori !== []) {
    header('Location: ' . $PAGINA_KO, true, 303);
    exit;
}

$oggetto = pulisci_intestazione(
    sprintf('[Sito L\'Alma] %s — %s', $richiesta !== '' ? $richiesta : 'richiesta', $nome)
);

$corpo = "Nuova richiesta dal sito L'Alma\n"
    . str_repeat('-', 46) . "\n\n"
    . "Nome:      {$nome}\n"
    . "Email:     {$email}\n"
    . "Locale:    " . ($locale !== '' ? $locale : '—') . "\n"
    . "Città:     " . ($citta !== '' ? $citta : '—') . "\n"
    . "Telefono:  " . ($telefono !== '' ? $telefono : '—') . "\n"
    . "Richiesta: " . ($richiesta !== '' ? $richiesta : '—') . "\n\n"
    . "Messaggio:\n" . ($messaggio !== '' ? $messaggio : '—') . "\n\n"
    . str_repeat('-', 46) . "\n"
    . 'Consenso privacy: sì (' . date('d/m/Y H:i') . ")\n"
    . 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'n/d') . "\n";

$intestazioni = [
    'From: L\'Alma <' . $MITTENTE . '>',
    'Reply-To: ' . pulisci_intestazione($nome) . ' <' . pulisci_intestazione($email) . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
];

$inviata = mail(
    $DESTINATARIO,
    '=?UTF-8?B?' . base64_encode($oggetto) . '?=',
    $corpo,
    implode("\r\n", $intestazioni)
);

header('Location: ' . ($inviata ? $PAGINA_OK : $PAGINA_KO), true, 303);
exit;
