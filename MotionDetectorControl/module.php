<?php

declare(strict_types=1);

class MotionDetectorControl extends IPSModule
{
    public function Create(): void
    {
        parent::Create();

        // Bewegungsmelder
        $this->RegisterPropertyInteger('MotionSensor1', 0);
        $this->RegisterPropertyInteger('MotionSensor2', 0);
        $this->RegisterPropertyInteger('MotionSensor3', 0);

        // Einschaltdauer
        $this->RegisterPropertyInteger('DurationValue', 30);
        $this->RegisterPropertyInteger('DurationUnit', 0);

        // Einschalten-Variable
        $this->RegisterPropertyInteger('OnVariable', 0);
        $this->RegisterPropertyInteger('OnVariableType', 0);
        $this->RegisterPropertyBoolean('OnValueBool', true);
        $this->RegisterPropertyFloat('OnValueFloat', 1.0);
        $this->RegisterPropertyInteger('OnValueInt', 1);
        $this->RegisterPropertyString('OnValueString', 'EIN');

        // Ausschalten-Variable
        $this->RegisterPropertyInteger('OffVariable', 0);

        // Keine Bewegung Variable (optional, für Zeitplan-Wert bei keine Bewegung)
        $this->RegisterPropertyInteger('NoMotionVariable', 0);
        $this->RegisterPropertyInteger('NoMotionVariableType', 0);
        $this->RegisterPropertyInteger('OffVariableType', 0);
        $this->RegisterPropertyBoolean('OffValueBool', false);
        $this->RegisterPropertyFloat('OffValueFloat', 0.0);
        $this->RegisterPropertyInteger('OffValueInt', 0);
        $this->RegisterPropertyString('OffValueString', 'AUS');

        // Zeitplan-Variable (Boolean)
        $this->RegisterPropertyInteger('TimeScheduleVariable', 0);

        // Zeitplan A (Boolean = false oder keine Variable)
        $this->RegisterPropertyString('TimeScheduleA', '[]');
        // Zeitplan B (Boolean = true)
        $this->RegisterPropertyString('TimeScheduleB', '[]');

        $this->RegisterTimer('SwitchOffTimer', 0, 'MDC_SwitchOff(' . $this->InstanceID . ');');
        $this->RegisterTimer('CountdownTimer', 0, 'MDC_UpdateCountdown(' . $this->InstanceID . ');');

        // Profile für Restlaufzeit
        if (!IPS_VariableProfileExists('MDC.Seconds')) {
            IPS_CreateVariableProfile('MDC.Seconds', 1);
            IPS_SetVariableProfileText('MDC.Seconds', '', ' Sek.');
        }
        if (!IPS_VariableProfileExists('MDC.Minutes')) {
            IPS_CreateVariableProfile('MDC.Minutes', 1);
            IPS_SetVariableProfileText('MDC.Minutes', '', ' Min.');
        }
        if (!IPS_VariableProfileExists('MDC.Hours')) {
            IPS_CreateVariableProfile('MDC.Hours', 1);
            IPS_SetVariableProfileText('MDC.Hours', '', ' Std.');
        }

        // Statusvariable Restlaufzeit
        $this->RegisterVariableInteger('Restlaufzeit', 'Restlaufzeit', 'MDC.Seconds', 0);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->UpdateRestlaufzeitProfile();

        for ($n = 1; $n <= 3; $n++) {
            $id = $this->ReadPropertyInteger('MotionSensor' . $n);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message !== VM_UPDATE) {
            return;
        }

        $value = GetValue($SenderID);
        if (is_bool($value) && $value === true) {
            $this->SwitchOn();
        } elseif ((is_int($value) || is_float($value)) && $value > 0) {
            $this->SwitchOn();
        }
    }

    public function SwitchOn(): void
    {
        $targetID = $this->ReadPropertyInteger('OnVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            return;
        }

        $scheduleValue = $this->GetScheduleValue();
        $type = $this->ReadPropertyInteger('OnVariableType');

        if ($scheduleValue !== null) {
            if ($type === 0) {
                $this->SendValue($targetID, ($scheduleValue === 'true' || $scheduleValue === '1'), $type);
            } else {
                $this->SendValue($targetID, $scheduleValue, $type);
            }
        } else {
            $this->SendOnValue($targetID, $type);
        }

        $value = $this->ReadPropertyInteger('DurationValue');
        $unit  = $this->ReadPropertyInteger('DurationUnit');
        switch ($unit) {
            case 1: $seconds = $value * 60; break;
            case 2: $seconds = $value * 3600; break;
            default: $seconds = $value; break;
        }
        $this->SetTimerInterval('SwitchOffTimer', $seconds * 1000);

        // Restlaufzeit initialisieren und Countdown-Timer starten (jede Sekunde)
        $this->SetValue('Restlaufzeit', $value);
        $this->SetTimerInterval('CountdownTimer', 1000);
    }

