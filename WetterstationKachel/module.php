<?php

class WetterstationKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Wetterstation');
        $this->RegisterPropertyInteger('TemperatureID',   0);
        $this->RegisterPropertyInteger('HumidityID',      0);
        $this->RegisterPropertyInteger('WindSpeedID',     0);
        $this->RegisterPropertyInteger('WindDirectionID', 0);
        $this->RegisterPropertyInteger('RainID',          0);
        $this->RegisterPropertyInteger('PressureID',      0);
        $this->RegisterPropertyInteger('UVIndexID',       0);
        $this->RegisterPropertyInteger('ColorBackground', 1843237);
        $this->RegisterPropertyInteger('ColorText',       16777215);
        $this->RegisterPropertyInteger('ColorTextSub',    9214891);
        $this->RegisterPropertyInteger('ColorAccent',     2733814);

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
        $props = ['TemperatureID','HumidityID','WindSpeedID','WindDirectionID','RainID','PressureID','UVIndexID'];
        foreach ($props as $prop) {
            $varID = $this->ReadPropertyInteger($prop);
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

    public function GetVisualizationTile()
    {
        $dataJsonLiteral = json_encode(json_encode($this->GetCurrentData()));
        $module = file_get_contents(__DIR__ . '/module.html');
        $init   = '<script>handleMessage(' . $dataJsonLiteral . ');</script>';
        return $module . $init;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function getVarData(int $varID): ?array
    {
        if ($varID <= 0 || !IPS_VariableExists($varID)) {
            return null;
        }

        $value   = GetValue($varID);
        $suffix  = '';
        $digits  = 0;

        // Priorität 1: IPS 7 Variablendarstellung
        if (function_exists('IPS_GetVariablePresentation')) {
            $pres = IPS_GetVariablePresentation($varID);
            foreach (['SUFFIX', 'Suffix'] as $key) {
                if (isset($pres[$key]) && $pres[$key] !== '') {
                    $suffix = $pres[$key];
                    break;
                }
            }
        }

        // Priorität 2: Custom Profile / Standard Profile
        $var         = IPS_GetVariable($varID);
        $profileName = $var['VariableCustomProfile'] !== '' ? $var['VariableCustomProfile'] : $var['VariableProfile'];
        if ($profileName !== '') {
            $profile = IPS_GetVariableProfile($profileName);
            if ($suffix === '') {
                $suffix = $profile['Suffix'] ?? '';
            }
            $digits = $profile['Digits'] ?? 0;
        }

        return [
            'value'  => $value,
            'suffix' => trim($suffix),
            'digits' => (int) $digits,
        ];
    }

    private function GetCurrentData(): array
    {
        return [
            'name'         => $this->ReadPropertyString('TileName'),
            'temperature'  => $this->getVarData($this->ReadPropertyInteger('TemperatureID')),
            'humidity'     => $this->getVarData($this->ReadPropertyInteger('HumidityID')),
            'windSpeed'    => $this->getVarData($this->ReadPropertyInteger('WindSpeedID')),
            'windDir'      => $this->getVarData($this->ReadPropertyInteger('WindDirectionID')),
            'rain'         => $this->getVarData($this->ReadPropertyInteger('RainID')),
            'pressure'     => $this->getVarData($this->ReadPropertyInteger('PressureID')),
            'uvIndex'      => $this->getVarData($this->ReadPropertyInteger('UVIndexID')),
            'colors'       => [
                'bg'      => $this->ReadPropertyInteger('ColorBackground'),
                'text'    => $this->ReadPropertyInteger('ColorText'),
                'textSub' => $this->ReadPropertyInteger('ColorTextSub'),
                'accent'  => $this->ReadPropertyInteger('ColorAccent'),
            ],
        ];
    }
}
