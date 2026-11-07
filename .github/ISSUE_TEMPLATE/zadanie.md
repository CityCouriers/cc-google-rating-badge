---
name: Zadanie lub znalezisko
about: Zmiana kodu wtyczki, CI, dokumentacji albo wydania; także znalezisko z audytu narzędziami
labels: needs-triage
---

<!--
Zasady: README.md (sekcja Proces) i .github/LABELS.md.
- Etykiety P, T i area nadaje autor po triage; needs-triage zdejmujemy, gdy komplet jest nadany.
- by:agent nadaje wyłącznie agent lokalny swoim zgłoszeniom. audit:* nadaje tylko workflow issue-audit.
- To repozytorium jest PUBLICZNE: bez kluczy API, tokenów, danych klientów, danych osobowych
  i surowych logów. Klucz Google wklejony do zgłoszenia uznaj za ujawniony i unieważnij go.
- Zgłoszenie zakładane z CLI: treść z pliku UTF-8 (--body-file); ten szablon wtedy się nie wczytuje.
-->

**Refs:** #nr (zgłoszenie nadrzędne, zależności) albo `brak`
**Rola autora:** Claude Code (agent lokalny) / ChatGPT / właściciel
**Rola recenzenta (audyt specyfikacji):** strona, która nie pisała tej treści

## Cel

Co ma być prawdą po zamknięciu zgłoszenia?

## Kontekst i ustalenia

Fakty z pomiarem i źródłem (komenda, wynik, plik i linia). Hipotezy oznacz jako hipotezy.

## Zakres

- [ ] 

## Poza zakresem

Co celowo zostaje dla innych zgłoszeń (numery).

## Ryzyko i wycofanie

Co się stanie, gdy zmiana będzie błędna; jak ją wycofać. Podstawa do `T1`-`T3`.

## Co zamyka

Weryfikowalne kryteria z dowodem (komenda i wynik, test pozytywny i negatywny).

## Triage

| pole | wartość |
| --- | --- |
| priorytet | P0-P4 |
| ryzyko | T1-T3 |
| obszar | area:... |

Podpis: zastąp tę linię jedną z dwóch (zawsze ostatnia linia treści): `_— Claude Code (agent lokalny)_` albo `_— ChatGPT_`
