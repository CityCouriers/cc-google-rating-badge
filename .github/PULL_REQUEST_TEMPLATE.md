<!--
Tytuł: Conventional Commits (feat: / fix: / docs: / chore: / ci: / test: / refactor:), temat małą literą.
Pola, które nie dotyczą zmiany: „N/A (powód)”. Nie zostawiaj placeholderów.
Check `pr-title` pilnuje tytułu; `quality-gate` zbiera wyniki PHPCS, PHPStan, PHPCompatibility, testów i kontroli bezpieczeństwa.
-->

## Kontekst

- **Dlaczego:** jedno zdanie o wartości tej zmiany.
- **Problem:** co dziś jest źle albo czego brakuje.
- **Rozwiązanie:** co się zmieniło i jak to rozwiązuje problem.
- **Szczegóły (opcjonalnie):** ryzyka, przypadki brzegowe, decyzje.

## Dowody

<!-- Odhacz z krótkim potwierdzeniem albo N/A z powodem. -->

- [ ] **Testy:** `composer test` zielony; nowe zachowanie ma test pozytywny i negatywny
- [ ] **Jakość:** `composer phpcs`, `composer phpstan` i `phpcs --standard=phpcompat.xml.dist` zielone; żaden wyjątek nie przybył (usunięte wyjątki wymień niżej)
- [ ] **Bezpieczeństwo:** brak kluczy, danych klientów i identyfikatorów wewnętrznych; wejście sanityzowane, wyjście escapowane, albo N/A
- [ ] **Wydanie:** wersja w nagłówku wtyczki i w `enqueue_style` zgodna z zamierzonym wydaniem, albo N/A
- [ ] **Audyt:** zgłoszenie ma werdykt `audit:ok` strony przeciwnej autorowi treści (bramka `issue-audit`), albo N/A (powód)

**Usunięte wyjątki z `phpcs.xml.dist` / `phpstan.neon.dist`:** brak, albo lista.

## Linki

Fixes: #`<nr>` albo Refs: #`<nr>`

## Przed merge

- [ ] Tytuł zgodny z konwencją
- [ ] `quality-gate` i `pr-title` zielone
- [ ] Follow-upy wypisane wyżej albo `brak`
