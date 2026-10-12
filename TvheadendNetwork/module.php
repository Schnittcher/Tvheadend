<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';

//Ein Netzwerk des Servers (DVB-S, DVB-C, IPTV, ...): Anzahl und Zustand der Muxe, Services, Scan.
class TvheadendNetwork extends IPSModuleStrict
{
    use TvheadendChild;

    //Werte von scan_result bei den Muxen.
    private const SCAN_OK = 1;
    private const SCAN_FAILED = 2;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('NetworkUuid', '');
        $this->RegisterPropertyInteger('UpdateInterval', 300);

        $this->RegisterVariableBoolean('Enabled', $this->Translate('Enabled'), $this->BooleanPresentation('Yes', 'No'), 1);
        $this->RegisterVariableInteger('Muxes', $this->Translate('Muxes'), $this->ValuePresentation(), 2);
        $this->RegisterVariableInteger('MuxesOk', $this->Translate('Muxes - Scan OK'), $this->ValuePresentation(), 3);
        $this->RegisterVariableInteger('MuxesFailed', $this->Translate('Muxes - Scan Failed'), $this->ValuePresentation(), 4);
        $this->RegisterVariableInteger('Services', $this->Translate('Services'), $this->ValuePresentation(), 5);
        $this->RegisterVariableInteger('Channels', $this->Translate('Mapped Channels'), $this->ValuePresentation(), 6);
        $this->RegisterVariableInteger('ScanQueue', $this->Translate('Scan Queue'), $this->ValuePresentation(), 7);
        $this->RegisterVariableBoolean('Scanning', $this->Translate('Scanning'), $this->BooleanPresentation('Yes', 'No'), 8);
        $this->RegisterVariableInteger('LastScan', $this->Translate('Last Scan'), $this->DateTimePresentation(), 9);

        //Muxe und Services ändern sich selten: ein langsamer Abgleich genügt, den Zustand eines Scans zeigt er mit.
        $this->RegisterTimer('Poll', 0, 'TVHN_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if ($this->ReadPropertyString('NetworkUuid') === '') {
            $this->SetTimerInterval('Poll', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        $this->SetStatus(IS_ACTIVE);
        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval('Poll', $interval > 0 ? max(30, $interval) * 1000 : 0);

        if (IPS_GetKernelRunlevel() === KR_READY && $this->HasActiveParent()) {
            $this->Update();
        }
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (($data['Action'] ?? '') === TVH_ACTION_SYNC && ($data['Online'] ?? false) === true) {
            $this->Update();
        }
        return '';
    }

    //Liest das Netzwerk und seine Muxe vom Server.
    public function Update(): void
    {
        if ($this->GetStatus() !== IS_ACTIVE) {
            return;
        }
        $uuid = $this->ReadPropertyString('NetworkUuid');
        $networks = $this->ApiRequest('api/mpegts/network/grid', ['limit' => 200]);
        if ($networks['code'] === 403) {
            $this->SendDebug(__FUNCTION__, 'mpegts/network/grid needs administrator rights', 0);
            return;
        }
        if ($networks['code'] !== 200 || !is_array($networks['data'])) {
            return;
        }
        $network = null;
        foreach ((array) ($networks['data']['entries'] ?? []) as $entry) {
            if (($entry['uuid'] ?? '') === $uuid) {
                $network = $entry;
                break;
            }
        }
        if ($network === null) {
            $this->SendDebug(__FUNCTION__, 'Network not found on the server', 0);
            return;
        }

        $this->SetValue('Enabled', !empty($network['enabled']));
        $this->SetValue('Muxes', (int) ($network['num_mux'] ?? 0));
        $this->SetValue('Services', (int) ($network['num_svc'] ?? 0));
        $this->SetValue('Channels', (int) ($network['num_chn'] ?? 0));
        $queue = (int) ($network['scanq_length'] ?? 0);
        $this->SetValue('ScanQueue', $queue);

        $ok = 0;
        $failed = 0;
        $pending = 0;
        $last = 0;
        $muxes = $this->ApiRequest('api/mpegts/mux/grid', ['limit' => 5000]);
        if ($muxes['code'] === 200 && is_array($muxes['data'])) {
            foreach ((array) ($muxes['data']['entries'] ?? []) as $mux) {
                if (($mux['network_uuid'] ?? '') !== $uuid) {
                    continue;
                }
                $result = (int) ($mux['scan_result'] ?? 0);
                $ok += $result === self::SCAN_OK ? 1 : 0;
                $failed += $result === self::SCAN_FAILED ? 1 : 0;
                //scan_state: 0 = ruht, 1 = wartet in der Warteschlange, 2 = läuft.
                $pending += (int) ($mux['scan_state'] ?? 0) > 0 ? 1 : 0;
                $last = max($last, (int) ($mux['scan_last'] ?? 0));
            }
        }
        $this->SetValue('MuxesOk', $ok);
        $this->SetValue('MuxesFailed', $failed);
        $this->SetValue('Scanning', $queue > 0 || $pending > 0);
        $this->SetValue('LastScan', $last);
    }

    //Startet einen Scan des Netzwerks. Die Muxe werden nacheinander abgefragt.
    public function StartScan(): bool
    {
        $result = $this->ApiRequest('api/mpegts/network/scan', ['uuid' => [$this->ReadPropertyString('NetworkUuid')]], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Update':
                $this->Update();
                break;
            case 'StartScan':
                $this->StartScan();
                break;
        }
    }
}
