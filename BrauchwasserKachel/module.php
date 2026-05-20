<?php

class BrauchwasserKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('TileName', 'Brauchwasser');
        $this->RegisterPropertyInteger('TempTopID',  0);
        $this->RegisterPropertyInteger('PumpID',     0);
        $this->RegisterPropertyInteger('TempOnID',   0);
        $this->RegisterPropertyInteger('TempOffID',  0);
        $this->RegisterPropertyInteger('ColorAccent',     16724736);
        $this->RegisterPropertyInteger('ColorPumpOn',     2201331);

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
            $this->ReadPropertyInteger('TempTopID'),
            $this->ReadPropertyInteger('PumpID'),
            $this->ReadPropertyInteger('TempOnID'),
            $this->ReadPropertyInteger('TempOffID'),
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
            'name'    => $this->ReadPropertyString('TileName'),
            'tempTop' => null,
            'pumpOn'  => null,
            'tempOn'  => null,
            'tempOff' => null,
            'colors'  => [
                'accent'  => $this->ReadPropertyInteger('ColorAccent'),
                'pumpOn'  => $this->ReadPropertyInteger('ColorPumpOn'),
            ],
        ];

        $tempTopID = $this->ReadPropertyInteger('TempTopID');
        if ($tempTopID > 0 && IPS_VariableExists($tempTopID)) {
            $data['tempTop'] = round((float) GetValue($tempTopID), 1);
        }

        $pumpID = $this->ReadPropertyInteger('PumpID');
        if ($pumpID > 0 && IPS_VariableExists($pumpID)) {
            $var = IPS_GetVariable($pumpID);
            $data['pumpOn'] = (bool) GetValue($pumpID);
        }

        $tempOnID = $this->ReadPropertyInteger('TempOnID');
        if ($tempOnID > 0 && IPS_VariableExists($tempOnID)) {
            $data['tempOn'] = round((float) GetValue($tempOnID), 1);
        }

        $tempOffID = $this->ReadPropertyInteger('TempOffID');
        if ($tempOffID > 0 && IPS_VariableExists($tempOffID)) {
            $data['tempOff'] = round((float) GetValue($tempOffID), 1);
        }

        return $data;
    }
}
