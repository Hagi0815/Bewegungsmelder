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
        $this->LogMessage('SwitchOff called', KL_MESSAGE);
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
}