    public function SwitchOff(): void
    {
        $this->SetTimerInterval('SwitchOffTimer', 0);
        $this->SetTimerInterval('CountdownTimer', 0);
        $this->SetValue('Restlaufzeit', 0);

        // Zeitplan-Wert für "keine Bewegung" prüfen
        $scheduleOffValue = $this->GetScheduleOffValue();

        if ($scheduleOffValue !== null) {
            // Eigene NoMotion-Variable verwenden falls konfiguriert, sonst OffVariable
            $noMotionID = $this->ReadPropertyInteger('NoMotionVariable');
            if ($noMotionID > 0 && IPS_VariableExists($noMotionID)) {
                $type = $this->ReadPropertyInteger('NoMotionVariableType');
                $targetID = $noMotionID;
            } else {
                $targetID = $this->ReadPropertyInteger('OffVariable');
                if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
                    return;
                }
                $type = $this->ReadPropertyInteger('OffVariableType');
            }
            if ($type === 0) {
                $this->SendValue($targetID, ($scheduleOffValue === 'true' || $scheduleOffValue === '1'), $type);
            } else {
                $this->SendValue($targetID, $scheduleOffValue, $type);
            }
        } else {
            // Standard Ausschalten
            $targetID = $this->ReadPropertyInteger('OffVariable');
            if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
                return;
            }
            $type = $this->ReadPropertyInteger('OffVariableType');
            $this->SendOffValue($targetID, $type);
        }
    }

    public function UpdateCountdown(): void
    {
        $current = $this->GetValue('Restlaufzeit');
        if ($current <= 1) {
            $this->SetValue('Restlaufzeit', 0);
            $this->SetTimerInterval('CountdownTimer', 0);
        } else {
            $this->SetValue('Restlaufzeit', $current - 1);
        }
    }

    private function UpdateRestlaufzeitProfile(): void
    {
        $unit = $this->ReadPropertyInteger('DurationUnit');
        switch ($unit) {
            case 1:
                IPS_SetVariableCustomProfile($this->GetIDForIdent('Restlaufzeit'), 'MDC.Minutes');
                break;
            case 2:
                IPS_SetVariableCustomProfile($this->GetIDForIdent('Restlaufzeit'), 'MDC.Hours');
                break;
            default:
                IPS_SetVariableCustomProfile($this->GetIDForIdent('Restlaufzeit'), 'MDC.Seconds');
                break;
        }
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────

    private function SendValue($targetID, $value, $type): void
    {
        switch ($type) {
            case 0: RequestAction($targetID, (bool) $value); break;
            case 1: RequestAction($targetID, (float) $value); break;
            case 2: RequestAction($targetID, (int) $value); break;
            case 3: RequestAction($targetID, (string) $value); break;
        }
    }

    private function SendOnValue($targetID, $type): void
    {
        switch ($type) {
            case 0: RequestAction($targetID, (bool) $this->ReadPropertyBoolean('OnValueBool')); break;
            case 1: RequestAction($targetID, $this->ReadPropertyFloat('OnValueFloat')); break;
            case 2: RequestAction($targetID, $this->ReadPropertyInteger('OnValueInt')); break;
            case 3: RequestAction($targetID, $this->ReadPropertyString('OnValueString')); break;
        }
    }

    private function SendOffValue($targetID, $type): void
    {
        switch ($type) {
            case 0: RequestAction($targetID, (bool) $this->ReadPropertyBoolean('OffValueBool')); break;
            case 1: RequestAction($targetID, $this->ReadPropertyFloat('OffValueFloat')); break;
            case 2: RequestAction($targetID, $this->ReadPropertyInteger('OffValueInt')); break;
            case 3: RequestAction($targetID, $this->ReadPropertyString('OffValueString')); break;
        }
    }

    // Returns array ['on' => value, 'off' => value] or null if no match
    private function GetScheduleEntry()
    {
        $scheduleVarID = $this->ReadPropertyInteger('TimeScheduleVariable');
        $useScheduleB = false;
        if ($scheduleVarID > 0 && IPS_VariableExists($scheduleVarID)) {
            $useScheduleB = GetValueBoolean($scheduleVarID);
        }

        $scheduleJson = $this->ReadPropertyString($useScheduleB ? 'TimeScheduleB' : 'TimeScheduleA');
        $schedule = json_decode($scheduleJson, true);

        if (empty($schedule)) {
            return null;
        }

        $now = (int) date('H') * 60 + (int) date('i');

        foreach ($schedule as $entry) {
            if (!isset($entry['FromH']) || !isset($entry['ToH'])) {
                continue;
            }

            $from = (int) $entry['FromH'] * 60 + (int) $entry['FromM'];
            $to   = (int) $entry['ToH']   * 60 + (int) $entry['ToM'];

            $inRange = false;
            if ($from <= $to) {
                $inRange = ($now >= $from && $now < $to);
            } else {
                $inRange = ($now >= $from || $now < $to);
            }

            if ($inRange) {
                return [
                    'on'  => isset($entry['Value'])    && $entry['Value']    !== '' ? $entry['Value']    : null,
                    'off' => isset($entry['ValueOff']) && $entry['ValueOff'] !== '' ? $entry['ValueOff'] : null,
                ];
            }
        }

        return null;
    }

    private function GetScheduleValue()
    {
        $entry = $this->GetScheduleEntry();
        return $entry !== null ? $entry['on'] : null;
    }

    private function GetScheduleOffValue()
    {
        $entry = $this->GetScheduleEntry();
        return ($entry !== null && $entry['off'] !== null) ? $entry['off'] : null;
    }

    public function GetConfigurationForm(): string
    {
        $onVarID       = $this->ReadPropertyInteger('OnVariable');
        $offVarID      = $this->ReadPropertyInteger('OffVariable');
        $noMotionVarID = $this->ReadPropertyInteger('NoMotionVariable');

        $onOptions       = $this->GetProfileOptions($onVarID);
        $offOptions      = $this->GetProfileOptions($offVarID);
        $noMotionOptions = $this->GetProfileOptions($noMotionVarID);

        $noMotionType = $this->ReadPropertyInteger('NoMotionVariableType');

        $onType  = $this->ReadPropertyInteger('OnVariableType');
        $offType = $this->ReadPropertyInteger('OffVariableType');

        $scheduleValueColA    = $this->BuildValueColumn($onOptions,  $onType,  'Value',    'Wert bei Bewegung');
        $scheduleValueColB    = $this->BuildValueColumn($onOptions,  $onType,  'Value',    'Wert bei Bewegung');
        $scheduleValueOffColA = $this->BuildValueColumn($noMotionOptions, $noMotionType, 'ValueOff', 'Wert bei keine Bewegung');
        $scheduleValueOffColB = $this->BuildValueColumn($noMotionOptions, $noMotionType, 'ValueOff', 'Wert bei keine Bewegung');

        // Standard-Einschaltwert fuer String: Dropdown wenn Profil vorhanden
        $form = [
            'elements' => [

                // ── Zeile 1: Bewegungsmelder + Einschalten + Ausschalten ─
                ['type' => 'RowLayout', 'items' => [

                    ['type' => 'ExpansionPanel', 'caption' => 'Bewegungsmelder', 'expanded' => true, 'items' => [
                        ['type' => 'SelectVariable', 'name' => 'MotionSensor1', 'caption' => 'Bewegungsmelder 1', 'validVariableType' => [0, 1, 2]],
                        ['type' => 'SelectVariable', 'name' => 'MotionSensor2', 'caption' => 'Bewegungsmelder 2 (optional)', 'validVariableType' => [0, 1, 2]],
                        ['type' => 'SelectVariable', 'name' => 'MotionSensor3', 'caption' => 'Bewegungsmelder 3 (optional)', 'validVariableType' => [0, 1, 2]],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'CheckBox', 'name' => 'MotionSensor1', 'caption' => ' ', 'visible' => false],
                        ['type' => 'NumberSpinner', 'name' => 'DurationValue', 'caption' => ' ', 'visible' => false],
                        ['type' => 'NumberSpinner', 'name' => 'DurationValue', 'caption' => ' ', 'visible' => false],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'SelectVariable', 'name' => 'MotionSensor1', 'caption' => ' ', 'visible' => false],
                        ['type' => 'Select', 'name' => 'OnVariableType', 'caption' => ' ', 'visible' => false, 'options' => [['caption' => ' ', 'value' => 0]]],
                    ]],

                    ['type' => 'ExpansionPanel', 'caption' => 'Einschalten', 'expanded' => true, 'items' => [
                        ['type' => 'SelectVariable', 'name' => 'OnVariable', 'caption' => 'Variable zum Einschalten'],
                        ['type' => 'Select', 'name' => 'OnVariableType', 'caption' => 'Variablentyp', 'options' => [
                            ['caption' => 'Boolean', 'value' => 0],
                            ['caption' => 'Float',   'value' => 1],
                            ['caption' => 'Integer', 'value' => 2],
                            ['caption' => 'String',  'value' => 3],
                        ]],
                        ['type' => 'Label', 'caption' => 'Standard Einschaltwert (wenn kein Zeitplan greift)'],
                        ['type' => 'CheckBox',      'name' => 'OnValueBool',  'caption' => 'Boolean EIN'],
                        ['type' => 'NumberSpinner', 'name' => 'OnValueFloat', 'caption' => 'Float EIN', 'digits' => 2],
                        ['type' => 'NumberSpinner', 'name' => 'OnValueInt',   'caption' => 'Integer EIN'],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'SelectVariable', 'name' => 'OnVariable', 'caption' => ' ', 'visible' => false],
                        ['type' => 'Select', 'name' => 'OnVariableType', 'caption' => ' ', 'visible' => false, 'options' => [['caption' => ' ', 'value' => 0]]],
                    ]],

                    ['type' => 'ExpansionPanel', 'caption' => 'Ausschalten', 'expanded' => true, 'items' => [
                        ['type' => 'SelectVariable', 'name' => 'OffVariable', 'caption' => 'Variable zum Ausschalten'],
                        ['type' => 'Select', 'name' => 'OffVariableType', 'caption' => 'Variablentyp', 'options' => [
                            ['caption' => 'Boolean', 'value' => 0],
                            ['caption' => 'Float',   'value' => 1],
                            ['caption' => 'Integer', 'value' => 2],
                            ['caption' => 'String',  'value' => 3],
                        ]],
                        ['type' => 'Label', 'caption' => 'Ausschaltwert'],
                        ['type' => 'CheckBox',      'name' => 'OffValueBool',  'caption' => 'Boolean AUS'],
                        ['type' => 'NumberSpinner', 'name' => 'OffValueFloat', 'caption' => 'Float AUS', 'digits' => 2],
                        ['type' => 'NumberSpinner', 'name' => 'OffValueInt',   'caption' => 'Integer AUS'],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Label', 'caption' => 'Variable bei keine Bewegung (Zeitplan)'],
                        ['type' => 'SelectVariable', 'name' => 'NoMotionVariable', 'caption' => 'Variable (optional)'],
                        ['type' => 'Select', 'name' => 'NoMotionVariableType', 'caption' => 'Variablentyp', 'options' => [
                            ['caption' => 'Boolean', 'value' => 0],
                            ['caption' => 'Float',   'value' => 1],
                            ['caption' => 'Integer', 'value' => 2],
                            ['caption' => 'String',  'value' => 3],
                        ]],
                    ]],
                ]],

                ['type' => 'Label', 'caption' => ' '],

                // ── Einschaltdauer ───────────────────────────────────────
                ['type' => 'Label', 'bold' => true, 'caption' => 'Einschaltdauer'],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'NumberSpinner', 'name' => 'DurationValue', 'caption' => 'Dauer', 'minimum' => 1, 'maximum' => 9999],
                    ['type' => 'Select', 'name' => 'DurationUnit', 'caption' => 'Einheit', 'options' => [
                        ['caption' => 'Sekunden', 'value' => 0],
                        ['caption' => 'Minuten',  'value' => 1],
                        ['caption' => 'Stunden',  'value' => 2],
                    ]],
                ]],

                ['type' => 'Label', 'caption' => ' '],

                // ── Zeitplan ─────────────────────────────────────────────
                ['type' => 'Label', 'bold' => true, 'caption' => 'Zeitplan'],
                ['type' => 'SelectVariable', 'name' => 'TimeScheduleVariable', 'caption' => 'Zeitplan-Umschalter (Boolean, leer = Zeitplan A aktiv)', 'validVariableType' => [0]],

                ['type' => 'Label', 'caption' => 'Zeitplan A (Boolean = false oder keine Variable gewählt)'],
                ['type' => 'List', 'name' => 'TimeScheduleA', 'caption' => 'Zeitplan A', 'rowCount' => 5, 'add' => true, 'delete' => true,
                    'columns' => [
                        ['caption' => 'Von Std', 'name' => 'FromH', 'width' => '80px', 'add' => 7,  'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 23]],
                        ['caption' => 'Von Min', 'name' => 'FromM', 'width' => '80px', 'add' => 0,  'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 59]],
                        ['caption' => 'Bis Std', 'name' => 'ToH',   'width' => '80px', 'add' => 22, 'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 23]],
                        ['caption' => 'Bis Min', 'name' => 'ToM',   'width' => '80px', 'add' => 0,  'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 59]],
                        array_merge($scheduleValueColA,    ['width' => '200px']),
                        array_merge($scheduleValueOffColA, ['width' => '200px']),
                    ],
                ],

                ['type' => 'Label', 'caption' => ' '],

                ['type' => 'Label', 'caption' => 'Zeitplan B (Boolean = true)'],
                ['type' => 'List', 'name' => 'TimeScheduleB', 'caption' => 'Zeitplan B', 'rowCount' => 5, 'add' => true, 'delete' => true,
                    'columns' => [
                        ['caption' => 'Von Std', 'name' => 'FromH', 'width' => '80px', 'add' => 7,  'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 23]],
                        ['caption' => 'Von Min', 'name' => 'FromM', 'width' => '80px', 'add' => 0,  'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 59]],
                        ['caption' => 'Bis Std', 'name' => 'ToH',   'width' => '80px', 'add' => 22, 'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 23]],
                        ['caption' => 'Bis Min', 'name' => 'ToM',   'width' => '80px', 'add' => 0,  'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 59]],
                        array_merge($scheduleValueColB,    ['width' => '200px']),
                        array_merge($scheduleValueOffColB, ['width' => '200px']),
                    ],
                ],
            ],

                        'actions' => [
                ['type' => 'Button', 'caption' => 'Einschalten (Test)', 'onClick' => 'MDC_SwitchOn($id);'],
                ['type' => 'Button', 'caption' => 'Ausschalten (Test)', 'onClick' => 'MDC_SwitchOff($id);'],
            ],
            'status' => [
                ['code' => 102, 'icon' => 'active', 'caption' => 'Bereit'],
                ['code' => 200, 'icon' => 'error',  'caption' => 'Fehler: Einschalten-Variable nicht gesetzt'],
                ['code' => 201, 'icon' => 'error',  'caption' => 'Fehler: Kein Bewegungsmelder konfiguriert'],
            ],
        ];

        return json_encode($form);
    }

    private function GetProfileOptions(int $variableID): array
    {
        if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
            return [];
        }
        $variable = IPS_GetVariable($variableID);
        $profileName = $variable['VariableCustomProfile'] !== ''
            ? $variable['VariableCustomProfile']
            : $variable['VariableProfile'];
        if ($profileName === '' || !IPS_VariableProfileExists($profileName)) {
            return [];
        }
        $profile = IPS_GetVariableProfile($profileName);
        $options = [];
        foreach ($profile['Associations'] as $assoc) {
            $options[] = ['caption' => $assoc['Name'], 'value' => (string) $assoc['Value']];
        }
        return $options;
    }

    private function BuildValueColumn(array $profileOptions, int $varType = 3, string $name = 'Value', string $caption = 'Einschaltwert'): array
    {
        // String mit Profil -> Dropdown
        if ($varType === 3 && !empty($profileOptions)) {
            return [
                'caption' => $caption,
                'name'    => $name,
                'width'   => 'auto',
                'add'     => $profileOptions[0]['value'] ?? '',
                'edit'    => ['type' => 'Select', 'options' => $profileOptions],
            ];
        }
        // Boolean -> CheckBox
        if ($varType === 0) {
            return [
                'caption' => $caption,
                'name'    => $name,
                'width'   => 'auto',
                'add'     => 'false',
                'edit'    => ['type' => 'Select', 'options' => [
                    ['caption' => 'true',  'value' => 'true'],
                    ['caption' => 'false', 'value' => 'false'],
                ]],
            ];
        }
        // Float, Integer, String ohne Profil -> freies Textfeld
        return [
            'caption' => $caption,
            'name'    => $name,
            'width'   => 'auto',
            'add'     => '',
            'edit'    => ['type' => 'ValidationTextBox'],
        ];
    }

}
