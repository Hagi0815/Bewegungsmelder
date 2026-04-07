<?php

declare(strict_types=1);

class MotionDetectorControl extends IPSModule
{
    private const TIMER_SWITCH_OFF = 'SwitchOffTimer';

    public function Create(): void
    {
        parent::Create();

        // Bewegungsmelder
        $this->RegisterPropertyInteger('MotionSensor1', 0);
        $this->RegisterPropertyInteger('MotionSensor2', 0);
        $this->RegisterPropertyInteger('MotionSensor3', 0);

        // Einschaltdauer (Einheit: 0=Sek, 1=Min, 2=Std)
        $this->RegisterPropertyInteger('DurationValue', 30);
        $this->RegisterPropertyInteger('DurationUnit', 0);

        // Ziel-Variable (Typ: 0=Bool, 1=Float, 2=Int, 3=String)
        $this->RegisterPropertyInteger('TargetVariable', 0);
        $this->RegisterPropertyInteger('TargetVariableType', 0);

        // Ein/Aus-Werte je Typ
        $this->RegisterPropertyBoolean('OnValueBool',   true);
        $this->RegisterPropertyBoolean('OffValueBool',  false);
        $this->RegisterPropertyFloat('OnValueFloat',    1.0);
        $this->RegisterPropertyFloat('OffValueFloat',   0.0);
        $this->RegisterPropertyInteger('OnValueInt',    1);
        $this->RegisterPropertyInteger('OffValueInt',   0);
        $this->RegisterPropertyString('OnValueString',  'EIN');
        $this->RegisterPropertyString('OffValueString', 'AUS');

        // Timer
        $this->RegisterTimer(self::TIMER_SWITCH_OFF, 0, 'MDC_SwitchOff($id);');
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->UnregisterAllMessages();

        foreach ($this->GetConfiguredSensorIDs() as $sensorID) {
            $this->RegisterMessage($sensorID, VM_UPDATE);
        }

        $this->ValidateConfiguration();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message !== VM_UPDATE) {
            return;
        }

        if (!in_array($SenderID, $this->GetConfiguredSensorIDs(), true)) {
            return;
        }

        $value = GetValue($SenderID);
        if ($this->IsTriggerActive($value)) {
            $this->OnMotionDetected();
        }
    }

    public function SwitchOff(): void
    {
        $this->SetTimerInterval(self::TIMER_SWITCH_OFF, 0);
        $this->WriteTargetValue(false);
        $this->SetStatus(102);
        $this->SendDebug('SwitchOff', 'Ziel-Variable ausgeschaltet', 0);
    }

    public function SwitchOn(): void
    {
        $this->OnMotionDetected();
    }

    private function OnMotionDetected(): void
    {
        $targetID = $this->ReadPropertyInteger('TargetVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            return;
        }

        $this->WriteTargetValue(true);
        $this->SetStatus(103);

        $seconds = $this->GetDurationInSeconds();
        $this->SetTimerInterval(self::TIMER_SWITCH_OFF, $seconds * 1000);

        $this->SendDebug('OnMotionDetected', 'Eingeschaltet, Timer: ' . $seconds . 's', 0);
    }

    private function WriteTargetValue(bool $on): void
    {
        $targetID = $this->ReadPropertyInteger('TargetVariable');
        if ($targetID <= 0 || !IPS_VariableExists($targetID)) {
            return;
        }

        $type = $this->ReadPropertyInteger('TargetVariableType');

        switch ($type) {
            case 0:
                SetValueBoolean($targetID, $on
                    ? $this->ReadPropertyBoolean('OnValueBool')
                    : $this->ReadPropertyBoolean('OffValueBool'));
                break;
            case 1:
                SetValueFloat($targetID, $on
                    ? $this->ReadPropertyFloat('OnValueFloat')
                    : $this->ReadPropertyFloat('OffValueFloat'));
                break;
            case 2:
                SetValueInteger($targetID, $on
                    ? $this->ReadPropertyInteger('OnValueInt')
                    : $this->ReadPropertyInteger('OffValueInt'));
                break;
            case 3:
                SetValueString($targetID, $on
                    ? $this->ReadPropertyString('OnValueString')
                    : $this->ReadPropertyString('OffValueString'));
                break;
        }
    }

    private function GetDurationInSeconds(): int
    {
        $value = $this->ReadPropertyInteger('DurationValue');
        $unit  = $this->ReadPropertyInteger('DurationUnit');

        switch ($unit) {
            case 1: return $value * 60;
            case 2: return $value * 3600;
            default: return $value;
        }
    }

    private function GetConfiguredSensorIDs(): array
    {
        $ids = [];
        foreach ([1, 2, 3] as $n) {
            $id = $this->ReadPropertyInteger("MotionSensor$n");
            if ($id > 0 && IPS_VariableExists($id)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

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
