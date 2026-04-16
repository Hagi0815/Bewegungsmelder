<?php

declare(strict_types=1);

class MotionDetectorControl extends IPSModule
{
    public function GetVersion(): string
    {
        return 'v52-2026-04-16';
    }

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('MotionSensor1', 0);
        $this->RegisterPropertyInteger('MotionSensor2', 0);
        $this->RegisterPropertyInteger('MotionSensor3', 0);
        $this->RegisterPropertyInteger('DurationValue', 30);
        $this->RegisterPropertyInteger('DurationUnit', 0);
        $this->RegisterPropertyInteger('OnVariable', 0);
        $this->RegisterPropertyInteger('OnVariableType', 0);
        $this->RegisterPropertyBoolean('OnValueBool', true);
        $this->RegisterPropertyFloat('OnValueFloat', 1.0);
        $this->RegisterPropertyInteger('OnValueInt', 1);
        $this->RegisterPropertyString('OnValueString', 'EIN');
        $this->RegisterPropertyInteger('OffVariable', 0);
        $this->RegisterPropertyInteger('NoMotionVariable', 0);
        $this->RegisterPropertyInteger('NoMotionVariableType', 0);
        $this->RegisterPropertyString('NoMotionValueString', '');
        $this->RegisterPropertyInteger('OffVariableType', 0);
        $this->RegisterPropertyBoolean('OffValueBool', false);
        $this->RegisterPropertyFloat('OffValueFloat', 0.0);
        $this->RegisterPropertyInteger('OffValueInt', 0);
        $this->RegisterPropertyString('OffValueString', 'AUS');
        $this->RegisterPropertyInteger('TimeScheduleVariable', 0);
        $this->RegisterPropertyInteger('ManualOnVariable', 0);
        $this->RegisterPropertyInteger('ScheduleMode', 0);
        $this->RegisterPropertyString('TimeScheduleA', '[]');
        $this->RegisterPropertyInteger('SwitchActionVariableA', 0);
        $this->RegisterPropertyInteger('SwitchActionTypeA', 0);
        $this->RegisterPropertyBoolean('SwitchActionBoolA', false);
        $this->RegisterPropertyFloat('SwitchActionFloatA', 0.0);
        $this->RegisterPropertyInteger('SwitchActionIntA', 0);
        $this->RegisterPropertyString('SwitchActionStringA', '');
        $this->RegisterPropertyInteger('SwitchActionVariableB', 0);
        $this->RegisterPropertyInteger('SwitchActionTypeB', 0);
        $this->RegisterPropertyBoolean('SwitchActionBoolB', false);
        $this->RegisterPropertyFloat('SwitchActionFloatB', 0.0);
        $this->RegisterPropertyInteger('SwitchActionIntB', 0);
        $this->RegisterPropertyString('SwitchActionStringB', '');
        $this->RegisterPropertyString('TimeScheduleB', '[]');

        $this->RegisterTimer('SwitchOffTimer', 0, 'MDC_SwitchOff(' . $this->InstanceID . ');');
        $this->RegisterVariableBoolean('Active', 'Aktiv', '~Switch', 0);
        $this->EnableAction('Active');
        $this->RegisterTimer('CountdownTimer', 0, 'MDC_UpdateCountdown(' . $this->InstanceID . ');');

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

        $this->RegisterVariableInteger('Restlaufzeit', 'Restlaufzeit', 'MDC.Seconds', 0);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->UpdateRestlaufzeitProfile();

        $isNew = (@$this->GetIDForIdent('Active') == false);
        $this->RegisterVariableBoolean('Active', 'Aktiv', '~Switch', -1);
        $this->EnableAction('Active');
        if ($isNew) {
            $this->SetValue('Active', true);
            $this->SendDebug('ApplyChanges', 'Aktiv-Variable neu angelegt und auf true gesetzt', 0);
        }

        $switchVarID = $this->ReadPropertyInteger('TimeScheduleVariable');
        if ($switchVarID > 0 && IPS_VariableExists($switchVarID)) {
            $this->RegisterMessage($switchVarID, VM_UPDATE);
        }

