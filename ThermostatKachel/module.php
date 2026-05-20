<?php

class ThermostatKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Thermostat');
        $this->RegisterPropertyInteger('CurrentTemperatureID', 0);
        $this->RegisterPropertyInteger('TargetTemperatureID', 0);
        $this->RegisterPropertyInteger('HumidityID', 0);
        $this->RegisterPropertyInteger('ValvePositionID', 0);
        $this->RegisterPropertyInteger('HVACModeID', 0);
$this->RegisterPropertyInteger('ColorHeat', 16739072);
        $this->RegisterPropertyInteger('ColorCool', 2201331);
        $this->RegisterPropertyInteger('ColorAuto', 5025616);
        $this->RegisterPropertyInteger('ColorOff',  10066329);
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
            $this->ReadPropertyInteger('HumidityID'),
            $this->ReadPropertyInteger('ValvePositionID'),
            $this->ReadPropertyInteger('HVACModeID'),
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
        switch ($Ident) {
            case 'SetTargetTemp':
                $varID = $this->ReadPropertyInteger('TargetTemperatureID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    $cfg = $this->GetTempConfig();
                    $val = round(max($cfg['min'], min($cfg['max'], (float) $Value)), 1);
                    RequestAction($varID, $val);
                }
                break;

            case 'SetHVACMode':
                $varID = $this->ReadPropertyInteger('HVACModeID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    $var = IPS_GetVariable($varID);
                    $val = $var['VariableType'] === 1 ? (int) $Value : (string) $Value;
                    RequestAction($varID, $val);
                }
                break;

            case 'SetValvePosition':
                $varID = $this->ReadPropertyInteger('ValvePositionID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    $var = IPS_GetVariable($varID);
                    $val = $var['VariableType'] === 1
                        ? (int) round((float) $Value)
                        : (float) round((float) $Value, 1);
                    RequestAction($varID, $val);
                }
                break;
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
            'name'          => $this->ReadPropertyString('TileName'),
            'currentTemp'   => null,
            'targetTemp'    => null,
            'humidity'      => null,
            'valvePosition' => null,
            'hvacMode'      => null,
            'modeOptions'   => [],
            'colors'      => [
                'heat'    => $this->ReadPropertyInteger('ColorHeat'),
                'cool'    => $this->ReadPropertyInteger('ColorCool'),
                'auto'    => $this->ReadPropertyInteger('ColorAuto'),
                'off'     => $this->ReadPropertyInteger('ColorOff'),
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

        $humidityID = $this->ReadPropertyInteger('HumidityID');
        if ($humidityID > 0 && IPS_VariableExists($humidityID)) {
            $data['humidity'] = round((float) GetValue($humidityID), 0);
        }

        $valveID = $this->ReadPropertyInteger('ValvePositionID');
        if ($valveID > 0 && IPS_VariableExists($valveID)) {
            $data['valvePosition'] = round((float) GetValue($valveID), 0);
        }

        $hvacModeID = $this->ReadPropertyInteger('HVACModeID');
        if ($hvacModeID > 0 && IPS_VariableExists($hvacModeID)) {
            $data['hvacMode'] = (string) GetValue($hvacModeID);
            $associations = [];

            // Priorität 1: IPS 7 Variablendarstellung
            if (function_exists('IPS_GetVariablePresentation')) {
                $pres = IPS_GetVariablePresentation($hvacModeID);
                if (!empty($pres['OPTIONS'])) {
                    $opts = json_decode($pres['OPTIONS'], true);
                    if (is_array($opts)) {
                        foreach ($opts as $opt) {
                            $associations[] = ['Value' => $opt['Value'], 'Name' => $opt['Caption']];
                        }
                    }
                }
            }

            // Priorität 2: Custom Profile / Standard Profile
            if (empty($associations)) {
                $var = IPS_GetVariable($hvacModeID);
                $profileName = $var['VariableCustomProfile'] !== '' ? $var['VariableCustomProfile'] : $var['VariableProfile'];
                if ($profileName !== '') {
                    $profile = IPS_GetVariableProfile($profileName);
                    $associations = $profile['Associations'];
                }
            }

            foreach ($associations as $assoc) {
                $data['modeOptions'][] = [
                    'value' => (string) $assoc['Value'],
                    'label' => $assoc['Name'],
                ];
            }
        }

        return $data;
    }

}
