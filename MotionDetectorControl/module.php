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

        for ($n = 1; $n <= 3; $n++) {
            $id = $this->ReadPropertyInteger('MotionSensor' . $n);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
    }

    public function SwitchOn(): void
    {
    }

    public function SwitchOff(): void
    {
        $this->SetTimerInterval('SwitchOffTimer', 0);
    }
}