        $manualVarID = $this->ReadPropertyInteger('ManualOnVariable');
        if ($manualVarID > 0 && IPS_VariableExists($manualVarID)) {
            $this->RegisterMessage($manualVarID, VM_UPDATE);
        }

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

        $sensorIDs = [];
        for ($n = 1; $n <= 3; $n++) {
            $id = $this->ReadPropertyInteger('MotionSensor' . $n);
            if ($id > 0) $sensorIDs[] = $id;
        }

        if (in_array($SenderID, $sensorIDs, true)) {
            if (!$this->GetValue('Active')) {
                $this->SendDebug('MessageSink', 'Modul deaktiviert – Bewegung ignoriert', 0);
                return;
            }
            $value = GetValue($SenderID);
            $this->SendDebug('MessageSink', 'Bewegungsmelder ID ' . $SenderID . ' → Wert: ' . var_export($value, true), 0);
            if (is_bool($value) && $value === true) {
                $this->SendDebug('MessageSink', 'Bewegung erkannt → SwitchOn', 0);
                $this->SwitchOn();
            } elseif ((is_int($value) || is_float($value)) && $value > 0) {
                $this->SendDebug('MessageSink', 'Bewegung erkannt (Wert > 0) → SwitchOn', 0);
                $this->SwitchOn();
            } else {
                $this->SendDebug('MessageSink', 'Keine Bewegung (Wert inaktiv)', 0);
            }
            return;
        }

        $manualVarID = $this->ReadPropertyInteger('ManualOnVariable');
        if ($manualVarID > 0 && $SenderID === $manualVarID) {
            $manualValue = GetValueBoolean($SenderID);
            if ($manualValue) {
                $this->SendDebug('MessageSink', 'Manuell EIN aktiviert → Modul deaktivieren + Licht einschalten', 0);
                $this->SetValue('Active', false);
                $this->SwitchOn();
                $this->SetTimerInterval('SwitchOffTimer', 0);
                $this->SetTimerInterval('CountdownTimer', 0);
                $this->SetValue('Restlaufzeit', 0);
            } else {
                $this->SendDebug('MessageSink', 'Manuell EIN deaktiviert → Modul aktivieren + Licht ausschalten', 0);
                $this->SetValue('Active', true);
                $this->SwitchOff();
            }
            return;
        }

