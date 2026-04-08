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
}
