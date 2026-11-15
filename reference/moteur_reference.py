#!/usr/bin/env python3
"""Moteur de la Bibliothèque de Babel : implémentation de référence (Python 3, sans dépendance).

Elle sert à deux choses : lever toute ambiguïté du cahier des charges, et produire les vecteurs
de test que le moteur PHP doit reproduire à l'octet près. Elle n'est pas optimisée.

    python3 moteur_reference.py vecteurs > vecteurs.json   # régénère les vecteurs
    python3 moteur_reference.py verifier                    # propriétés, sur 200 tirages
"""
import base64, hashlib, hmac, json, re, secrets, sys, time

sys.set_int_max_str_digits(0)                                   # N a 4 680 chiffres décimaux

ALPHABET = "abcdefghijklmnopqrstuvwxyz ,."
LONGUEUR_PAGE = 3200
MURS, ETAGERES, VOLUMES, PAGES = 4, 5, 32, 410
PAGES_PAR_HEXAGONE = MURS * ETAGERES * VOLUMES * PAGES          # 262 400
N = 29 ** LONGUEUR_PAGE                                         # nombre de pages
BITS = N.bit_length()                                           # 15 546
DEMI = BITS // 2                                                # 7 773
OCTETS_DEMI = (DEMI + 7) // 8                                   # 972
MASQUE = (1 << DEMI) - 1
TOURS = 8
BLOCS = (OCTETS_DEMI + 31) // 32                                # 31
HEXAGONE_MAX = (N - 1) // PAGES_PAR_HEXAGONE
CHIFFRES36 = "0123456789abcdefghijklmnopqrstuvwxyz"


class ErreurMoteur(Exception):
    """Porte une liste d'erreurs {code, champ}."""
    def __init__(self, erreurs):
        self.erreurs = erreurs
        super().__init__(", ".join(f"{c}({ch})" for c, ch in erreurs))


def _hmac(cle, message):
    return hmac.new(cle, message, hashlib.sha256).digest()


def _base36(x):
    if x == 0:
        return "0"
    s = []
    while x:
        x, d = divmod(x, 36)
        s.append(CHIFFRES36[d])
    return "".join(reversed(s))


