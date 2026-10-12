<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';

//Diagnose des Servers: Log-Meldungen (live über den WebSocket) und EPG-Grabber.
class TvheadendDiagnostics extends IPSModuleStrict
{
    use TvheadendChild;

    //Zeilen in der HTML-Liste.
    private const LIST_LIMIT = 20;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('Keywords', 'error,failed,fail,unable,cannot,timeout,invalid,warning');
        $this->RegisterPropertyInteger('KeepLines', 100);
        $this->RegisterPropertyBoolean('DebugAllLines', false);

        //Letzte Problemzeilen: [{t, m}].
        $this->RegisterAttributeString('Problems', '[]');

        $this->RegisterVariableInteger('ProblemCount', $this->Translate('Problem Lines'), $this->ValuePresentation(), 1);
        $this->RegisterVariableString('LastProblem', $this->Translate('Last Problem'), $this->ValuePresentation(), 2);
        $this->RegisterVariableInteger('LastProblemTime', $this->Translate('Last Problem - Time'), $this->DateTimePresentation(), 3);
        $this->RegisterVariableString('ProblemList', $this->Translate('Problems'), $this->HtmlPresentation(), 4);
        $this->RegisterVariableString('Grabbers', $this->Translate('EPG Grabbers'), $this->HtmlPresentation(), 5);
        $this->RegisterVariableInteger('NextOtaGrab', $this->Translate('Next Over-the-air EPG Grab'), $this->DateTimePresentation(), 6);
        $this->RegisterVariableInteger('NextInternalGrab', $this->Translate('Next Internal EPG Grab'), $this->DateTimePresentation(), 7);
        $this->RegisterVariableInteger('NextMuxScan', $this->Translate('Next Mux Scan'), $this->DateTimePresentation(), 8);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() === KR_READY && $this->HasActiveParent()) {
            $this->Update();
        }
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        switch ($data['Action'] ?? '') {
            case TVH_ACTION_SYNC:
                if (($data['Online'] ?? false) === true) {
                    $this->Update();
                }
                break;
            case TVH_ACTION_NOTIFY:
                if (($data['Class'] ?? '') === 'logmessage') {
                    $this->HandleLogLine((string) ($data['Message']['logtxt'] ?? ''));
                }
                break;
        }
        return '';
    }

    //Liest EPG-Grabber und nächste Aktivitäten vom Server.
    public function Update(): void
    {
        if (!($this->GetServerInfo()['admin'] ?? false)) {
            $this->SendDebug(__FUNCTION__, 'User is not an administrator', 0);
            return;
        }

        $result = $this->ApiRequest('api/epggrab/module/list');
        if ($result['code'] === 200 && is_array($result['data'])) {
            $rows = [];
            foreach ((array) ($result['data']['entries'] ?? []) as $module) {
                //Nur die Grabber, die eingeschaltet sind.
                if (($module['status'] ?? '') === 'epggrabmodEnabled') {
                    $rows[] = [(string) ($module['title'] ?? '')];
                }
            }
            $this->SetValue('Grabbers', $this->BuildTable(['Enabled EPG grabbers'], $rows));
        }

        $result = $this->ApiRequest('api/status/activity');
        if ($result['code'] === 200 && is_array($result['data'])) {
            $activities = (array) ($result['data']['activities'] ?? []);
            $this->SetValue('NextOtaGrab', (int) ($activities['ota_grabber'] ?? 0));
            $this->SetValue('NextInternalGrab', (int) ($activities['int_grabber'] ?? 0));
            $this->SetValue('NextMuxScan', (int) ($activities['mux_scheduler'] ?? 0));
        }
    }

    //Die gesammelten Problemzeilen als JSON: [{t, m}], neueste zuerst.
    public function GetProblems(): string
    {
        return json_encode(array_reverse($this->GetProblemLines()));
    }

    //Setzt Zähler und Liste der Problemzeilen zurück.
    public function ClearProblems(): void
    {
        $this->WriteAttributeString('Problems', '[]');
        $this->UpdateProblemVariables([]);
    }

    //Startet den EPG-Grabber über die Luft (OTA) nach $DelaySeconds Sekunden (1 Sekunde bis eine Woche).
    public function TriggerOtaGrab(int $DelaySeconds = 1): bool
    {
        $result = $this->ApiRequest('api/epggrab/ota/trigger', ['trigger' => max(1, min(604800, $DelaySeconds))], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    //Startet die internen EPG-Grabber (zum Beispiel XMLTV) sofort.
    public function RerunInternalGrabbers(): bool
    {
        $result = $this->ApiRequest('api/epggrab/internal/rerun', ['rerun' => 1], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Update':
                $this->Update();
                break;
        }
    }

    //Merkt Zeilen, die eines der Stichwörter enthalten. Der Server meldet beim Log keine Schwere: Die Zeilen
    //sehen aus wie "2026-10-12 17:56:47.772 api: failed to find subsystem".
    private function HandleLogLine(string $Line): void
    {
        if ($Line === '') {
            return;
        }
        if ($this->ReadPropertyBoolean('DebugAllLines')) {
            $this->SendDebug('Log', $Line, 0);
        }
        $matches = false;
        foreach (array_filter(array_map('trim', explode(',', $this->ReadPropertyString('Keywords')))) as $keyword) {
            if (stripos($Line, $keyword) !== false) {
                $matches = true;
                break;
            }
        }
        if (!$matches) {
            return;
        }

        $lines = $this->GetProblemLines();
        $lines[] = ['t' => time(), 'm' => $Line];
        $lines = array_slice($lines, -max(10, $this->ReadPropertyInteger('KeepLines')));
        $this->WriteAttributeString('Problems', json_encode($lines));
        $this->UpdateProblemVariables($lines);
    }

    private function GetProblemLines(): array
    {
        return (array) json_decode($this->ReadAttributeString('Problems'), true);
    }

    private function UpdateProblemVariables(array $Lines): void
    {
        $this->SetValue('ProblemCount', count($Lines));
        $last = $Lines[count($Lines) - 1] ?? null;
        $this->SetValue('LastProblem', $last['m'] ?? '');
        $this->SetValue('LastProblemTime', (int) ($last['t'] ?? 0));

        $rows = [];
        foreach (array_slice(array_reverse($Lines), 0, self::LIST_LIMIT) as $line) {
            $rows[] = [date('d.m.Y H:i:s', (int) $line['t']), $line['m']];
        }
        $this->SetValue('ProblemList', $this->BuildTable(['Time', 'Message'], $rows));
    }
}
