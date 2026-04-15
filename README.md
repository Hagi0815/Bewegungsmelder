# MotionDetectorControl – IP-Symcon Modul

**Autor:** Christian Hagedorn  
**GUID:** `{A7D3E2F1-4B5C-6D7E-8F9A-0B1C2D3E4F5A}`  
**IPS-Version:** 8.0+  
**Prefix:** `MDC`

---

## Beschreibung

Das Modul **MotionDetectorControl** überwacht bis zu **drei Bewegungsmelder** und schaltet bei erkannter Bewegung eine konfigurierbare Variable ein. Nach Ablauf einer einstellbaren Nachlaufzeit wird die Variable wieder ausgeschaltet. Wird erneut Bewegung erkannt bevor der Timer abläuft, wird die Nachlaufzeit automatisch verlängert.

Das Modul unterstützt zwei Schaltpunkt-Modi (Zeitplan und Tag/Nacht), eine Manuell-EIN Funktion sowie eine Aktiv/Deaktiviert-Variable.

---

## Automatisch erstellte Variablen

| Variable | Typ | Beschreibung |
|---|---|---|
| `Aktiv` | Boolean | Aktiviert/deaktiviert das Modul. Wird beim Anlegen auf `true` gesetzt. |
| `Restlaufzeit` | Integer | Zeigt die verbleibende Zeit bis zum Ausschalten an. Einheit je nach Konfiguration (Sek./Min./Std.). |

---

## Konfiguration

### Manuell EIN
| Parameter | Beschreibung |
|---|---|
| Manuell EIN Variable | Boolean-Variable die das Modul manuell übersteuert. Bei `true`: Modul deaktivieren + Licht einschalten. Bei `false`: Modul aktivieren + Licht ausschalten. |

### Bewegungsmelder
| Parameter | Beschreibung |
|---|---|
| Bewegungsmelder 1–3 | Bis zu 3 Variablen (Boolean/Integer/Float). Auslösung bei `true`, Wert > 0. |

### Einschaltdauer
| Parameter | Beschreibung |
|---|---|
| Dauer | Zahlenwert (1–9999) |
| Einheit | Sekunden / Minuten / Stunden |

### Einschalten
| Parameter | Beschreibung |
|---|---|
| Variable zum Einschalten | Variable die bei Bewegung geschaltet wird |
| Variablentyp | Boolean / Float / Integer / String |
| Standard Einschaltwert | Wert wenn kein Schaltpunkt (Zeitplan/Tag/Nacht) greift |

### Ausschalten
| Parameter | Beschreibung |
|---|---|
| Variable zum Ausschalten | Variable die nach Ablauf der Zeit geschaltet wird |
| Variablentyp | Boolean / Float / Integer / String |
| Ausschaltwert | Wert beim Ausschalten |
| Variable bei keine Bewegung (Zeitplan) | Optionale separate Variable für den "keine Bewegung"-Wert aus dem Schaltplan |

---

## Schaltpunkte

### Modus: Zeitplan (Uhrzeit)

Definiert Zeitfenster mit unterschiedlichen Ein- und Ausschaltwerten.

| Spalte | Beschreibung |
|---|---|
| Von | Startzeit im Format `HH:MM` |
| Bis | Endzeit im Format `HH:MM` (Mitternachts-Überlauf wird unterstützt, z.B. `22:00`–`06:00`) |
| Wert bei Bewegung | Wert der beim Einschalten gesetzt wird (Dropdown bei String-Profil) |
| Wert bei keine Bewegung | Wert der beim Ausschalten gesetzt wird (leer = Standard-Ausschaltwert) |

**Zeitplan-Umschalter:** Optionale Boolean-Variable die zwischen Zeitplan A (`false`) und Zeitplan B (`true`) umschaltet. Kein Umschalter = Zeitplan A ist immer aktiv.

### Modus: Tag/Nacht (Boolean)

Statt Uhrzeiten wird eine Boolean-Variable (z.B. `isDay`) verwendet um zwischen zwei Konfigurationen umzuschalten.

| Spalte | Beschreibung |
|---|---|
| Wert bei Bewegung | Wert beim Einschalten |
| Wert bei keine Bewegung | Wert beim Ausschalten |

**Tag/Nacht Variable:** `false` = Plan A aktiv, `true` = Plan B aktiv. Kein Umschalter = Plan A ist immer aktiv.

#### Aktion beim Umschalten

Im Tag/Nacht Modus kann beim Umschalten der Boolean-Variable sofort eine Aktion ausgeführt werden – unabhängig von Bewegung:

| Parameter | Beschreibung |
|---|---|
| Aktion bei Plan A | Variable + Typ + Wert der bei Boolean = `false` gesetzt wird |
| Aktion bei Plan B | Variable + Typ + Wert der bei Boolean = `true` gesetzt wird |

---

## Schaltpunkt-Logik

```
Bewegung erkannt
      │
      ├── Manuell EIN aktiv? → ignorieren
      ├── Modul deaktiviert? → ignorieren
      │
      ▼
 Aktiver Schaltpunkt vorhanden?
      │
      ├── JA  → Schaltpunkt-Wert an Einschalten-Variable senden
      └── NEIN → Standard-Einschaltwert senden
      │
      ▼
 Timer starten (Nachlaufzeit)
      │
      ▼ (nach Ablauf)
 Aktiver Schaltpunkt mit "keine Bewegung"-Wert?
      │
      ├── JA  → "keine Bewegung"-Wert an NoMotion-Variable (oder Off-Variable) senden
      └── NEIN → Standard-Ausschaltwert an Off-Variable senden
```

---

## Verfügbare Funktionen

```php
// Manuell einschalten (startet/verlängert Timer)
MDC_SwitchOn(int $InstanceID): void

// Manuell ausschalten (stoppt Timer sofort)
MDC_SwitchOff(int $InstanceID): void

// Aktiv-Variable anlegen (für bestehende Instanzen)
MDC_CreateActiveVariable(int $InstanceID): void

// Countdown-Update (intern, vom Timer aufgerufen)
MDC_UpdateCountdown(int $InstanceID): void
```

---

## Statusmeldungen

| Code | Symbol | Bedeutung |
|---|---|---|
| 102 | ✓ aktiv | Bereit – wartet auf Bewegung |
| 200 | ✗ Fehler | Einschalten-Variable nicht gesetzt |
| 201 | ✗ Fehler | Kein Bewegungsmelder konfiguriert |

---

## Debug-Ausgaben

Im **Debug**-Tab der Instanz erscheinen folgende Informationen:

| Quelle | Inhalt |
|---|---|
| `MessageSink` | Welcher Sensor ausgelöst hat, Wert, ob SwitchOn aufgerufen wird |
| `SwitchOn` | Aktiver Modus, gesetzter Wert, Timer-Dauer |
| `SwitchOff` | Schaltpunkt- oder Standard-Ausschaltwert, Ziel-Variable |
| `GetScheduleEntry` | Aktives Zeitfenster, EIN- und AUS-Wert |
| `GetDayNightEntry` | Aktiver Plan (A/B), EIN- und AUS-Wert |
| `SwitchAction` | Ausgeführte Aktion beim Tag/Nacht-Umschalten |
| `ApplyChanges` | Status der Aktiv-Variable |

---

## Beispielkonfigurationen

### Beispiel 1: Flur-Licht mit Zeitplan

**Zeitplan A** (kein Umschalter):

| Von | Bis | Wert bei Bewegung | Wert bei keine Bewegung |
|---|---|---|---|
| 06:00 | 22:00 | Szene "Hell" | Szene "Aus" |
| 22:00 | 06:00 | Szene "Nacht" | Szene "Aus" |

### Beispiel 2: Tag/Nacht mit isDay-Variable

**Tag/Nacht Variable:** `isDay`

| Plan | Wert bei Bewegung | Wert bei keine Bewegung |
|---|---|---|
| A (Nacht) | 10% Helligkeit | 0% |
| B (Tag) | 80% Helligkeit | 0% |

**Aktion beim Umschalten:**
- Plan A (Nacht): Helligkeit auf 0%
- Plan B (Tag): Helligkeit auf 30% (Grundhelligkeit)

### Beispiel 3: Manuell EIN

Variable `ManuellesLicht` auf `true` setzen:
- Modul wird deaktiviert
- Licht wird mit konfiguriertem Einschaltwert eingeschaltet
- Timer wird gestoppt → Licht bleibt dauerhaft an

Variable wieder auf `false`:
- Modul wird aktiviert
- Licht wird ausgeschaltet
- Normaler Bewegungsbetrieb

---

## Installation

1. Ordner `MotionDetectorControl` nach `modules/Bewegungsmelder/` in IP-Symcon kopieren
2. In der IPS-Verwaltungskonsole: **Modulverwaltung → + → URL:** `https://github.com/Hagi0815/Bewegungsmelder`
3. Instanz anlegen: Objektbaum → `+` → Instanz → **MotionDetectorControl**
4. Konfiguration speichern – die Variablen `Aktiv` und `Restlaufzeit` werden automatisch angelegt
