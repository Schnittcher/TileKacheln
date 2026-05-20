<?php

class RolloKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Rollo');
        $this->RegisterPropertyInteger('PositionID', 0);
        $this->RegisterPropertyBoolean('Invert', false);
        $this->RegisterPropertyInteger('SlatsID', 0);
        $this->RegisterPropertyInteger('ColorAccent', 2201331);

        $this->RegisterPropertyString('Preset1Label', '');
        $this->RegisterPropertyFloat('Preset1Value', 0.0);
        $this->RegisterPropertyString('Preset2Label', '');
        $this->RegisterPropertyFloat('Preset2Value', 0.0);
        $this->RegisterPropertyString('Preset3Label', '');
        $this->RegisterPropertyFloat('Preset3Value', 0.0);
        $this->RegisterPropertyString('Preset4Label', '');
        $this->RegisterPropertyFloat('Preset4Value', 0.0);

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

        $anyLinked = false;
        foreach ([$this->ReadPropertyInteger('PositionID'), $this->ReadPropertyInteger('SlatsID')] as $varID) {
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
                    $cfg = $this->GetVarConfig($varID);
                    $var = IPS_GetVariable($varID);
                    $val = $var['VariableType'] === 2
                        ? (float) max($cfg['min'], min($cfg['max'], (float) $Value))
                        : (int)   round(max($cfg['min'], min($cfg['max'], (float) $Value)));
                    RequestAction($varID, $val);
                }
                break;

            case 'SetSlats':
                $varID = $this->ReadPropertyInteger('SlatsID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    $cfg = $this->GetVarConfig($varID);
                    $var = IPS_GetVariable($varID);
                    $val = $var['VariableType'] === 2
                        ? (float) max($cfg['min'], min($cfg['max'], (float) $Value))
                        : (int)   round(max($cfg['min'], min($cfg['max'], (float) $Value)));
                    RequestAction($varID, $val);
                }
                break;
        }
    }

    public function GetVisualizationTile()
    {
        $posCfg = $this->GetVarConfig($this->ReadPropertyInteger('PositionID'));

        $configJson = json_encode([
            'min'    => $posCfg['min'],
            'max'    => $posCfg['max'],
            'step'   => $posCfg['step'],
            'invert' => $this->ReadPropertyBoolean('Invert'),
        ]);
        $dataJsonLiteral = json_encode(json_encode($this->GetCurrentData()));

        $module = file_get_contents(__DIR__ . '/module.html');
        $init   = '<script>var _config = ' . $configJson . '; handleMessage(' . $dataJsonLiteral . ');</script>';

        return $module . $init;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function GetVarConfig(int $varID, float $defaultMin = 0.0, float $defaultMax = 100.0, float $defaultStep = 1.0): array
    {
        $config = ['min' => $defaultMin, 'max' => $defaultMax, 'step' => $defaultStep];

        if ($varID <= 0 || !IPS_VariableExists($varID)) {
            return $config;
        }

        // Priorität 2: Variablen-Profil (Custom hat Vorrang)
        $var         = IPS_GetVariable($varID);
        $profileName = $var['VariableCustomProfile'] !== ''
            ? $var['VariableCustomProfile']
            : $var['VariableProfile'];

        if ($profileName !== '' && IPS_VariableProfileExists($profileName)) {
            $profile         = IPS_GetVariableProfile($profileName);
            $config['min']   = (float) $profile['MinValue'];
            $config['max']   = (float) $profile['MaxValue'];
            if ($profile['StepSize'] > 0) {
                $config['step'] = (float) $profile['StepSize'];
            }
        }

        // Priorität 1: IPS 7 Variablen-Darstellung (höchste Priorität)
        if (function_exists('IPS_GetVariablePresentation')) {
            $pres = IPS_GetVariablePresentation($varID);
            if (is_array($pres)) {
                if (isset($pres['MinValue']) && $pres['MinValue'] !== null) {
                    $config['min']  = (float) $pres['MinValue'];
                }
                if (isset($pres['MaxValue']) && $pres['MaxValue'] !== null) {
                    $config['max']  = (float) $pres['MaxValue'];
                }
                if (isset($pres['StepSize']) && $pres['StepSize'] > 0) {
                    $config['step'] = (float) $pres['StepSize'];
                }
            }
        }

        return $config;
    }

    private function GetCurrentData(): array
    {
        $presets = [];
        for ($i = 1; $i <= 4; $i++) {
            $label = $this->ReadPropertyString('Preset' . $i . 'Label');
            if ($label !== '') {
                $presets[] = [
                    'label' => $label,
                    'value' => $this->ReadPropertyFloat('Preset' . $i . 'Value'),
                ];
            }
        }

        $data = [
            'name'     => $this->ReadPropertyString('TileName'),
            'position' => null,
            'slats'    => null,
            'presets'  => $presets,
            'colors'   => [
                'accent'  => $this->ReadPropertyInteger('ColorAccent'),
            ],
        ];

        $positionID = $this->ReadPropertyInteger('PositionID');
        if ($positionID > 0 && IPS_VariableExists($positionID)) {
            $var = IPS_GetVariable($positionID);
            $data['position'] = $var['VariableType'] === 2
                ? round((float) GetValue($positionID), 1)
                : (int) GetValue($positionID);
        }

        $slatsID = $this->ReadPropertyInteger('SlatsID');
        if ($slatsID > 0 && IPS_VariableExists($slatsID)) {
            $var = IPS_GetVariable($slatsID);
            $data['slats'] = $var['VariableType'] === 2
                ? round((float) GetValue($slatsID), 1)
                : (int) GetValue($slatsID);
        }

        return $data;
    }
}
