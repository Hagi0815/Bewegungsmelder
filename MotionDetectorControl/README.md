# MotionDetectorControl – IP-Symcon Modul

**Autor:** Christian Hagedorn  
**GUID:** `{A7D3E2F1-4B5C-6D7E-8F9A-0B1C2D3E4F5A}`  
**IPS-Version:** 7.0 / 8.0+

---

## Beschreibung

Das Modul **MotionDetectorControl** überwacht bis zu **drei Bewegungsmelder** und schaltet bei erkannter Bewegung eine frei wählbare Variable für eine konfigurierbare Zeit ein. Wird erneut Bewegung erkannt, bevor die Zeit abläuft, wird der Timer automatisch verlängert (Nachlaufzeit-Reset).

---

## Funktionsweise

```
Bewegung erkannt
      │
      ▼
 Ziel-Variable auf "EIN-Wert" setzen
      │
      ▼
 Timer starten / neu starten (Nachlaufzeit)
      │
      ▼ (nach Ablauf der Zeit)
 Ziel-Variable auf "AUS-Wert" setzen
```

---

## Konfiguration

### Bewegungsmelder
| Parameter        | Beschreibung                                      |
|-----------------|---------------------------------------------------|
| Bewegungsmelder 1 | Variable des 1. Sensors (Bool/Int/Float)         |
| Bewegungsmelder 2 | Variable des 2. Sensors (optional)               |
| Bewegungsmelder 3 | Variable des 3. Sensors (optional)               |

### Einschaltdauer
| Parameter    | Beschreibung                          |
|-------------|---------------------------------------|
| Dauer        | Zahlenwert (1–9999)                  |
| Einheit      | Sekunden / Minuten / Stunden         |

### Ziel-Variable
| Parameter         | Beschreibung                                          |
|------------------|-------------------------------------------------------|
| Variable          | ID der zu schaltenden Variable                       |
| Variablentyp      | Boolean / Float / Integer / String                   |
| Einschaltwert     | Wert beim Einschalten (je Typ konfigurierbar)        |
| Ausschaltwert     | Wert beim Ausschalten (je Typ konfigurierbar)        |

---

## Trigger-Logik der Bewegungsmelder

| Variablentyp | Auslösung bei...    |
|-------------|---------------------|
| Boolean     | `true`              |
| Integer     | Wert > 0            |
| Float       | Wert > 0.0          |
| String      | Nicht-leerer String |

---

## Verfügbare Funktionen

```php
// Manuell einschalten (startet/verlängert Timer)
MDC_SwitchOn(int $InstanceID): void

// Manuell ausschalten (stoppt Timer sofort)
MDC_SwitchOff(int $InstanceID): void
```

---

## Statusmeldungen

| Code | Bedeutung                          |
|------|-------------------------------------|
| 102  | Bereit – wartet auf Bewegung        |
| 103  | Aktiv – Ziel-Variable eingeschaltet |
| 201  | Fehler: Ziel-Variable nicht gesetzt |
| 202  | Fehler: Kein Bewegungsmelder konfiguriert |

---

## Installation

1. Ordner `MotionDetectorControl` nach `modules/` in IP-Symcon kopieren.
2. In der IPS-Verwaltungskonsole: **Instanz hinzufügen → MotionDetectorControl**.
3. Bewegungsmelder, Dauer und Ziel-Variable konfigurieren.
4. Speichern – das Modul ist sofort aktiv.

---

## Beispiel-Konfiguration

**Licht im Flur 30 Minuten einschalten bei Bewegung:**
- Bewegungsmelder 1: Variable `HM_Bewegung_Flur` (Boolean)
- Dauer: `30` Minuten
- Ziel-Variable: `Licht_Flur` (Boolean)
- Einschaltwert Boolean: `true`
- Ausschaltwert Boolean: `false`

**Helligkeitswert auf 100% setzen:**
- Ziel-Variable: `Helligkeit_Wohnzimmer` (Float)
- Einschaltwert Float: `100.0`
- Ausschaltwert Float: `0.0`