        if ($this->ReadPropertyInteger('ScheduleMode') === 1) {
            $switchVarID = $this->ReadPropertyInteger('TimeScheduleVariable');
            if ($SenderID === $switchVarID) {
                $this->ExecuteSwitchAction();
            }
        }
    }

    public function CreateActiveVariable(): void
    {
        $activeVarID = 0;
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (IPS_ObjectExists($childID) && IPS_GetObject($childID)['ObjectIdent'] === 'Active') {
                $activeVarID = $childID;
                break;
            }
        }
        if ($activeVarID === 0) {
            $newVarID = IPS_CreateVariable(0);
            IPS_SetParent($newVarID, $this->InstanceID);
            IPS_SetIdent($newVarID, 'Active');
            IPS_SetName($newVarID, 'Aktiv');
            IPS_SetVariableCustomProfile($newVarID, '~Switch');
            IPS_SetVariableCustomAction($newVarID, $this->InstanceID);
            SetValueBoolean($newVarID, true);
            echo 'Aktiv-Variable angelegt (ID: ' . $newVarID . ')';
        } else {
            echo 'Aktiv-Variable bereits vorhanden (ID: ' . $activeVarID . ')';
        }
    }

    public function RequestAction($ident, $value): void
    {
        if ($ident === 'Active') {
            $this->SetValue('Active', (bool) $value);
            $this->SendDebug('RequestAction', 'Modul ' . ($value ? 'aktiviert' : 'deaktiviert'), 0);
            return;
        }
    }

    public function SwitchOn(): void
    {
        $this->SendDebug('SwitchOn', 'START', 0);
        $targetID = $this->ReadPropertyInteger('OnVariable');
        $this->SendDebug('SwitchOn', 'TargetID: ' . $targetID, 0);
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            $this->SendDebug('SwitchOn', 'Abgebrochen: Einschalten-Variable nicht gesetzt', 0);
            return;
        }

        $scheduleValue = $this->GetScheduleValue();
        $type = $this->ReadPropertyInteger('OnVariableType');
        $mode = $this->ReadPropertyInteger('ScheduleMode');
        $modeLabel = $mode === 0 ? 'Zeitplan' : 'Tag/Nacht';

        if ($scheduleValue !== null) {
            $this->SendDebug('SwitchOn', 'Modus: ' . $modeLabel . ' | Schaltpunkt-Wert: ' . $scheduleValue, 0);
            if ($type === 0) {
                $this->SendValue($targetID, ($scheduleValue === 'true' || $scheduleValue === '1'), $type);
            } else {
                $this->SendValue($targetID, $scheduleValue, $type);
            }
        } elseif ($mode === 1) {
            $this->SendDebug('SwitchOn', 'Tag/Nacht Modus: Kein Eintrag im aktiven Plan → nicht einschalten', 0);
            return;
        } else {
            $this->SendDebug('SwitchOn', 'Modus: ' . $modeLabel . ' | Kein Schaltpunkt aktiv → Standard-Einschaltwert', 0);
            $this->SendOnValue($targetID, $type);
        }

        $value = $this->ReadPropertyInteger('DurationValue');
        $unit  = $this->ReadPropertyInteger('DurationUnit');
        switch ($unit) {
            case 1: $seconds = $value * 60; break;
            case 2: $seconds = $value * 3600; break;
            default: $seconds = $value; break;
        }
        $unitLabel = ['Sekunden', 'Minuten', 'Stunden'][$unit] ?? 'Sekunden';
        $this->SendDebug('SwitchOn', 'Timer gesetzt: ' . $value . ' ' . $unitLabel . ' (' . $seconds . ' Sek.)', 0);
        $this->SetTimerInterval('SwitchOffTimer', $seconds * 1000);
        $this->SetValue('Restlaufzeit', $value);
        $this->SetTimerInterval('CountdownTimer', 1000);
    }

    public function SwitchOff(): void
    {
        $this->SetTimerInterval('SwitchOffTimer', 0);
        $this->SetTimerInterval('CountdownTimer', 0);
        $this->SetValue('Restlaufzeit', 0);
        $this->SendDebug('SwitchOff', 'Timer abgelaufen oder manuell ausgeschaltet', 0);

        $scheduleOffValue = $this->GetScheduleOffValue();

        if ($scheduleOffValue !== null) {
            $noMotionID = $this->ReadPropertyInteger('NoMotionVariable');
            if ($noMotionID > 0 && IPS_VariableExists($noMotionID)) {
                $type = $this->ReadPropertyInteger('NoMotionVariableType');
                $targetID = $noMotionID;
                $this->SendDebug('SwitchOff', 'Schaltpunkt "keine Bewegung": ' . $scheduleOffValue . ' → NoMotion-Variable (ID: ' . $noMotionID . ')', 0);
            } else {
                $targetID = $this->ReadPropertyInteger('OffVariable');
                if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
                    $this->SendDebug('SwitchOff', 'Abgebrochen: Ausschalten-Variable nicht gesetzt', 0);
                    return;
                }
                $type = $this->ReadPropertyInteger('OffVariableType');
                $this->SendDebug('SwitchOff', 'Schaltpunkt "keine Bewegung": ' . $scheduleOffValue . ' → Off-Variable (ID: ' . $targetID . ')', 0);
            }
            if ($type === 0) {
                $this->SendValue($targetID, ($scheduleOffValue === 'true' || $scheduleOffValue === '1'), $type);
            } else {
                $this->SendValue($targetID, $scheduleOffValue, $type);
            }
        } else {
            $targetID = $this->ReadPropertyInteger('OffVariable');
            if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
                $this->SendDebug('SwitchOff', 'Abgebrochen: Ausschalten-Variable nicht gesetzt', 0);
                return;
            }
            $type = $this->ReadPropertyInteger('OffVariableType');
            $this->SendDebug('SwitchOff', 'Kein Schaltpunkt aktiv → Standard-Ausschaltwert (ID: ' . $targetID . ')', 0);
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

    public function ExecuteSwitchAction(): void
    {
        $switchVarID = $this->ReadPropertyInteger('TimeScheduleVariable');
        $isB = false;
        if ($switchVarID > 0 && IPS_VariableExists($switchVarID)) {
            $isB = GetValueBoolean($switchVarID);
        }

        $suffix = $isB ? 'B' : 'A';
        $label  = $isB ? 'B (true)' : 'A (false)';

        $targetID = $this->ReadPropertyInteger('SwitchActionVariable' . $suffix);
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            $this->SendDebug('SwitchAction', 'Plan ' . $label . ': Keine Aktionsvariable konfiguriert', 0);
            return;
        }

        $type = $this->ReadPropertyInteger('SwitchActionType' . $suffix);
        switch ($type) {
            case 0:
                $val = $this->ReadPropertyBoolean('SwitchActionBool' . $suffix);
                $this->SendDebug('SwitchAction', 'Plan ' . $label . ' → Boolean: ' . ($val ? 'true' : 'false'), 0);
                RequestAction($targetID, $val);
                break;
            case 1:
                $val = $this->ReadPropertyFloat('SwitchActionFloat' . $suffix);
                $this->SendDebug('SwitchAction', 'Plan ' . $label . ' → Float: ' . $val, 0);
                RequestAction($targetID, $val);
                break;
            case 2:
                $val = $this->ReadPropertyInteger('SwitchActionInt' . $suffix);
                $this->SendDebug('SwitchAction', 'Plan ' . $label . ' → Integer: ' . $val, 0);
                RequestAction($targetID, $val);
                break;
            case 3:
                $val = $this->ReadPropertyString('SwitchActionString' . $suffix);
                $this->SendDebug('SwitchAction', 'Plan ' . $label . ' → String: ' . $val, 0);
                RequestAction($targetID, $val);
                break;
        }
    }

    public function HandleDayNightTrigger(int $senderID): void
    {
        $dayNight = json_decode($this->ReadPropertyString('DayNightSchedule'), true);
        if (empty($dayNight)) {
            return;
        }

        $currentValue = GetValueBoolean($senderID);

        foreach ($dayNight as $entry) {
            if (empty($entry['TriggerVar'])) {
                continue;
            }
            if ((int) $entry['TriggerVar'] !== $senderID) {
                continue;
            }

            $expectedValue = isset($entry['TriggerValue']) && $entry['TriggerValue'] === 'true';
            if ($currentValue !== $expectedValue) {
                continue;
            }

            $targetID = isset($entry['TargetVar']) ? (int) $entry['TargetVar'] : 0;
            if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
                continue;
            }

            $type  = isset($entry['TargetType']) ? (int) $entry['TargetType'] : 0;
            $value = $entry['TargetValue'] ?? '';

            switch ($type) {
                case 0: RequestAction($targetID, ($value === 'true' || $value === '1')); break;
                case 1: RequestAction($targetID, (float) $value); break;
                case 2: RequestAction($targetID, (int) $value); break;
                case 3: RequestAction($targetID, (string) $value); break;
            }
        }
    }

    private function GetDayNightEntry()
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

        foreach ($schedule as $entry) {
            if (!isset($entry['Value'])) {
                continue;
            }
            $onVal  = isset($entry['Value'])    && $entry['Value']    !== '' ? $entry['Value']    : null;
            $offVal = isset($entry['ValueOff']) && $entry['ValueOff'] !== '' ? $entry['ValueOff'] : null;
            $switchLabel = $useScheduleB ? 'B (true)' : 'A (false)';
            $this->SendDebug('GetDayNightEntry', 'Plan ' . $switchLabel . ' aktiv | EIN: ' . ($onVal ?? 'Standard') . ' | AUS: ' . ($offVal ?? 'Standard'), 0);
            return ['on' => $onVal, 'off' => $offVal];
        }

        return null;
    }

    private function GetScheduleEntry()
    {
        $mode = $this->ReadPropertyInteger('ScheduleMode');

        if ($mode === 1) {
            return $this->GetDayNightEntry();
        }

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
            if (empty($entry['From']) || empty($entry['To'])) {
                continue;
            }

            $fromParts = explode(':', $entry['From']);
            $toParts   = explode(':', $entry['To']);

            if (count($fromParts) < 2 || count($toParts) < 2) {
                continue;
            }

            $from = (int) $fromParts[0] * 60 + (int) $fromParts[1];
            $to   = (int) $toParts[0] * 60 + (int) $toParts[1];

            $inRange = false;
            if ($from <= $to) {
                $inRange = ($now >= $from && $now < $to);
            } else {
                $inRange = ($now >= $from || $now < $to);
            }

            if ($inRange) {
                $onVal  = isset($entry['Value'])    && $entry['Value']    !== '' ? $entry['Value']    : null;
                $offVal = isset($entry['ValueOff']) && $entry['ValueOff'] !== '' ? $entry['ValueOff'] : null;
                $this->SendDebug('GetScheduleEntry', 'Zeitfenster ' . $entry['From'] . '-' . $entry['To'] . ' aktiv | EIN: ' . ($onVal ?? 'Standard') . ' | AUS: ' . ($offVal ?? 'Standard'), 0);
                return ['on' => $onVal, 'off' => $offVal];
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
        $scheduleMode  = $this->ReadPropertyInteger('ScheduleMode');
        $switchVarAID     = $this->ReadPropertyInteger('SwitchActionVariableA');
        $switchVarBID     = $this->ReadPropertyInteger('SwitchActionVariableB');
        $switchOptionsA   = $this->GetProfileOptions($switchVarAID);
        $switchOptionsB   = $this->GetProfileOptions($switchVarBID);
        $switchTypeA      = $this->ReadPropertyInteger('SwitchActionTypeA');
        $switchTypeB      = $this->ReadPropertyInteger('SwitchActionTypeB');
        $switchStringEditA = ($switchTypeA === 3 && !empty($switchOptionsA))
            ? ['type' => 'Select', 'options' => $switchOptionsA]
            : ['type' => 'ValidationTextBox'];
        $switchStringEditB = ($switchTypeB === 3 && !empty($switchOptionsB))
            ? ['type' => 'Select', 'options' => $switchOptionsB]
            : ['type' => 'ValidationTextBox'];
        $offVarID      = $this->ReadPropertyInteger('OffVariable');
        $noMotionVarID = $this->ReadPropertyInteger('NoMotionVariable');
        $onOptions       = $this->GetProfileOptions($onVarID);
        $offOptions      = $this->GetProfileOptions($offVarID);
        $noMotionOptions = $this->GetProfileOptions($noMotionVarID);
        $noMotionType = $this->ReadPropertyInteger('NoMotionVariableType');
        $noMotionStringEdit = ($noMotionType === 3 && !empty($noMotionOptions))
            ? ['type' => 'Select', 'options' => $noMotionOptions]
            : ['type' => 'ValidationTextBox'];
        $onType  = $this->ReadPropertyInteger('OnVariableType');
        $offType = $this->ReadPropertyInteger('OffVariableType');
        $onStringEdit = ($onType === 3 && !empty($onOptions))
            ? ['type' => 'Select', 'options' => $onOptions]
            : ['type' => 'ValidationTextBox'];
        $scheduleValueColA    = $this->BuildValueColumn($onOptions,  $onType,  'Value',    'Wert bei Bewegung');
        $scheduleValueColB    = $this->BuildValueColumn($onOptions,  $onType,  'Value',    'Wert bei Bewegung');
        $scheduleValueOffColA = $this->BuildValueColumn($noMotionOptions, $noMotionType, 'ValueOff', 'Wert bei keine Bewegung');
        $scheduleValueOffColB = $this->BuildValueColumn($noMotionOptions, $noMotionType, 'ValueOff', 'Wert bei keine Bewegung');

        $form = [
            'elements' => [
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'Label', 'bold' => true, 'caption' => 'Modul Status: ' . ($this->GetValue('Active') ? '✓ Aktiv' : '✗ Deaktiviert')],
                    ['type' => 'Button', 'caption' => $this->GetValue('Active') ? 'Deaktivieren' : 'Aktivieren',
                        'onClick' => 'RequestAction(' . $this->GetIDForIdent("Active") . ', ' . ($this->GetValue('Active') ? 'false' : 'true') . '); IPS_RequestAction($id, "dummy", 0);'],
                ]],
                ['type' => 'Label', 'caption' => ' '],
                ['type' => 'Label', 'bold' => true, 'caption' => 'Manuell EIN'],
                ['type' => 'Label', 'caption' => 'Wenn aktiv: Modul deaktivieren + Licht einschalten. Wenn inaktiv: Modul aktivieren + Licht ausschalten.'],
                ['type' => 'SelectVariable', 'name' => 'ManualOnVariable', 'caption' => 'Manuell EIN Variable (Boolean)', 'validVariableType' => [0]],
                ['type' => 'Label', 'caption' => ' '],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'ExpansionPanel', 'caption' => 'Bewegungsmelder', 'expanded' => true, 'items' => [
                        ['type' => 'SelectVariable', 'name' => 'MotionSensor1', 'caption' => 'Bewegungsmelder 1', 'validVariableType' => [0, 1, 2]],
                        ['type' => 'SelectVariable', 'name' => 'MotionSensor2', 'caption' => 'Bewegungsmelder 2 (optional)', 'validVariableType' => [0, 1, 2]],
                        ['type' => 'SelectVariable', 'name' => 'MotionSensor3', 'caption' => 'Bewegungsmelder 3 (optional)', 'validVariableType' => [0, 1, 2]],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'CheckBox', 'name' => 'OffValueBool', 'caption' => ' ', 'visible' => false],
                        ['type' => 'NumberSpinner', 'name' => 'OffValueFloat', 'caption' => ' ', 'visible' => false],
                        ['type' => 'NumberSpinner', 'name' => 'OffValueInt', 'caption' => ' ', 'visible' => false],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Select', 'name' => 'NoMotionVariableType', 'caption' => ' ', 'visible' => false, 'options' => [['caption' => ' ', 'value' => 0]]],
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
                        array_merge(['name' => 'OnValueString', 'caption' => 'String EIN'], $onStringEdit),
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Label', 'caption' => ' '],
                        ['type' => 'Select', 'name' => 'OffVariableType', 'caption' => ' ', 'visible' => false, 'options' => [['caption' => ' ', 'value' => 0]]],
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
                        array_merge(['name' => 'NoMotionValueString', 'caption' => 'String (keine Bewegung)'], $noMotionStringEdit),
                    ]],
                ]],
                ['type' => 'Label', 'caption' => ' '],
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
                ['type' => 'Label', 'bold' => true, 'caption' => 'Schaltpunkte'],
                ['type' => 'Select', 'name' => 'ScheduleMode', 'caption' => 'Modus', 'options' => [
                    ['caption' => 'Zeitplan (Uhrzeit)', 'value' => 0],
                    ['caption' => 'Tag/Nacht (Boolean)', 'value' => 1],
                ]],
                ['type' => 'SelectVariable', 'name' => 'TimeScheduleVariable',
                    'caption' => $scheduleMode === 0
                        ? 'Zeitplan-Umschalter (Boolean, leer = Zeitplan A aktiv)'
                        : 'Tag/Nacht Variable (Boolean: false = A, true = B)',
                    'validVariableType' => [0]],
                ['type' => 'Label', 'caption' => $scheduleMode === 0 ? 'Zeitplan A (Boolean = false oder keine Variable gewählt)' : 'Tag/Nacht A (Boolean = false oder keine Variable)'],
                ['type' => 'List', 'name' => 'TimeScheduleA', 'caption' => 'Zeitplan A', 'rowCount' => 5, 'add' => true, 'delete' => true,
                    'columns' => array_merge(
                        $scheduleMode === 0 ? [
                            ['caption' => 'Von', 'name' => 'From', 'width' => '100px', 'add' => '07:00', 'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Bis', 'name' => 'To',   'width' => '100px', 'add' => '22:00', 'edit' => ['type' => 'ValidationTextBox']],
                        ] : [],
                        [
                            array_merge($scheduleValueColA,    ['width' => '200px']),
                            array_merge($scheduleValueOffColA, ['width' => '200px']),
                        ]
                    ),
                ],
                ['type' => 'Label', 'caption' => ' '],
                ['type' => 'Label', 'caption' => $scheduleMode === 0 ? 'Zeitplan B (Boolean = true)' : 'Tag/Nacht B (Boolean = true)'],
                ['type' => 'List', 'name' => 'TimeScheduleB', 'caption' => 'Zeitplan B', 'rowCount' => 5, 'add' => true, 'delete' => true,
                    'columns' => array_merge(
                        $scheduleMode === 0 ? [
                            ['caption' => 'Von', 'name' => 'From', 'width' => '100px', 'add' => '07:00', 'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Bis', 'name' => 'To',   'width' => '100px', 'add' => '22:00', 'edit' => ['type' => 'ValidationTextBox']],
                        ] : [],
                        [
                            array_merge($scheduleValueColB,    ['width' => '200px']),
                            array_merge($scheduleValueOffColB, ['width' => '200px']),
                        ]
                    ),
                ],
                ['type' => 'Label', 'caption' => ' '],
                ['type' => 'Label', 'bold' => true, 'caption' => 'Aktion beim Umschalten' . ($scheduleMode === 1 ? ' der Tag/Nacht-Variable' : ' (nur im Tag/Nacht Modus aktiv)')],
                ['type' => 'Label', 'caption' => 'Wird sofort ausgeführt wenn die Boolean-Variable umschaltet'],
                ['type' => 'Label', 'caption' => 'Aktion bei Plan A (Boolean = false)'],
                ['type' => 'SelectVariable', 'name' => 'SwitchActionVariableA', 'caption' => 'Variable'],
                ['type' => 'Select', 'name' => 'SwitchActionTypeA', 'caption' => 'Typ', 'options' => [
                    ['caption' => 'Boolean', 'value' => 0],
                    ['caption' => 'Float',   'value' => 1],
                    ['caption' => 'Integer', 'value' => 2],
                    ['caption' => 'String',  'value' => 3],
                ]],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'CheckBox',     'name' => 'SwitchActionBoolA',  'caption' => 'Boolean'],
                    ['type' => 'NumberSpinner', 'name' => 'SwitchActionFloatA', 'caption' => 'Float', 'digits' => 2],
                    ['type' => 'NumberSpinner', 'name' => 'SwitchActionIntA',   'caption' => 'Integer'],
                    array_merge(['name' => 'SwitchActionStringA', 'caption' => 'String'], $switchStringEditA),
                ]],
                ['type' => 'Label', 'caption' => ' '],
                ['type' => 'Label', 'caption' => 'Aktion bei Plan B (Boolean = true)'],
                ['type' => 'SelectVariable', 'name' => 'SwitchActionVariableB', 'caption' => 'Variable'],
                ['type' => 'Select', 'name' => 'SwitchActionTypeB', 'caption' => 'Typ', 'options' => [
                    ['caption' => 'Boolean', 'value' => 0],
                    ['caption' => 'Float',   'value' => 1],
                    ['caption' => 'Integer', 'value' => 2],
                    ['caption' => 'String',  'value' => 3],
                ]],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'CheckBox',     'name' => 'SwitchActionBoolB',  'caption' => 'Boolean'],
                    ['type' => 'NumberSpinner', 'name' => 'SwitchActionFloatB', 'caption' => 'Float', 'digits' => 2],
                    ['type' => 'NumberSpinner', 'name' => 'SwitchActionIntB',   'caption' => 'Integer'],
                    array_merge(['name' => 'SwitchActionStringB', 'caption' => 'String'], $switchStringEditB),
                ]],
            ],
            'actions' => [
                ['type' => 'Button', 'caption' => 'Einschalten (Test)', 'onClick' => 'MDC_SwitchOn($id);'],
                ['type' => 'Button', 'caption' => 'Ausschalten (Test)', 'onClick' => 'MDC_SwitchOff($id);'],
                ['type' => 'Button', 'caption' => 'Aktiv-Variable anlegen', 'onClick' => 'IPS_ApplyChanges($id); echo "Variable wurde angelegt";'],
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
        if ($varType === 3 && !empty($profileOptions)) {
            return [
                'caption' => $caption,
                'name'    => $name,
                'width'   => 'auto',
                'add'     => $profileOptions[0]['value'] ?? '',
                'edit'    => ['type' => 'Select', 'options' => $profileOptions],
            ];
        }
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
        return [
            'caption' => $caption,
            'name'    => $name,
            'width'   => 'auto',
            'add'     => '',
            'edit'    => ['type' => 'ValidationTextBox'],
        ];
    }
}
