<?php
declare(strict_types=1);
// Portage jetable de la référence Python : vérifie que les vecteurs se reproduisent en PHP + GMP et mesure les temps.
// Usage : php faisabilite.php vecteurs.json   (PHP 8.2 ou plus, extension gmp)

final class ErreurMoteur extends RuntimeException {
    /** @param list<array{code:string,champ:string}> $erreurs */
    public function __construct(public readonly array $erreurs) { parent::__construct($erreurs[0]['code']); }
}

final class Moteur {
    const ALPHABET = 'abcdefghijklmnopqrstuvwxyz ,.';
    const CHIFFRES29 = '0123456789abcdefghijklmnopqrs';
    const LONGUEUR = 3200; const DEMI = 7773; const OCTETS = 972; const TOURS = 8; const BLOCS = 31;
    private string $kPerm; private string $kBruit; private string $empreinte;
    private GMP $n; private GMP $masque; private GMP $hexMax;

    public function __construct(#[\SensitiveParameter] string $cle) {
        if (strlen($cle) !== 32) throw new InvalidArgumentException('cle');
        $this->kPerm = hash_hmac('sha256', 'babel-moteur/v1/permutation', $cle, true);
        $this->kBruit = hash_hmac('sha256', 'babel-moteur/v1/bruit', $cle, true);
        $this->empreinte = hash_hmac('sha256', 'babel-moteur/v1/empreinte', $cle);
        $this->n = gmp_pow(29, self::LONGUEUR);
        $this->masque = gmp_sub(gmp_pow(2, self::DEMI), 1);
        $this->hexMax = gmp_div_q(gmp_sub($this->n, 1), 262400);
    }
    public function empreinte(): string { return $this->empreinte; }

    private function f(int $tour, GMP $x): GMP {
        $msg = chr($tour) . str_pad(gmp_export($x, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), self::OCTETS, "\0", STR_PAD_LEFT);
        $flux = '';
        for ($i = 0; $i < self::BLOCS; $i++) $flux .= hash_hmac('sha256', pack('n', $i) . $msg, $this->kPerm, true);
        return gmp_and(gmp_import(substr($flux, 0, self::OCTETS), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), $this->masque);
    }
    public function p(GMP $x): GMP {
        $g = $x >> self::DEMI; $d = gmp_and($x, $this->masque);
        for ($t = 0; $t < self::TOURS; $t++) [$g, $d] = [$d, gmp_xor($g, $this->f($t, $d))];
        return ($g << self::DEMI) | $d;
    }
    public function pInverse(GMP $y): GMP {
        $g = $y >> self::DEMI; $d = gmp_and($y, $this->masque);
        for ($t = self::TOURS - 1; $t >= 0; $t--) [$g, $d] = [gmp_xor($d, $this->f($t, $g)), $g];
        return ($g << self::DEMI) | $d;
    }
    public function fHex(int $t, string $xHex): string { return gmp_strval($this->f($t, gmp_init($xHex, 16)), 16); }

    public function lire(string $adresse): string {
        $y = $this->p($this->analyser($adresse));
        while (gmp_cmp($y, $this->n) >= 0) $y = $this->p($y);
        return strtr(str_pad(gmp_strval($y, 29), self::LONGUEUR, '0', STR_PAD_LEFT), self::CHIFFRES29, self::ALPHABET);
    }
    public function chercher(string $texte, string $mode): string {
        $page = $this->composer($texte, $mode);
        $x = $this->pInverse(gmp_init(strtr($page, self::ALPHABET, self::CHIFFRES29), 29));
        while (gmp_cmp($x, $this->n) >= 0) $x = $this->pInverse($x);
        [$r, $p] = gmp_div_qr($x, 410); [$r, $v] = gmp_div_qr($r, 32); [$r, $s] = gmp_div_qr($r, 5); [$h, $w] = gmp_div_qr($r, 4);
        return sprintf('%s-w%d-s%d-v%d:%d', gmp_strval($h, 36), gmp_intval($w) + 1, gmp_intval($s) + 1, gmp_intval($v) + 1, gmp_intval($p) + 1);
    }

