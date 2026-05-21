<?php

require_once __DIR__ . '/../libs/SymconHelper/dimDevice.php';

class RolloKachel extends IPSModule
{
    use HelperDimDevice;

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
                    $pct = (float) $Value;
                    if ($this->ReadPropertyBoolean('Invert')) {
                        $pct = 100 - $pct;
                    }
                    $absoluteValue = self::percentToAbsolute($varID, $pct);
                    if ($absoluteValue === false) {
                        break;
                    }
                    $var = IPS_GetVariable($varID);
                    $val = $var['VariableType'] === VARIABLETYPE_FLOAT
                        ? (float) $absoluteValue
                        : (int) round($absoluteValue);
                    RequestAction($varID, $val);
                }
                break;

            case 'SetSlats':
                $varID = $this->ReadPropertyInteger('SlatsID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    $absoluteValue = self::percentToAbsolute($varID, (float) $Value);
                    if ($absoluteValue === false) {
                        break;
                    }
                    $var = IPS_GetVariable($varID);
                    $val = $var['VariableType'] === VARIABLETYPE_FLOAT
                        ? (float) $absoluteValue
                        : (int) round($absoluteValue);
                    RequestAction($varID, $val);
                }
                break;
        }
    }

    public function GetVisualizationTile()
    {
        $posCfg = $this->GetVarConfig($this->ReadPropertyInteger('PositionID'));
        $range  = $posCfg['max'] - $posCfg['min'];
        $stepPct = ($range > 0) ? round($posCfg['step'] / $range * 100, 4) : 1.0;
        $stepPct = max(0.01, $stepPct);

        $configJson      = json_encode([
            'step' => $stepPct,
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
                'accent' => $this->ReadPropertyInteger('ColorAccent'),
            ],
        ];

        $positionID = $this->ReadPropertyInteger('PositionID');
        if ($positionID > 0 && IPS_VariableExists($positionID)) {
            $dimVal = self::getDimValue($positionID);
            if ($this->ReadPropertyBoolean('Invert')) {
                $dimVal = 100 - $dimVal;
            }
            $data['position'] = round($dimVal, 1);
        }

        $slatsID = $this->ReadPropertyInteger('SlatsID');
        if ($slatsID > 0 && IPS_VariableExists($slatsID)) {
            $data['slats'] = round(self::getDimValue($slatsID), 1);
        }

        return $data;
    }
}
