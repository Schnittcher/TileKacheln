<?php

class HeizoeltankKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Heizöltank');
        $this->RegisterPropertyInteger('FillLevelID',       0);
        $this->RegisterPropertyInteger('ColorAccent',     14710784);

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

        $varID = $this->ReadPropertyInteger('FillLevelID');
        if ($varID > 0 && IPS_VariableExists($varID)) {
            $this->RegisterMessage($varID, VM_UPDATE);
            $this->SetStatus(102);
        } else {
            $this->SetStatus(201);
        }

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

    private function GetCurrentData(): array
    {
        $data = [
            'name'   => $this->ReadPropertyString('TileName'),
            'value'  => null,
            'suffix' => '',
            'digits' => 0,
            'pct'    => null,
            'colors' => [
                'accent'  => $this->ReadPropertyInteger('ColorAccent'),
            ],
        ];

        $varID = $this->ReadPropertyInteger('FillLevelID');
        if ($varID <= 0 || !IPS_VariableExists($varID)) {
            return $data;
        }

        $data['value'] = GetValue($varID);
        $suffix        = '';
        $min           = 0;
        $max           = 100;
        $digits        = 0;

        // Priorität 2: Custom Profile / Standard Profile
        $var         = IPS_GetVariable($varID);
        $profileName = $var['VariableCustomProfile'] !== '' ? $var['VariableCustomProfile'] : $var['VariableProfile'];
        if ($profileName !== '') {
            $profile = IPS_GetVariableProfile($profileName);
            $suffix  = $profile['Suffix']   ?? '';
            $min     = $profile['MinValue'] ?? 0;
            $max     = $profile['MaxValue'] ?? 100;
            $digits  = $profile['Digits']   ?? 0;
        }

        // Priorität 1: IPS 7 Variablendarstellung (überschreibt Profil-Werte)
        if (function_exists('IPS_GetVariablePresentation')) {
            $pres = IPS_GetVariablePresentation($varID);
            if (is_array($pres)) {
                foreach (['SUFFIX', 'Suffix'] as $key) {
                    if (isset($pres[$key]) && $pres[$key] !== '') {
                        $suffix = $pres[$key];
                        break;
                    }
                }
                if (isset($pres['MIN']) && $pres['MIN'] !== null) {
                    $min = (float) $pres['MIN'];
                }
                if (isset($pres['MAX']) && $pres['MAX'] !== null) {
                    $max = (float) $pres['MAX'];
                }
                if (isset($pres['Digits']) && $pres['Digits'] !== null) {
                    $digits = (int) $pres['Digits'];
                }
            }
        }

        $data['suffix'] = trim($suffix);
        $data['digits'] = (int) $digits;

        if ($max > $min) {
            $pct         = ($data['value'] - $min) / ($max - $min) * 100;
            $data['pct'] = (int) round(max(0, min(100, $pct)));
        }

        return $data;
    }
}