    private function analyser(string $a): GMP {
        if (!preg_match('/\A([^-]*)-w([^-]*)-s([^-]*)-v([^:]*)(?::(.*))?\z/s', $a, $m, PREG_UNMATCHED_AS_NULL))
            throw new ErreurMoteur([['code' => 'ERR_INVALID_ADDRESS_SYNTAX', 'champ' => 'adresse']]);
        $e = []; $val = [];
        $hex = $m[1];
        if ($hex === '') $e[] = ['code' => 'ERR_INVALID_ADDRESS_SYNTAX', 'champ' => 'hexagone'];
        elseif (!preg_match('/\A[0-9a-z]+\z/', $hex)) $e[] = ['code' => 'ERR_INVALID_HEX_CHARS', 'champ' => 'hexagone'];
        elseif (strlen($hex) > 1 && $hex[0] === '0') $e[] = ['code' => 'ERR_INVALID_ADDRESS_SYNTAX', 'champ' => 'hexagone'];
        elseif (strlen($hex) > 3004) $e[] = ['code' => 'ERR_OUT_OF_BOUNDS', 'champ' => 'hexagone'];
        else { $h = gmp_init($hex, 36); if (gmp_cmp($h, $this->hexMax) > 0) $e[] = ['code' => 'ERR_OUT_OF_BOUNDS', 'champ' => 'hexagone']; }
        $champs = [['mur', $m[2], 4], ['etagere', $m[3], 5], ['volume', $m[4], 32], ['page', $m[5] ?? null, 410]];
        foreach ($champs as [$nom, $brut, $max]) {
            if ($brut === null || !preg_match('/\A(0|[1-9][0-9]*)\z/', $brut)) $e[] = ['code' => 'ERR_INVALID_ADDRESS_SYNTAX', 'champ' => $nom];
            elseif (strlen($brut) > 3 || (int) $brut < 1 || (int) $brut > $max) $e[] = ['code' => 'ERR_OUT_OF_BOUNDS', 'champ' => $nom];
            else $val[$nom] = (int) $brut;
        }
        if ($e) throw new ErreurMoteur($e);
        $num = ((($h * 4 + ($val['mur'] - 1)) * 5 + ($val['etagere'] - 1)) * 32 + ($val['volume'] - 1)) * 410 + ($val['page'] - 1);
        if (gmp_cmp($num, $this->n) >= 0) throw new ErreurMoteur([['code' => 'ERR_OUT_OF_BOUNDS', 'champ' => 'adresse']]);
        return $num;
    }

    public function composer(string $t, string $mode): string {
        $err = fn(string $c, string $ch) => new ErreurMoteur([['code' => $c, 'champ' => $ch]]);
        if (!in_array($mode, ['exact', 'bruit', 'page'], true)) throw $err('ERR_UNKNOWN_MODE', 'mode');
        if ($t === '') throw $err('ERR_EMPTY_QUERY', 'texte');
        if (strspn($t, self::ALPHABET) !== strlen($t)) throw $err('ERR_INVALID_ALPHABET', 'texte');
        if ($mode === 'page') { if (strlen($t) !== self::LONGUEUR) throw $err('ERR_INVALID_PAGE_LENGTH', 'texte'); return $t; }
        if (strlen($t) > self::LONGUEUR) throw $err('ERR_TEXT_TOO_LONG', 'texte');
        if ($mode === 'exact') return str_pad($t, self::LONGUEUR, ' ');
        [$pos, $r] = $this->bruit($t);
        return substr($r, 0, $pos) . $t . substr($r, $pos);
    }
    public function bruit(string $t): array {
        $tampon = ''; $bloc = 0; $lu = 0;
        $octets = function (int $n) use (&$tampon, &$bloc, &$lu, $t): string {
            while (strlen($tampon) - $lu < $n) $tampon .= hash_hmac('sha256', pack('N', $bloc++) . $t, $this->kBruit, true);
            $s = substr($tampon, $lu, $n); $lu += $n; return $s;
        };
        $places = self::LONGUEUR - strlen($t) + 1; $limite = 65536 - 65536 % $places;
        do { $u = unpack('n', $octets(2))[1]; } while ($u >= $limite);
        $pos = $u % $places; $r = ''; $besoin = self::LONGUEUR - strlen($t);
        while (strlen($r) < $besoin) { $b = ord($octets(1)); if ($b < 232) $r .= self::ALPHABET[$b % 29]; }
        return [$pos, $r];
    }
}

