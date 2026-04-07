<?php

declare(strict_types=1);

class MotionDetectorControl extends IPSModule
{
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('MotionSensor1', 0);
        $this->RegisterPropertyInteger('MotionSensor2', 0);
        $this->RegisterPropertyInteger('MotionSensor3', 0);
        $this->RegisterPropertyInteger('DurationValue', 30);
        $this->RegisterPropertyInteger('DurationUnit', 0);
        $this->RegisterPropertyInteger('TargetVariable', 0);
        $this->RegisterPropertyInteger('TargetVariableType', 0);

        // Standard Ein/Aus Werte
        $this->RegisterPropertyBoolean('OnValueBool', true);
        $this->RegisterPropertyBoolean('OffValueBool', false);
        $this->RegisterPropertyFloat('OnValueFloat', 1.0);
        $this->RegisterPropertyFloat('OffValueFloat', 0.0);
        $this->RegisterPropertyInteger('OnValueInt', 1);
        $this->RegisterPropertyInteger('OffValueInt', 0);
        $this->RegisterPropertyString('OnValueString', 'EIN');
        $this->RegisterPropertyString('OffValueString', 'AUS');

        // Zeitplan aktivieren/deaktivieren über externe Boolean-Variable
        $this->RegisterPropertyInteger('TimeScheduleVariable', 0);

        // Zeitplan-Einträge als JSON-Array
        // Jeder Eintrag: {"From":"07:00","To":"10:00","Value":"0.5"}
        $this->RegisterPropertyString('TimeSchedule', '[]');

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
        $targetID = $this->ReadPropertyInteger('TargetVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            return;
        }

        $this->WriteTargetValue(true);

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
        $this->WriteTargetValue(false);
    }

    private function WriteTargetValue($on): void
    {
        $targetID = $this->ReadPropertyInteger('TargetVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            return;
        }

        $type = $this->ReadPropertyInteger('TargetVariableType');

        if ($on) {
            // Zeitplan prüfen
            $scheduleValue = $this->GetScheduleValue();
            if ($scheduleValue !== null) {
                // Zeitplan-Wert verwenden
                switch ($type) {
                    case 0:
                        RequestAction($targetID, (bool) $scheduleValue);
                        break;
                    case 1:
                        RequestAction($targetID, (float) $scheduleValue);
                        break;
                    case 2:
                        RequestAction($targetID, (int) $scheduleValue);
                        break;
                    case 3:
                        RequestAction($targetID, (string) $scheduleValue);
                        break;
                }
                return;
            }
        }

        // Standard-Wert verwenden
        switch ($type) {
            case 0:
                $boolVal = $on ? $this->ReadPropertyBoolean('OnValueBool') : $this->ReadPropertyBoolean('OffValueBool');
                RequestAction($targetID, (bool) $boolVal);
                break;
            case 1:
                RequestAction($targetID, $on ? $this->ReadPropertyFloat('OnValueFloat') : $this->ReadPropertyFloat('OffValueFloat'));
                break;
            case 2:
                RequestAction($targetID, $on ? $this->ReadPropertyInteger('OnValueInt') : $this->ReadPropertyInteger('OffValueInt'));
                break;
            case 3:
                RequestAction($targetID, $on ? $this->ReadPropertyString('OnValueString') : $this->ReadPropertyString('OffValueString'));
                break;
        }
    }

    private function GetScheduleValue()
    {
        // Zeitplan-Variable prüfen: nicht gesetzt = immer aktiv
        $scheduleVarID = $this->ReadPropertyInteger('TimeScheduleVariable');
        if ($scheduleVarID > 0 && IPS_VariableExists($scheduleVarID)) {
            if (!GetValueBoolean($scheduleVarID)) {
                return null;
            }
        }

        $scheduleJson = $this->ReadPropertyString('TimeSchedule');
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

            // Mitternacht-Überlauf: z.B. 22:00 - 02:00
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
