<?php

class KlimaKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Klimaanlage');
        $this->RegisterPropertyInteger('StatusID', 0);
        $this->RegisterPropertyInteger('CurrentTemperatureID', 0);
        $this->RegisterPropertyInteger('ModusID', 0);
        $this->RegisterPropertyInteger('TargetTemperatureID', 0);
        $this->RegisterPropertyInteger('FanSpeedID', 0);
        $this->RegisterPropertyInteger('SilentModeID', 0);
        $this->RegisterPropertyBoolean('UseSymconColors', true);
        $this->RegisterPropertyInteger('ColorOn',  2201331);
        $this->RegisterPropertyInteger('ColorOff', 10066329);

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
            $this->ReadPropertyInteger('StatusID'),
            $this->ReadPropertyInteger('CurrentTemperatureID'),
            $this->ReadPropertyInteger('ModusID'),
            $this->ReadPropertyInteger('TargetTemperatureID'),
            $this->ReadPropertyInteger('FanSpeedID'),
            $this->ReadPropertyInteger('SilentModeID'),
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
            case 'SetStatus':
                $varID = $this->ReadPropertyInteger('StatusID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    RequestAction($varID, (bool)(int) $Value);
                }
                break;

            case 'SetTargetTemp':
                $varID = $this->ReadPropertyInteger('TargetTemperatureID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    $cfg = $this->GetTempConfig();
                    $val = round(max($cfg['min'], min($cfg['max'], (float) $Value)), 1);
                    RequestAction($varID, $val);
                }
                break;

            case 'SetModus':
                $varID = $this->ReadPropertyInteger('ModusID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    $var = IPS_GetVariable($varID);
                    $val = $var['VariableType'] === VARIABLETYPE_STRING ? (string) $Value : (int) $Value;
                    RequestAction($varID, $val);
                }
                break;

            case 'SetFanSpeed':
                $varID = $this->ReadPropertyInteger('FanSpeedID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    RequestAction($varID, (int) $Value);
                }
                break;

            case 'SetSilentMode':
                $varID = $this->ReadPropertyInteger('SilentModeID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    RequestAction($varID, (bool)(int) $Value);
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
        $config = ['min' => 16.0, 'max' => 30.0, 'step' => 0.5];

        $varID = $this->ReadPropertyInteger('TargetTemperatureID');
        if ($varID <= 0 || !IPS_VariableExists($varID)) {
            return $config;
        }

        $var         = IPS_GetVariable($varID);
        $profileName = $var['VariableCustomProfile'] !== ''
            ? $var['VariableCustomProfile']
            : $var['VariableProfile'];

        if ($profileName !== '' && IPS_VariableProfileExists($profileName)) {
            $profile        = IPS_GetVariableProfile($profileName);
            $config['min']  = (float) $profile['MinValue'];
            $config['max']  = (float) $profile['MaxValue'];
            if ($profile['StepSize'] > 0) {
                $config['step'] = (float) $profile['StepSize'];
            }
        }

        if (function_exists('IPS_GetVariablePresentation')) {
            $pres = IPS_GetVariablePresentation($varID);
            if (is_array($pres)) {
                if (isset($pres['MinValue']) && $pres['MinValue'] !== null) {
                    $config['min'] = (float) $pres['MinValue'];
                }
                if (isset($pres['MaxValue']) && $pres['MaxValue'] !== null) {
                    $config['max'] = (float) $pres['MaxValue'];
                }
                if (isset($pres['StepSize']) && $pres['StepSize'] > 0) {
                    $config['step'] = (float) $pres['StepSize'];
                }
            }
        }

        return $config;
    }

    private function GetVarOptions(int $varID): array
    {
        $options = [];

        if (function_exists('IPS_GetVariablePresentation')) {
            $pres = IPS_GetVariablePresentation($varID);
            if (!empty($pres['OPTIONS'])) {
                $opts = json_decode($pres['OPTIONS'], true);
                if (is_array($opts)) {
                    foreach ($opts as $opt) {
                        $options[] = ['value' => (string) $opt['Value'], 'label' => $opt['Caption']];
                    }
                    return $options;
                }
            }
        }

        $var         = IPS_GetVariable($varID);
        $profileName = $var['VariableCustomProfile'] !== ''
            ? $var['VariableCustomProfile']
            : $var['VariableProfile'];

        if ($profileName !== '' && IPS_VariableProfileExists($profileName)) {
            foreach (IPS_GetVariableProfile($profileName)['Associations'] as $assoc) {
                $options[] = ['value' => (string) $assoc['Value'], 'label' => $assoc['Name']];
            }
        }

        return $options;
    }

    private function GetCurrentData(): array
    {
        $data = [
            'name'               => $this->ReadPropertyString('TileName'),
            'status'             => null,
            'statusEditable'     => false,
            'currentTemp'        => null,
            'targetTemp'         => null,
            'targetTempEditable' => false,
            'modus'              => null,
            'modusOptions'       => [],
            'fanSpeed'            => null,
            'fanSpeedOptions'     => [],
            'silentMode'          => null,
            'silentModeEditable'  => false,
            'useSymconColors'    => $this->ReadPropertyBoolean('UseSymconColors'),
            'colors'             => [
                'on'  => $this->ReadPropertyInteger('ColorOn'),
                'off' => $this->ReadPropertyInteger('ColorOff'),
            ],
        ];

        $statusID = $this->ReadPropertyInteger('StatusID');
        if ($statusID > 0 && IPS_VariableExists($statusID)) {
            $data['status']         = (bool) GetValue($statusID);
            $data['statusEditable'] = HasAction($statusID);
        }

        $currentTempID = $this->ReadPropertyInteger('CurrentTemperatureID');
        if ($currentTempID > 0 && IPS_VariableExists($currentTempID)) {
            $data['currentTemp'] = round((float) GetValue($currentTempID), 1);
        }

        $targetTempID = $this->ReadPropertyInteger('TargetTemperatureID');
        if ($targetTempID > 0 && IPS_VariableExists($targetTempID)) {
            $data['targetTemp']         = round((float) GetValue($targetTempID), 1);
            $data['targetTempEditable'] = HasAction($targetTempID);
        }

        $modusID = $this->ReadPropertyInteger('ModusID');
        if ($modusID > 0 && IPS_VariableExists($modusID)) {
            $data['modus']        = (string) GetValue($modusID);
            $data['modusOptions'] = $this->GetVarOptions($modusID);
        }

        $fanSpeedID = $this->ReadPropertyInteger('FanSpeedID');
        if ($fanSpeedID > 0 && IPS_VariableExists($fanSpeedID)) {
            $data['fanSpeed']        = (string) GetValue($fanSpeedID);
            $data['fanSpeedOptions'] = $this->GetVarOptions($fanSpeedID);
        }

        $silentModeID = $this->ReadPropertyInteger('SilentModeID');
        if ($silentModeID > 0 && IPS_VariableExists($silentModeID)) {
            $data['silentMode']         = (bool) GetValue($silentModeID);
            $data['silentModeEditable'] = HasAction($silentModeID);
        }

        return $data;
    }
}
