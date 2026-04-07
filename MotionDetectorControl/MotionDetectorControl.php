<?php

declare(strict_types=1);

class MotionDetectorControl extends IPSModule
{
    // Timer-ID Konstante
    private const TIMER_SWITCH_OFF = 'SwitchOffTimer';

    public function Create(): void
    {
        parent::Create();

        // ── Bewegungsmelder (bis zu 3) ──────────────────────────────────────
        $this->RegisterPropertyInteger('MotionSensor1', 0);
        $this->RegisterPropertyInteger('MotionSensor2', 0);
        $this->RegisterPropertyInteger('MotionSensor3', 0);

        // ── Einschaltdauer ──────────────────────────────────────────────────
        // Einheit: 0 = Sekunden, 1 = Minuten, 2 = Stunden
        $this->RegisterPropertyInteger('DurationValue', 30);
        $this->RegisterPropertyInteger('DurationUnit', 0);

        // ── Ziel-Variable ───────────────────────────────────────────────────
        // Typ: 0 = Boolean, 1 = Float, 2 = Integer, 3 = String
        $this->RegisterPropertyInteger('TargetVariable', 0);
        $this->RegisterPropertyInteger('TargetVariableType', 0);

        // Werte zum Einschalten / Ausschalten je nach Typ
        $this->RegisterPropertyBoolean('OnValueBool',   true);
        $this->RegisterPropertyBoolean('OffValueBool',  false);
        $this->RegisterPropertyFloat('OnValueFloat',    1.0);
        $this->RegisterPropertyFloat('OffValueFloat',   0.0);
        $this->RegisterPropertyInteger('OnValueInt',    1);
        $this->RegisterPropertyInteger('OffValueInt',   0);
        $this->RegisterPropertyString('OnValueString',  'EIN');
        $this->RegisterPropertyString('OffValueString', 'AUS');

        // ── Interner Status ─────────────────────────────────────────────────
        $this->RegisterAttributeBoolean('IsActive', false);

        // ── Timer ───────────────────────────────────────────────────────────
        $this->RegisterTimer(self::TIMER_SWITCH_OFF, 0, 'MDC_SwitchOff($id);');
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Alte Registrierungen aufräumen, dann neu registrieren
        $this->UnregisterAllMessages();

        $sensorIDs = $this->GetConfiguredSensorIDs();
        foreach ($sensorIDs as $sensorID) {
            $this->RegisterMessage($sensorID, VM_UPDATE);
        }

        $this->ValidateConfiguration();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Nachrichten-Handler
    // ────────────────────────────────────────────────────────────────────────

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message !== VM_UPDATE) {
            return;
        }

        // Prüfen, ob der Sender einer unserer konfigurierten Sensoren ist
        if (!in_array($SenderID, $this->GetConfiguredSensorIDs(), true)) {
            return;
        }