$v = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$ok = 0; $ko = 0;
$verif = function (bool $c, string $quoi) use (&$ok, &$ko) { if ($c) $ok++; else { $ko++; echo "ECHEC $quoi\n"; } };
foreach ($v['cles'] as $c) {
    $m = new Moteur(base64_decode(substr($c['babel_key'], 7), true));
    $verif($m->empreinte() === $c['empreinte'], 'empreinte ' . $c['nom']);
    foreach ($c['lire'] as $l) {
        $verif($m->lire($l['adresse']) === $l['page'], 'lire ' . substr($l['adresse'], 0, 20));
        $verif($m->chercher($l['page'], 'page') === $l['adresse'], 'retour ' . substr($l['adresse'], 0, 20));
    }
    foreach ($c['chercher'] as $l) {
        $a = $m->chercher($l['texte'], $l['mode']);
        $verif($a === $l['adresse'], "chercher {$l['mode']} " . substr($l['texte'], 0, 20));
        $verif(str_contains($m->lire($a), $l['texte']), 'contient');
    }
    foreach ($c['interne'] as $i) {
        if (isset($i['f_hex'])) $verif($m->fHex($i['tour'], $i['x_hex']) === $i['f_hex'], 'F');
        elseif (isset($i['p_hex'])) { $verif(gmp_strval($m->p(gmp_init($i['x_hex'], 16)), 16) === $i['p_hex'], 'P');
            $verif(gmp_strval($m->pInverse(gmp_init($i['p_hex'], 16)), 16) === $i['x_hex'], 'Pinv'); }
        else { [$pos, $r] = $m->bruit($i['bruit_texte']); $verif($pos === $i['position'] && hash('sha256', $r) === $i['remplissage_sha256'], 'bruit'); }
    }
}
$m = new Moteur(str_repeat("\x07", 32));
foreach ($v['erreurs']['lire'] as $e) {
    try { $m->lire($e['adresse']); $verif(false, 'accepte ' . json_encode(substr($e['adresse'], 0, 20))); }
    catch (ErreurMoteur $x) { $verif($x->erreurs === $e['erreurs'], 'erreurs ' . json_encode(substr($e['adresse'], 0, 20))); }
}
foreach ($v['erreurs']['chercher'] as $e) {
    try { $m->chercher($e['texte'], $e['mode']); $verif(false, 'accepte recherche'); }
    catch (ErreurMoteur $x) { $verif($x->erreurs === $e['erreurs'], 'erreurs recherche ' . $e['mode']); }
}
echo "$ok vérifications réussies, $ko échecs\n";

// temps
$m = new Moteur(random_bytes(32));
$t = []; $adresses = [];
for ($i = 0; $i < 300; $i++) {
    $texte = ''; for ($j = 0; $j < 3200; $j++) $texte .= Moteur::ALPHABET[random_int(0, 28)];
    $t0 = hrtime(true); $a = $m->chercher($texte, 'page'); $t1 = hrtime(true); $p = $m->lire($a); $t2 = hrtime(true);
    if ($p !== $texte) { echo "ALLER-RETOUR FAUX\n"; exit(1); }
    $t['chercher'][] = ($t1 - $t0) / 1e6; $t['lire'][] = ($t2 - $t1) / 1e6;
}
foreach ($t as $nom => $d) { sort($d); printf("%s : médiane %.2f ms, p95 %.2f ms, max %.2f ms\n", $nom, $d[150], $d[285], $d[299]); }
$t0 = hrtime(true); new Moteur(random_bytes(32)); printf("construction : %.3f ms\n", (hrtime(true) - $t0) / 1e6);
