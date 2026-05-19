<?php

class WhirlpoolKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Whirlpool');
        $this->RegisterPropertyInteger('CurrentTemperatureID', 0);
        $this->RegisterPropertyInteger('TargetTemperatureID', 0);
        $this->RegisterPropertyInteger('BubblesID', 0);
        $this->RegisterPropertyInteger('PumpID', 0);
        $this->RegisterPropertyInteger('HeatingID', 0);
$this->RegisterPropertyInteger('ColorBackground', 1843237);
        $this->RegisterPropertyInteger('ColorText',       16777215);
        $this->RegisterPropertyInteger('ColorTextSub',    9214891);
        $this->RegisterPropertyInteger('ColorAccent',     46296);
        $this->RegisterPropertyInteger('ColorBubbles',    46296);
        $this->RegisterPropertyInteger('ColorPump',       2201331);
        $this->RegisterPropertyInteger('ColorHeating',    16739072);

        $this->SetVisualizationType(1);
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        $varIDs = [
            $this->ReadPropertyInteger('CurrentTemperatureID'),
            $this->ReadPropertyInteger('TargetTemperatureID'),
            $this->ReadPropertyInteger('BubblesID'),
            $this->ReadPropertyInteger('PumpID'),
            $this->ReadPropertyInteger('HeatingID'),
        ];

        $anyLinked = false;
        foreach ($varIDs as $varID) {
            if ($varID > 0 && IPS_VariableExists($varID)) {
                $this->RegisterMessage($varID, VM_UPDATE);
                $anyLinked = true;
            }
        }

        $this->SetStatus($anyLinked ? 102 : 201);
        $this->UpdateVisualizationValue(json_encode($this->GetCurrentData()));
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === VM_UPDATE) {
            $this->UpdateVisualizationValue(json_encode($this->GetCurrentData()));
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $switchMap = [
            'SetBubbles' => 'BubblesID',
            'SetPump'    => 'PumpID',
            'SetHeating' => 'HeatingID',
        ];

        if (isset($switchMap[$Ident])) {
            $varID = $this->ReadPropertyInteger($switchMap[$Ident]);
            if ($varID > 0 && IPS_VariableExists($varID)) {
                $var = IPS_GetVariable($varID);
                RequestAction($varID, $var['VariableType'] === 0 ? (bool)(int)$Value : (int)$Value);
            }
            return;
        }

        if ($Ident === 'SetTargetTemp') {
            $varID = $this->ReadPropertyInteger('TargetTemperatureID');
            if ($varID > 0 && IPS_VariableExists($varID)) {
                $cfg = $this->GetTempConfig();
                RequestAction($varID, round(max($cfg['min'], min($cfg['max'], (float) $Value)), 1));
            }
        }
    }

    public function GetVisualizationTile()
    {
        $configJson      = json_encode($this->GetTempConfig());
        $dataJsonLiteral = json_encode(json_encode($this->GetCurrentData()));

        $module = file_get_contents(__DIR__ . '/module.html');
        $init   = '<script>var _config = ' . $configJson . '; handleMessage(' . $dataJsonLiteral . ');</script>';

        return $module . $init;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function GetTempConfig(): array
    {
        $config = ['min' => 0.0, 'max' => 100.0, 'step' => 0.5];

        $varID = $this->ReadPropertyInteger('TargetTemperatureID');
        if ($varID > 0 && IPS_VariableExists($varID)) {
            // Priorität 2: Variablen-Profil (Custom hat Vorrang vor System-Profil)
            $var = IPS_GetVariable($varID);
            $profileName = $var['VariableCustomProfile'] !== ''
                ? $var['VariableCustomProfile']
                : $var['VariableProfile'];

            if ($profileName !== '' && IPS_VariableProfileExists($profileName)) {
                $profile = IPS_GetVariableProfile($profileName);
                $config['min']  = $profile['MinValue'];
                $config['max']  = $profile['MaxValue'];
                $config['step'] = $profile['StepSize'] > 0 ? $profile['StepSize'] : $config['step'];
            }

            // Priorität 1: IPS 7 Variablen-Darstellung (höchste Priorität)
            if (function_exists('IPS_GetVariablePresentation')) {
                $presentation = IPS_GetVariablePresentation($varID);
                if (is_array($presentation)) {
                    if (isset($presentation['MinValue']) && $presentation['MinValue'] !== null) {
                        $config['min'] = (float) $presentation['MinValue'];
                    }
                    if (isset($presentation['MaxValue']) && $presentation['MaxValue'] !== null) {
                        $config['max'] = (float) $presentation['MaxValue'];
                    }
                    if (isset($presentation['StepSize']) && $presentation['StepSize'] > 0) {
                        $config['step'] = (float) $presentation['StepSize'];
                    }
                }
            }
        }

        return $config;
    }

    private function GetCurrentData(): array
    {
        $data = [
            'name'        => $this->ReadPropertyString('TileName'),
            'currentTemp' => null,
            'targetTemp'  => null,
            'bubblesOn'   => null,
            'pumpOn'      => null,
            'heatingOn'   => null,
            'colors'      => [
                'bg'      => $this->ReadPropertyInteger('ColorBackground'),
                'text'    => $this->ReadPropertyInteger('ColorText'),
                'textSub' => $this->ReadPropertyInteger('ColorTextSub'),
                'accent'  => $this->ReadPropertyInteger('ColorAccent'),
                'bubbles' => $this->ReadPropertyInteger('ColorBubbles'),
                'pump'    => $this->ReadPropertyInteger('ColorPump'),
                'heating' => $this->ReadPropertyInteger('ColorHeating'),
            ],
        ];

        $currentTempID = $this->ReadPropertyInteger('CurrentTemperatureID');
        if ($currentTempID > 0 && IPS_VariableExists($currentTempID)) {
            $data['currentTemp'] = round((float) GetValue($currentTempID), 1);
        }

        $targetTempID = $this->ReadPropertyInteger('TargetTemperatureID');
        if ($targetTempID > 0 && IPS_VariableExists($targetTempID)) {
            $data['targetTemp'] = round((float) GetValue($targetTempID), 1);
        }

        foreach (['bubblesOn' => 'BubblesID', 'pumpOn' => 'PumpID', 'heatingOn' => 'HeatingID'] as $key => $prop) {
            $varID = $this->ReadPropertyInteger($prop);
            if ($varID > 0 && IPS_VariableExists($varID)) {
                $data[$key] = (bool) GetValue($varID);
            }
        }

        return $data;
    }
}
