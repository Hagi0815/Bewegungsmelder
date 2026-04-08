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
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

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
            $this->SendValue($targetID, $scheduleValue, $type);
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
    }

    public function SwitchOff(): void
    {
        $this->SetTimerInterval('SwitchOffTimer', 0);

        $targetID = $this->ReadPropertyInteger('OffVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            return;
        }

        $type = $this->ReadPropertyInteger('OffVariableType');
        $this->SendOffValue($targetID, $type);
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

    private function GetScheduleValue()
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
            if (empty($entry['From']) || empty($entry['To']) || !isset($entry['Value'])) {
                continue;
            }

            $fromParts = explode(':', $entry['From']);
            $toParts   = explode(':', $entry['To']);

            if (count($fromParts) < 2 || count($toParts) < 2) {
                continue;
            }

            $from = (int) $fromParts[0] * 60 + (int) $fromParts[1];
            $to   = (int) $toParts[0] * 60 + (int) $toParts[1];

            if ($from <= $to) {
                if ($now >= $from && $now < $to) {
                    return $entry['Value'];
                }
            } else {
                if ($now >= $from || $now < $to) {
                    return $entry['Value'];
                }
            }
        }

        return null;
    }

    public function GetConfigurationForm(): string
    {
        $onVarID  = $this->ReadPropertyInteger('OnVariable');
        $offVarID = $this->ReadPropertyInteger('OffVariable');

        $onOptions  = $this->GetProfileOptions($onVarID);
        $offOptions = $this->GetProfileOptions($offVarID);

        $scheduleValueColA = $this->BuildValueColumn($onOptions);
        $scheduleValueColB = $this->BuildValueColumn($onOptions);

        // Standard-Einschaltwert fuer String: Dropdown wenn Profil vorhanden
        // Standard-Werte immer als freies Textfeld
        $onStringEdit  = ['type' => 'ValidationTextBox'];
        $offStringEdit = ['type' => 'ValidationTextBox'];

        $form = [
            'elements' => [
                ['type' => 'Label', 'caption' => 'Bewegungsmelder (bis zu 3)'],
                ['type' => 'SelectVariable', 'name' => 'MotionSensor1', 'caption' => 'Bewegungsmelder 1', 'validVariableType' => [0, 1, 2]],
                ['type' => 'SelectVariable', 'name' => 'MotionSensor2', 'caption' => 'Bewegungsmelder 2 (optional)', 'validVariableType' => [0, 1, 2]],
                ['type' => 'SelectVariable', 'name' => 'MotionSensor3', 'caption' => 'Bewegungsmelder 3 (optional)', 'validVariableType' => [0, 1, 2]],
                ['type' => 'Label', 'caption' => ' '],

                ['type' => 'Label', 'caption' => 'Einschaltdauer'],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'NumberSpinner', 'name' => 'DurationValue', 'caption' => 'Dauer', 'minimum' => 1, 'maximum' => 9999],
                    ['type' => 'Select', 'name' => 'DurationUnit', 'caption' => 'Einheit', 'options' => [
                        ['caption' => 'Sekunden', 'value' => 0],
                        ['caption' => 'Minuten',  'value' => 1],
                        ['caption' => 'Stunden',  'value' => 2],
                    ]],
                ]],
                ['type' => 'Label', 'caption' => ' '],

                ['type' => 'Label', 'caption' => 'Einschalten'],
                ['type' => 'SelectVariable', 'name' => 'OnVariable', 'caption' => 'Variable zum Einschalten'],
                ['type' => 'Select', 'name' => 'OnVariableType', 'caption' => 'Variablentyp', 'options' => [
                    ['caption' => 'Boolean', 'value' => 0],
                    ['caption' => 'Float',   'value' => 1],
                    ['caption' => 'Integer', 'value' => 2],
                    ['caption' => 'String',  'value' => 3],
                ]],
                ['type' => 'Label', 'caption' => 'Standard Einschaltwert (wenn kein Zeitplan greift)'],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'CheckBox',         'name' => 'OnValueBool',   'caption' => 'Boolean EIN'],
                    ['type' => 'NumberSpinner',     'name' => 'OnValueFloat',  'caption' => 'Float EIN', 'digits' => 2],
                    ['type' => 'NumberSpinner',     'name' => 'OnValueInt',    'caption' => 'Integer EIN'],
                    array_merge(['name' => 'OnValueString', 'caption' => 'String EIN'], $onStringEdit),
                ]],
                ['type' => 'Label', 'caption' => ' '],

                ['type' => 'Label', 'caption' => 'Ausschalten'],
                ['type' => 'SelectVariable', 'name' => 'OffVariable', 'caption' => 'Variable zum Ausschalten'],
                ['type' => 'Select', 'name' => 'OffVariableType', 'caption' => 'Variablentyp', 'options' => [
                    ['caption' => 'Boolean', 'value' => 0],
                    ['caption' => 'Float',   'value' => 1],
                    ['caption' => 'Integer', 'value' => 2],
                    ['caption' => 'String',  'value' => 3],
                ]],
                ['type' => 'Label', 'caption' => 'Ausschaltwert'],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'CheckBox',         'name' => 'OffValueBool',   'caption' => 'Boolean AUS'],
                    ['type' => 'NumberSpinner',     'name' => 'OffValueFloat',  'caption' => 'Float AUS', 'digits' => 2],
                    ['type' => 'NumberSpinner',     'name' => 'OffValueInt',    'caption' => 'Integer AUS'],
                    array_merge(['name' => 'OffValueString', 'caption' => 'String AUS'], $offStringEdit),
                ]],
                ['type' => 'Label', 'caption' => ' '],

                ['type' => 'Label', 'caption' => 'Zeitplan'],
                ['type' => 'SelectVariable', 'name' => 'TimeScheduleVariable', 'caption' => 'Zeitplan-Umschalter (Boolean, leer = Zeitplan A aktiv)', 'validVariableType' => [0]],

                ['type' => 'Label', 'caption' => 'Zeitplan A (Boolean = false oder keine Variable gewählt)'],
                ['type' => 'List', 'name' => 'TimeScheduleA', 'caption' => 'Zeitplan A', 'rowCount' => 5, 'add' => true, 'delete' => true,
                    'columns' => [
                        ['caption' => 'Von', 'name' => 'From', 'width' => '150px', 'add' => '07:00', 'edit' => ['type' => 'ValidationTextBox']],
                        ['caption' => 'Bis', 'name' => 'To',   'width' => '150px', 'add' => '22:00', 'edit' => ['type' => 'ValidationTextBox']],
                        $scheduleValueColA,
                    ],
                ],
                ['type' => 'Label', 'caption' => ' '],

                ['type' => 'Label', 'caption' => 'Zeitplan B (Boolean = true)'],
                ['type' => 'List', 'name' => 'TimeScheduleB', 'caption' => 'Zeitplan B', 'rowCount' => 5, 'add' => true, 'delete' => true,
                    'columns' => [
                        ['caption' => 'Von', 'name' => 'From', 'width' => '150px', 'add' => '07:00', 'edit' => ['type' => 'ValidationTextBox']],
                        ['caption' => 'Bis', 'name' => 'To',   'width' => '150px', 'add' => '22:00', 'edit' => ['type' => 'ValidationTextBox']],
                        $scheduleValueColB,
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

    private function BuildValueColumn(array $profileOptions): array
    {
        if (!empty($profileOptions)) {
            return [
                'caption' => 'Einschaltwert',
                'name'    => 'Value',
                'width'   => 'auto',
                'add'     => $profileOptions[0]['value'] ?? '',
                'edit'    => ['type' => 'Select', 'options' => $profileOptions],
            ];
        }
        return [
            'caption' => 'Einschaltwert',
            'name'    => 'Value',
            'width'   => 'auto',
            'add'     => '',
            'edit'    => ['type' => 'ValidationTextBox'],
        ];
    }

}
