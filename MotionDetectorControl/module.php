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
        $this->RegisterPropertyBoolean('OnValueBool', true);
        $this->RegisterPropertyBoolean('OffValueBool', false);
        $this->RegisterPropertyFloat('OnValueFloat', 1.0);
        $this->RegisterPropertyFloat('OffValueFloat', 0.0);
        $this->RegisterPropertyInteger('OnValueInt', 1);
        $this->RegisterPropertyInteger('OffValueInt', 0);
        $this->RegisterPropertyString('OnValueString', 'EIN');
        $this->RegisterPropertyString('OffValueString', 'AUS');
        $this->RegisterTimer('SwitchOffTimer', 0, 'MDC_SwitchOff($id);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();


        $ids = $this->GetConfiguredSensorIDs();
        foreach ($ids as $id) {
            $this->RegisterMessage($id, VM_UPDATE);
        }

        $this->ValidateConfiguration();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message !== VM_UPDATE) {
            return;
        }

        $ids = $this->GetConfiguredSensorIDs();
        if (!in_array($SenderID, $ids, true)) {
            return;
        }

        $value = GetValue($SenderID);
        if ($this->IsTriggerActive($value)) {
            $this->OnMotionDetected();
        }
    }

    public function SwitchOn(): void
    {
        $this->OnMotionDetected();
    }

    public function SwitchOff(): void
    {
        $this->SetTimerInterval('SwitchOffTimer', 0);
        $this->WriteTargetValue(false);
        $this->SetStatus(102);
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
        $this->SetTimerInterval('SwitchOffTimer', $seconds * 1000);
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
                $val = $on ? $this->ReadPropertyBoolean('OnValueBool') : $this->ReadPropertyBoolean('OffValueBool');
                SetValueBoolean($targetID, $val);
                break;
            case 1:
                $val = $on ? $this->ReadPropertyFloat('OnValueFloat') : $this->ReadPropertyFloat('OffValueFloat');
                SetValueFloat($targetID, $val);
                break;
            case 2:
                $val = $on ? $this->ReadPropertyInteger('OnValueInt') : $this->ReadPropertyInteger('OffValueInt');
                SetValueInteger($targetID, $val);
                break;
            case 3:
                $val = $on ? $this->ReadPropertyString('OnValueString') : $this->ReadPropertyString('OffValueString');
                SetValueString($targetID, $val);
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
        for ($n = 1; $n <= 3; $n++) {
            $id = $this->ReadPropertyInteger('MotionSensor' . $n);
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