        // Nur bei Bewegungserkennung (Wert = true / > 0) reagieren
        $value = GetValue($SenderID);
        if ($this->IsTriggerActive($value)) {
            $this->OnMotionDetected();
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Öffentliche Methoden (per RequestAction / direkt aufrufbar)
    // ────────────────────────────────────────────────────────────────────────

    /** Wird vom Timer aufgerufen – schaltet die Ziel-Variable aus */
    public function SwitchOff(): void
    {
        $this->SetTimer(0);
        $this->WriteTargetValue(false);
        $this->WriteAttributeBoolean('IsActive', false);
        $this->SetStatus(102);
        $this->SendDebug('SwitchOff', 'Ziel-Variable ausgeschaltet', 0);
    }

    /** Manuelles Einschalten (z. B. aus WebFront-Button) */
    public function SwitchOn(): void
    {
        $this->OnMotionDetected();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Konfigurations-UI
    // ────────────────────────────────────────────────────────────────────────

    public function GetConfigurationForm(): string
    {
        $form = [
            'elements' => [
                // ── Bewegungsmelder ──────────────────────────────────────
                [
                    'type'    => 'Label',
                    'caption' => 'Bewegungsmelder (bis zu 3)',
                ],
                [
                    'type'    => 'SelectVariable',
                    'name'    => 'MotionSensor1',
                    'caption' => 'Bewegungsmelder 1',
                    'validVariableType' => [0, 1, 2],  // Bool, Int, Float
                ],
                [
                    'type'    => 'SelectVariable',
                    'name'    => 'MotionSensor2',
                    'caption' => 'Bewegungsmelder 2 (optional)',
                    'validVariableType' => [0, 1, 2],
                ],
                [
                    'type'    => 'SelectVariable',
                    'name'    => 'MotionSensor3',
                    'caption' => 'Bewegungsmelder 3 (optional)',
                    'validVariableType' => [0, 1, 2],
                ],

                // ── Trennlinie ───────────────────────────────────────────
                ['type' => 'Label', 'caption' => ''],

                // ── Einschaltdauer ───────────────────────────────────────
                [
                    'type'    => 'Label',
                    'caption' => 'Einschaltdauer',
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'NumberSpinner',
                            'name'    => 'DurationValue',
                            'caption' => 'Dauer',
                            'minimum' => 1,
                            'maximum' => 9999,
                            'suffix'  => '',
                        ],
                        [
                            'type'    => 'Select',
                            'name'    => 'DurationUnit',
                            'caption' => 'Einheit',
                            'options' => [
                                ['caption' => 'Sekunden', 'value' => 0],
                                ['caption' => 'Minuten',  'value' => 1],
                                ['caption' => 'Stunden',  'value' => 2],
                            ],
                        ],
                    ],
                ],

                // ── Trennlinie ───────────────────────────────────────────
                ['type' => 'Label', 'caption' => ''],

                // ── Ziel-Variable ────────────────────────────────────────
                [
                    'type'    => 'Label',
                    'caption' => 'Ziel-Variable',
                ],
                [
                    'type'    => 'SelectVariable',
                    'name'    => 'TargetVariable',
                    'caption' => 'Variable zum Schalten',
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'TargetVariableType',
                    'caption' => 'Variablentyp',
                    'options' => [
                        ['caption' => 'Boolean', 'value' => 0],
                        ['caption' => 'Float',   'value' => 1],
                        ['caption' => 'Integer', 'value' => 2],
                        ['caption' => 'String',  'value' => 3],
                    ],
                ],

                // ── EIN-Werte ────────────────────────────────────────────
                [
                    'type'    => 'Label',
                    'caption' => 'Einschaltwert (bei Bewegung erkannt)',
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'OnValueBool',
                            'caption' => 'Boolean EIN',
                        ],
                        [
                            'type'    => 'NumberSpinner',
                            'name'    => 'OnValueFloat',
                            'caption' => 'Float EIN',
                            'digits'  => 2,
                        ],
                        [
                            'type'    => 'NumberSpinner',
                            'name'    => 'OnValueInt',
                            'caption' => 'Integer EIN',
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'OnValueString',
                            'caption' => 'String EIN',
                        ],
                    ],
                ],

                // ── AUS-Werte ────────────────────────────────────────────
                [
                    'type'    => 'Label',
                    'caption' => 'Ausschaltwert (nach Ablauf der Zeit)',
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'OffValueBool',
                            'caption' => 'Boolean AUS',
                        ],
                        [
                            'type'    => 'NumberSpinner',
                            'name'    => 'OffValueFloat',
                            'caption' => 'Float AUS',
                            'digits'  => 2,
                        ],
                        [
                            'type'    => 'NumberSpinner',
                            'name'    => 'OffValueInt',
                            'caption' => 'Integer AUS',
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'OffValueString',
                            'caption' => 'String AUS',
                        ],
                    ],
                ],
            ],

            'actions' => [
                [
                    'type'    => 'Label',
                    'caption' => 'Test',
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Einschalten (Test)',
                    'onClick' => 'MDC_SwitchOn($id);',
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Ausschalten (Test)',
                    'onClick' => 'MDC_SwitchOff($id);',
                ],
            ],

            'status' => [
                ['code' => 101, 'icon' => 'inactive', 'caption' => 'Instanz wird erstellt'],
                ['code' => 102, 'icon' => 'active',   'caption' => 'Bereit – wartet auf Bewegung'],
                ['code' => 103, 'icon' => 'active',   'caption' => 'Aktiv – Ziel eingeschaltet'],
                ['code' => 200, 'icon' => 'error',    'caption' => 'Fehler: Ungültige Konfiguration'],
                ['code' => 201, 'icon' => 'error',    'caption' => 'Fehler: Ziel-Variable nicht gesetzt'],
                ['code' => 202, 'icon' => 'error',    'caption' => 'Fehler: Kein Bewegungsmelder konfiguriert'],
            ],
        ];

        return json_encode($form);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Private Hilfsmethoden
    // ────────────────────────────────────────────────────────────────────────

    /** Bewegung erkannt → einschalten + Timer (re)starten */
    private function OnMotionDetected(): void
    {
        $targetID = $this->ReadPropertyInteger('TargetVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            $this->SendDebug('OnMotionDetected', 'Ziel-Variable ungültig', 0);
            return;
        }

        $this->WriteTargetValue(true);
        $this->WriteAttributeBoolean('IsActive', true);
        $this->SetStatus(103);

        // Timer (neu)starten – verlängert bei erneuter Bewegung automatisch
        $seconds = $this->GetDurationInSeconds();
        $this->SetTimer($seconds);

        $this->SendDebug(
            'OnMotionDetected',
            sprintf('Eingeschaltet, Timer auf %d Sekunden gesetzt', $seconds),
            0
        );
    }

    /** Schreibt den Ein- oder Ausschaltwert in die Ziel-Variable */
    private function WriteTargetValue(bool $on): void
    {
        $targetID = $this->ReadPropertyInteger('TargetVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            return;
        }

        $type = $this->ReadPropertyInteger('TargetVariableType');

        switch ($type) {
            case 0: // Boolean
                $value = $on
                    ? $this->ReadPropertyBoolean('OnValueBool')
                    : $this->ReadPropertyBoolean('OffValueBool');
                SetValueBoolean($targetID, $value);
                break;

            case 1: // Float
                $value = $on
                    ? $this->ReadPropertyFloat('OnValueFloat')
                    : $this->ReadPropertyFloat('OffValueFloat');
                SetValueFloat($targetID, $value);
                break;

            case 2: // Integer
                $value = $on
                    ? $this->ReadPropertyInteger('OnValueInt')
                    : $this->ReadPropertyInteger('OffValueInt');
                SetValueInteger($targetID, $value);
                break;

            case 3: // String
                $value = $on
                    ? $this->ReadPropertyString('OnValueString')
                    : $this->ReadPropertyString('OffValueString');
                SetValueString($targetID, $value);
                break;
        }
    }

    /** Gibt die konfigurierte Dauer in Sekunden zurück */
    private function GetDurationInSeconds(): int
    {
        $value = $this->ReadPropertyInteger('DurationValue');
        $unit  = $this->ReadPropertyInteger('DurationUnit');

        switch ($unit) {
            case 1:
                return $value * 60;
            case 2:
                return $value * 3600;
            default:
                return $value;
        }
    }

    /** Liefert alle konfigurierten, gültigen Sensor-IDs */
    private function GetConfiguredSensorIDs(): array
    {
        $ids = [];
        foreach ([1, 2, 3] as $n) {
            $id = $this->ReadPropertyInteger("MotionSensor{$n}");
            if ($id > 0 && IPS_VariableExists($id)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Bestimmt anhand des Variablentyps, ob der Sensor „ausgelöst" hat.
     * Boolean: true
     * Integer / Float: > 0
     * String: nicht leer
     */
    private function IsTriggerActive($value): bool
    {
        if (is_bool($value)) {
            return $value === true;
        }
        if (is_int($value) || is_float($value)) {
            return $value > 0;
        }
        if (is_string($value)) {
            return $value !== '';
        }
        return false;
    }

    /** Setzt den Ausschalt-Timer auf $seconds (0 = deaktivieren) */
    private function SetTimer(int $seconds): void
    {
        $this->SetTimerInterval(self::TIMER_SWITCH_OFF, $seconds * 1000);
    }

    /** Konfiguration prüfen und Status setzen */
    private function ValidateConfiguration(): void
    {
        $targetID = $this->ReadPropertyInteger('TargetVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            $this->SetStatus(201);
            return;
        }

        if (count($this->GetConfiguredSensorIDs()) === 0) {
            $this->SetStatus(202);
            return;
        }

        $this->SetStatus(102);
    }
}
