<?php

class VentilKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Ventil');
        $this->RegisterPropertyInteger('TemperatureID', 0);
        $this->RegisterPropertyInteger('PositionID', 0);
        $this->RegisterPropertyInteger('StatusID', 0);
        $this->RegisterPropertyBoolean('UseSymconColors', true);
        $this->RegisterPropertyInteger('ColorAccent', 2201331); // #2196F3

        // 4 Presets (Name + Sollwert)
        $this->RegisterPropertyString('Preset1Name', 'Zu');
        $this->RegisterPropertyFloat('Preset1Value', 0.0);
        $this->RegisterPropertyString('Preset2Name', '25 %');
        $this->RegisterPropertyFloat('Preset2Value', 25.0);
        $this->RegisterPropertyString('Preset3Name', '50 %');
        $this->RegisterPropertyFloat('Preset3Value', 50.0);
        $this->RegisterPropertyString('Preset4Name', 'Auf');
        $this->RegisterPropertyFloat('Preset4Value', 100.0);

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
            $this->ReadPropertyInteger('TemperatureID'),
            $this->ReadPropertyInteger('PositionID'),
            $this->ReadPropertyInteger('StatusID'),
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
            case 'SetPosition':
                $varID = $this->ReadPropertyInteger('PositionID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    $var = IPS_GetVariable($varID);
                    $val = $var['VariableType'] === VARIABLETYPE_INTEGER
                        ? (int) round((float) $Value)
                        : (float) $Value;
                    RequestAction($varID, $val);
                }
                break;

            case 'SetStatus':
                $varID = $this->ReadPropertyInteger('StatusID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    RequestAction($varID, (bool)(int) $Value);
                }
                break;
        }
    }

    public function GetVisualizationTile()
    {
        $dataJsonLiteral = json_encode(json_encode($this->GetCurrentData()));
        $module = file_get_contents(__DIR__ . '/module.html');
        $init   = '<script>handleMessage(' . $dataJsonLiteral . ');</script>';
        return $module . $init;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function GetVarProfile(int $varID): array
    {
        $result = ['suffix' => '', 'min' => 0.0, 'max' => 100.0, 'step' => 1.0, 'digits' => 0];

        if ($varID <= 0 || !IPS_VariableExists($varID)) {
            return $result;
        }

        $var         = IPS_GetVariable($varID);
        $profileName = $var['VariableCustomProfile'] !== ''
            ? $var['VariableCustomProfile']
            : $var['VariableProfile'];

        if ($profileName !== '' && IPS_VariableProfileExists($profileName)) {
            $profile          = IPS_GetVariableProfile($profileName);
            $result['suffix'] = trim($profile['Suffix'] ?? '');
            $result['min']    = (float) $profile['MinValue'];
            $result['max']    = (float) $profile['MaxValue'];
            $result['digits'] = (int) ($profile['Digits'] ?? 0);
            if ($profile['StepSize'] > 0) {
                $result['step'] = (float) $profile['StepSize'];
            }
        }

        if (function_exists('IPS_GetVariablePresentation')) {
            $pres = IPS_GetVariablePresentation($varID);
            if (is_array($pres)) {
                foreach (['SUFFIX', 'Suffix'] as $key) {
                    if (isset($pres[$key]) && $pres[$key] !== '') {
                        $result['suffix'] = trim($pres[$key]);
                        break;
                    }
                }
                if (isset($pres['MinValue']) && $pres['MinValue'] !== null) {
                    $result['min'] = (float) $pres['MinValue'];
                }
                if (isset($pres['MaxValue']) && $pres['MaxValue'] !== null) {
                    $result['max'] = (float) $pres['MaxValue'];
                }
                if (isset($pres['StepSize']) && $pres['StepSize'] > 0) {
                    $result['step'] = (float) $pres['StepSize'];
                }
                if (isset($pres['DIGITS']) && $pres['DIGITS'] >= 0) {
                    $result['digits'] = (int) $pres['DIGITS'];
                }
            }
        }

        return $result;
    }

    private function GetCurrentData(): array
    {
        $data = [
            'name'             => $this->ReadPropertyString('TileName'),
            'temperature'      => null,
            'tempSuffix'       => '°C',
            'position'         => null,
            'positionEditable' => false,
            'positionMin'      => 0.0,
            'positionMax'      => 100.0,
            'positionStep'     => 1.0,
            'positionSuffix'   => ' %',
            'positionDigits'   => 0,
            'status'           => null,
            'statusEditable'   => false,
            'useSymconColors'  => $this->ReadPropertyBoolean('UseSymconColors'),
            'colors'           => [
                'accent' => $this->ReadPropertyInteger('ColorAccent'),
            ],
            'presets'          => [
                ['name' => $this->ReadPropertyString('Preset1Name'), 'value' => $this->ReadPropertyFloat('Preset1Value')],
                ['name' => $this->ReadPropertyString('Preset2Name'), 'value' => $this->ReadPropertyFloat('Preset2Value')],
                ['name' => $this->ReadPropertyString('Preset3Name'), 'value' => $this->ReadPropertyFloat('Preset3Value')],
                ['name' => $this->ReadPropertyString('Preset4Name'), 'value' => $this->ReadPropertyFloat('Preset4Value')],
            ],
        ];

        $tempID = $this->ReadPropertyInteger('TemperatureID');
        if ($tempID > 0 && IPS_VariableExists($tempID)) {
            $profile = $this->GetVarProfile($tempID);
            $digits  = max(1, $profile['digits']);
            $data['temperature'] = round((float) GetValue($tempID), $digits);
            $data['tempSuffix']  = $profile['suffix'] !== '' ? $profile['suffix'] : '°C';
        }

        $posID = $this->ReadPropertyInteger('PositionID');
        if ($posID > 0 && IPS_VariableExists($posID)) {
            $profile = $this->GetVarProfile($posID);
            $data['position']         = round((float) GetValue($posID), $profile['digits']);
            $data['positionEditable'] = HasAction($posID);
            $data['positionMin']      = $profile['min'];
            $data['positionMax']      = $profile['max'];
            $data['positionStep']     = $profile['step'];
            $data['positionSuffix']   = $profile['suffix'] !== '' ? ' ' . $profile['suffix'] : ' %';
            $data['positionDigits']   = $profile['digits'];
        }

        $statusID = $this->ReadPropertyInteger('StatusID');
        if ($statusID > 0 && IPS_VariableExists($statusID)) {
            $data['status']         = (bool) GetValue($statusID);
            $data['statusEditable'] = HasAction($statusID);
        }

        return $data;
    }
}
