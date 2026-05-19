<?php

class RolloKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Rollo');
        $this->RegisterPropertyInteger('PositionID', 0);
        $this->RegisterPropertyInteger('SlatsID', 0);
        $this->RegisterPropertyInteger('OpenValue', 0);
        $this->RegisterPropertyInteger('CloseValue', 100);
        $this->RegisterPropertyInteger('PositionStep', 10);
        $this->RegisterPropertyInteger('ColorBackground', 1843237);
        $this->RegisterPropertyInteger('ColorText', 16777215);
        $this->RegisterPropertyInteger('ColorTextSub', 9214891);
        $this->RegisterPropertyInteger('ColorAccent', 2201331);

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
                    RequestAction($varID, max(0, min(100, (int) $Value)));
                }
                break;

            case 'SetSlats':
                $varID = $this->ReadPropertyInteger('SlatsID');
                if ($varID > 0 && IPS_VariableExists($varID)) {
                    RequestAction($varID, max(0, min(100, (int) $Value)));
                }
                break;
        }
    }

    public function GetVisualizationTile()
    {
        $configJson      = json_encode([
            'openValue'  => $this->ReadPropertyInteger('OpenValue'),
            'closeValue' => $this->ReadPropertyInteger('CloseValue'),
            'step'       => $this->ReadPropertyInteger('PositionStep'),
        ]);
        $dataJsonLiteral = json_encode(json_encode($this->GetCurrentData()));

        $module = file_get_contents(__DIR__ . '/module.html');
        $init   = '<script>var _config = ' . $configJson . '; handleMessage(' . $dataJsonLiteral . ');</script>';

        return $module . $init;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function GetCurrentData(): array
    {
        $data = [
            'name'     => $this->ReadPropertyString('TileName'),
            'position' => null,
            'slats'    => null,
            'colors'   => [
                'bg'      => $this->ReadPropertyInteger('ColorBackground'),
                'text'    => $this->ReadPropertyInteger('ColorText'),
                'textSub' => $this->ReadPropertyInteger('ColorTextSub'),
                'accent'  => $this->ReadPropertyInteger('ColorAccent'),
            ],
        ];

        $positionID = $this->ReadPropertyInteger('PositionID');
        if ($positionID > 0 && IPS_VariableExists($positionID)) {
            $data['position'] = (int) GetValue($positionID);
        }

        $slatsID = $this->ReadPropertyInteger('SlatsID');
        if ($slatsID > 0 && IPS_VariableExists($slatsID)) {
            $data['slats'] = (int) GetValue($slatsID);
        }

        return $data;
    }
}