class Moteur:
    def __init__(self, cle: bytes):
        if not isinstance(cle, (bytes, bytearray)) or len(cle) != 32:
            raise ValueError("la clé fait exactement 32 octets")
        self._k_perm = _hmac(cle, b"babel-moteur/v1/permutation")
        self._k_bruit = _hmac(cle, b"babel-moteur/v1/bruit")
        self._empreinte = _hmac(cle, b"babel-moteur/v1/empreinte").hex()

    @staticmethod
    def depuis_env(valeur: str) -> "Moteur":
        if not valeur.startswith("base64:"):
            raise ValueError("BABEL_KEY doit commencer par base64:")
        return Moteur(base64.b64decode(valeur[7:], validate=True))

    # ---------- permutation ----------
    def _f(self, tour, x):
        msg = bytes([tour]) + x.to_bytes(OCTETS_DEMI, "big")
        flux = b"".join(_hmac(self._k_perm, i.to_bytes(2, "big") + msg) for i in range(BLOCS))
        return int.from_bytes(flux[:OCTETS_DEMI], "big") & MASQUE

    def _p(self, x):
        g, d = x >> DEMI, x & MASQUE
        for t in range(TOURS):
            g, d = d, g ^ self._f(t, d)
        return (g << DEMI) | d

    def _p_inverse(self, y):
        g, d = y >> DEMI, y & MASQUE
        for t in reversed(range(TOURS)):
            g, d = d ^ self._f(t, g), g
        return (g << DEMI) | d

    def _vers_contenu(self, numero):            # adresse -> page, avec cycle-walking
        y, k = self._p(numero), 1
        while y >= N:
            y, k = self._p(y), k + 1
        return y, k

    def _vers_numero(self, contenu):            # page -> adresse
        x, k = self._p_inverse(contenu), 1
        while x >= N:
            x, k = self._p_inverse(x), k + 1
        return x, k

    # ---------- pages ----------
    @staticmethod
    def _texte(contenu):
        s = []
        for _ in range(LONGUEUR_PAGE):
            contenu, d = divmod(contenu, 29)
            s.append(ALPHABET[d])
        return "".join(reversed(s))

    @staticmethod
    def _contenu(texte):
        c = 0
        for ch in texte:
            c = c * 29 + ALPHABET.index(ch)
        return c

    # ---------- adresses ----------
    @staticmethod
    def analyser(adresse: str, page_obligatoire=True):
        """Renvoie le numéro de la page (ou du premier numéro du livre). Lève ErreurMoteur avec toutes les erreurs."""
        m = re.fullmatch(r"([^-]*)-w([^-]*)-s([^-]*)-v([^:]*)(?::(.*))?", adresse, flags=re.S)
        if not m:
            raise ErreurMoteur([("ERR_INVALID_ADDRESS_SYNTAX", "adresse")])
        hexa, mur, etagere, volume, page = m.groups()
        erreurs, valeurs = [], {}
        if hexa == "":
            erreurs.append(("ERR_INVALID_ADDRESS_SYNTAX", "hexagone"))
        elif not re.fullmatch(r"[0-9a-z]+", hexa):
            erreurs.append(("ERR_INVALID_HEX_CHARS", "hexagone"))
        elif len(hexa) > 1 and hexa[0] == "0":
            erreurs.append(("ERR_INVALID_ADDRESS_SYNTAX", "hexagone"))
        elif len(hexa) > 3004:
            erreurs.append(("ERR_OUT_OF_BOUNDS", "hexagone"))
        else:
            valeurs["hexagone"] = int(hexa, 36)
            if valeurs["hexagone"] > HEXAGONE_MAX:
                erreurs.append(("ERR_OUT_OF_BOUNDS", "hexagone"))
        champs = [("mur", mur, MURS), ("etagere", etagere, ETAGERES), ("volume", volume, VOLUMES)]
        if page is None:
            if page_obligatoire:
                erreurs.append(("ERR_INVALID_ADDRESS_SYNTAX", "page"))
        else:
            champs.append(("page", page, PAGES))
        for nom, brut, maxi in champs:
            if not re.fullmatch(r"0|[1-9][0-9]*", brut):
                erreurs.append(("ERR_INVALID_ADDRESS_SYNTAX", nom))
            elif len(brut) > 3 or not 1 <= int(brut) <= maxi:
                erreurs.append(("ERR_OUT_OF_BOUNDS", nom))
            else:
                valeurs[nom] = int(brut)
        if erreurs:
            ordre = {"hexagone": 0, "mur": 1, "etagere": 2, "volume": 3, "page": 4}
            raise ErreurMoteur(sorted(erreurs, key=lambda e: ordre[e[1]]))
        numero = (((valeurs["hexagone"] * MURS + valeurs["mur"] - 1) * ETAGERES + valeurs["etagere"] - 1)
                  * VOLUMES + valeurs["volume"] - 1) * PAGES + valeurs.get("page", 1) - 1
        if numero >= N:
            raise ErreurMoteur([("ERR_OUT_OF_BOUNDS", "adresse")])
        return numero

    @staticmethod
    def ecrire(numero: int) -> str:
        r, p = divmod(numero, PAGES)
        r, v = divmod(r, VOLUMES)
        r, s = divmod(r, ETAGERES)
        h, w = divmod(r, MURS)
        return f"{_base36(h)}-w{w + 1}-s{s + 1}-v{v + 1}:{p + 1}"

    # ---------- les trois fonctions publiques ----------
    def lire(self, adresse: str) -> str:
        return self._texte(self._vers_contenu(self.analyser(adresse))[0])

    def chercher(self, texte: str, mode: str) -> str:
        return self.ecrire(self._vers_numero(self._contenu(self.composer(texte, mode)))[0])

    def empreinte(self) -> str:
        return self._empreinte

    # ---------- composition de la page cherchée ----------
    def composer(self, texte: str, mode: str) -> str:
        if mode not in ("exact", "bruit", "page"):
            raise ErreurMoteur([("ERR_UNKNOWN_MODE", "mode")])
        if texte == "":
            raise ErreurMoteur([("ERR_EMPTY_QUERY", "texte")])
        if any(c not in ALPHABET for c in texte):
            raise ErreurMoteur([("ERR_INVALID_ALPHABET", "texte")])
        if mode == "page":
            if len(texte) != LONGUEUR_PAGE:
                raise ErreurMoteur([("ERR_INVALID_PAGE_LENGTH", "texte")])
            return texte
        if len(texte) > LONGUEUR_PAGE:
            raise ErreurMoteur([("ERR_TEXT_TOO_LONG", "texte")])
        if mode == "exact":
            return texte + " " * (LONGUEUR_PAGE - len(texte))
        position, remplissage = self.bruit(texte)
        return remplissage[:position] + texte + remplissage[position:]

    def bruit(self, texte: str):
        """Position du texte et remplissage (3 200 - len caractères), dérivés de la clé et du texte."""
        message = texte.encode("ascii")
        etat = {"tampon": b"", "bloc": 0}

        def octets(n):
            while len(etat["tampon"]) < n:
                etat["tampon"] += _hmac(self._k_bruit, etat["bloc"].to_bytes(4, "big") + message)
                etat["bloc"] += 1
            pris, etat["tampon"] = etat["tampon"][:n], etat["tampon"][n:]
            return pris

        places = LONGUEUR_PAGE - len(texte) + 1
        limite = 65536 - 65536 % places
        while True:
            u = int.from_bytes(octets(2), "big")
            if u < limite:
                break
        position = u % places
        symboles = []
        while len(symboles) < LONGUEUR_PAGE - len(texte):
            b = octets(1)[0]
            if b < 232:
                symboles.append(ALPHABET[b % 29])
        return position, "".join(symboles)


# ------------------------------------------------------------------ vecteurs et vérifications
CLE_A = bytes(range(32))
CLE_B = bytes([0xFF]) * 32
sha = lambda s: hashlib.sha256(s.encode("ascii")).hexdigest()


def vecteurs():
    sortie = {"constantes": {
        "alphabet": ALPHABET, "longueur_page": LONGUEUR_PAGE, "bits": BITS, "demi": DEMI, "octets_demi": OCTETS_DEMI,
        "tours": TOURS, "blocs_par_tour": BLOCS, "pages_par_hexagone": PAGES_PAR_HEXAGONE,
        "nombre_de_pages_sha256": hashlib.sha256(str(N).encode()).hexdigest(),
        "nombre_de_pages_chiffres": len(str(N)),
        "hexagone_max_longueur": len(_base36(HEXAGONE_MAX)), "hexagone_max_sha256": sha(_base36(HEXAGONE_MAX)),
        "derniere_adresse_sha256": sha(Moteur.ecrire(N - 1)),
    }, "cles": []}
    grand = ("babel" * 601)[:3003]                       # hexagone long, sous le maximum
    for nom, cle in (("A", CLE_A), ("B", CLE_B)):
        m = Moteur(cle)
        bloc = {"nom": nom, "cle_hex": cle.hex(), "babel_key": "base64:" + base64.b64encode(cle).decode(),
                "empreinte": m.empreinte(), "lire": [], "chercher": [], "interne": []}
        adresses = ["0-w1-s1-v1:1", "0-w1-s1-v1:2", "0-w4-s5-v32:410", "1-w1-s1-v1:1", "babel-w2-s3-v17:204",
                    grand + "-w3-s2-v9:77", Moteur.ecrire(N - 1)]
        for a in adresses:
            numero = m.analyser(a)
            contenu, k = m._vers_contenu(numero)
            page = m._texte(contenu)
            bloc["lire"].append({"adresse": a, "page": page, "page_sha256": sha(page), "applications": k})
        textes = ["a", ".", "le moteur de babel fonctionne.", "ou est passe l ete",
                  "la bibliotheque est illimitee et periodique.", " " * 3200, ("abc ,." * 534)[:3200]]
        for t in textes:
            for mode in ("exact", "bruit", "page"):
                if mode == "page" and len(t) != LONGUEUR_PAGE:
                    continue
                page = m.composer(t, mode)
                numero, k = m._vers_numero(m._contenu(page))
                adresse = m.ecrire(numero)
                v = {"texte": t, "mode": mode, "adresse": adresse, "adresse_sha256": sha(adresse),
                     "page_sha256": sha(page), "applications": k}
                if mode == "bruit":
                    v["position"] = m.bruit(t)[0]
                bloc["chercher"].append(v)
        for t, x in ((0, 0), (0, 1), (7, MASQUE), (3, int.from_bytes(hashlib.sha256(b"babel").digest(), "big"))):
            bloc["interne"].append({"tour": t, "x_hex": format(x, "x"), "f_hex": format(m._f(t, x), "x")})
        for x in (0, 1, N - 1, (1 << BITS) - 1):
            bloc["interne"].append({"x_hex": format(x, "x"), "p_hex": format(m._p(x), "x")})
        m_bruit = m.bruit("a")
        bloc["interne"].append({"bruit_texte": "a", "position": m_bruit[0], "remplissage_debut": m_bruit[1][:64],
                                "remplissage_sha256": sha(m_bruit[1])})
        sortie["cles"].append(bloc)
    sortie["erreurs"] = erreurs_attendues()
    return sortie


def erreurs_attendues():
    """Cas refusés. Ils ne dépendent pas de la clé."""
    hmax = _base36(HEXAGONE_MAX)
    adresses = [
        "", "abc", "0-w1-s1-v1", "0-w1-s1-v1:", "0-W1-s1-v1:1", "0-w1-S1-v1:1", "0-w04-s1-v1:1", "0-w1-s1-v1:01",
        "ABC-w1-s1-v1:1", "a_b-w1-s1-v1:1", " 0-w1-s1-v1:1", "00-w1-s1-v1:1", "01-w1-s1-v1:1", "-w1-s1-v1:1",
        "0-w5-s1-v1:1", "0-w0-s1-v1:1", "0-w1-s6-v33:411", "ABC-w9-sx-v1:1", "0-w1-s1-v1:1\n", "0-w1-s1-v1:1 ",
        "0-w1-s1-v1:1:2", "0-w1-s1-v1-x:1", "0-w1-s1-v1:+1", "0-w1-s1-v1:1.0", "0-w1-s1-v1:1000",
        "0-w1-s1-v1:99999999999999999999", "0-w-s1-v1:1", "0-w1-s1-v\u0661:1", "0\u00e9-w1-s1-v1:1",
        _base36(HEXAGONE_MAX + 1) + "-w1-s1-v1:1", hmax + "-w1-s1-v1:2", hmax + "-w2-s1-v1:1",
        hmax + "-w4-s5-v32:410", "z" * 3005 + "-w1-s1-v1:1", "z" * 3004 + "-w1-s1-v1:1",
    ]
    recherches = [
        ("a", "exacte"), ("a", "Exact"), ("a", ""), ("", "inconnu"), ("", "exact"), ("", "bruit"), ("", "page"),
        ("A", "exact"), ("\u00e9t\u00e9", "exact"), ("a\n", "bruit"), ("a;b", "exact"), ("a" * 3200 + "A", "exact"),
        ("a" * 3201, "exact"), ("a" * 3201, "bruit"), ("a" * 3199, "page"), ("a" * 3201, "page"), ("a", "page"),
    ]
    sortie = {"lire": [], "chercher": []}
    m = Moteur(CLE_A)
    for a in adresses:
        try:
            m.lire(a)
            raise AssertionError("adresse acceptée : " + a[:40])
        except ErreurMoteur as e:
            sortie["lire"].append({"adresse": a, "erreurs": [{"code": c, "champ": ch} for c, ch in e.erreurs]})
    for t, mode in recherches:
        try:
            m.chercher(t, mode)
            raise AssertionError("recherche acceptée")
        except ErreurMoteur as e:
            sortie["chercher"].append({"texte": t, "mode": mode,
                                       "erreurs": [{"code": c, "champ": ch} for c, ch in e.erreurs]})
    return sortie


def verifier(tirages=200):
    m = Moteur(secrets.token_bytes(32))
    t0, applications = time.perf_counter(), []
    for _ in range(tirages):
        numero = secrets.randbelow(N)
        adresse = Moteur.ecrire(numero)
        assert Moteur.analyser(adresse) == numero
        page = m.lire(adresse)
        assert len(page) == LONGUEUR_PAGE and set(page) <= set(ALPHABET)
        assert m.chercher(page, "page") == adresse
        applications.append(m._vers_contenu(numero)[1])
    duree = (time.perf_counter() - t0) / tirages * 1000
    for texte in ("a", "ou est passe l ete", "x" * 3200):
        for mode in ("exact", "bruit"):
            a = m.chercher(texte, mode)
            assert a == m.chercher(texte, mode)
            page = m.lire(a)
            assert texte in page
            if mode == "exact":
                assert page == texte + " " * (LONGUEUR_PAGE - len(texte))
            else:
                assert page.find(texte) <= m.bruit(texte)[0] and page[m.bruit(texte)[0]:].startswith(texte)
    assert Moteur(CLE_A).lire("0-w1-s1-v1:1") != Moteur(CLE_B).lire("0-w1-s1-v1:1")
    assert N % PAGES_PAR_HEXAGONE == 1 and Moteur.ecrire(N - 1) == _base36(HEXAGONE_MAX) + "-w1-s1-v1:1"
    print(f"{tirages} allers-retours exacts ; {sum(applications)/len(applications):.2f} application en moyenne, "
          f"{max(applications)} au plus ; {duree:.1f} ms par aller-retour (Python) ; 2^n/N = {2**BITS / N:.4f}")
    print(f"hexagone maximal : {len(_base36(HEXAGONE_MAX))} caractères ; adresse la plus longue : "
          f"{len(_base36(HEXAGONE_MAX)) + len('-w4-s5-v32:410')} caractères au plus")


if __name__ == "__main__":
    if sys.argv[1:] == ["vecteurs"]:
        json.dump(vecteurs(), sys.stdout, ensure_ascii=False, indent=1)
    else:
        verifier()
