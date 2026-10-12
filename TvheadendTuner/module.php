<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';
require_once __DIR__ . '/../libs/TvheadendTile.php';

//Alle Tuner des Servers in einer Instanz: Zusammenfassung, Variablen je Tuner, HTML-Liste und Kachel. Die Instanz zeigt
//nur an: Tuner aktiviert und deaktiviert man im Tvheadend (Konfiguration, DVB-Eingänge, TV-Adapter).
class TvheadendTuner extends IPSModuleStrict
{
    use TvheadendChild;
    use TvheadendTile;

    //Klassen von hardware/tree, die einen Tuner (Frontend) beschreiben, und die Tiefe, bis zu der der Baum gelesen wird.
    private const FRONTEND_PATTERN = '/frontend/i';
    private const TREE_DEPTH = 3;

    //Mindestabstand zwischen zwei Aktualisierungen der Kachel durch Meldungen (Sekunden). Der Server meldet belegte
    //Tuner im Sekundentakt.
    private const TILE_INTERVAL = 2;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyBoolean('CreateVariables', true);

        //Tuner aus dem Hardwarebaum: UUID => {name, enabled}. Letzter Zustand je Tuner aus status/inputs: Name => {...}.
        $this->RegisterAttributeString('Frontends', '{}');
        $this->RegisterAttributeString('State', '{}');
        //Skala von Signal und SNR je Variable (1 = relativ, 2 = dB), damit die Einheit passt.
        $this->RegisterAttributeString('Scales', '{}');

        $this->RegisterVariableInteger('TunerCount', $this->Translate('Tuners'), $this->ValuePresentation(), 1);
        $this->RegisterVariableInteger('EnabledCount', $this->Translate('Enabled Tuners'), $this->ValuePresentation(), 2);
        $this->RegisterVariableInteger('BusyCount', $this->Translate('Busy Tuners'), $this->ValuePresentation(), 3);
        $this->RegisterVariableInteger('FreeCount', $this->Translate('Free Tuners'), $this->ValuePresentation(), 4);
        $this->RegisterVariableString('TunerList', $this->Translate('Tuner List'), $this->HtmlPresentation(), 5);

        $this->RegisterTimer('Poll', 0, 'TVHT_Update($_IPS[\'TARGET\']);');
        //Pusht nach einer Flut von Meldungen einmal den Endstand an die Kachel.
        $this->RegisterTimer('Refresh', 0, 'TVHT_RefreshLater($_IPS[\'TARGET\']);');

        $this->EnableTile();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval('Poll', $interval > 0 ? max(10, $interval) * 1000 : 0);

        if (IPS_GetKernelRunlevel() === KR_READY && $this->HasActiveParent()) {
            $this->LoadFrontends();
            $this->Update();
        } else {
            $this->MaintainTunerVariables();
        }
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->BuildTileData());
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        switch ($data['Action'] ?? '') {
            case TVH_ACTION_SYNC:
                if (($data['Online'] ?? false) === true) {
                    $this->LoadFrontends();
                    $this->Update();
                }
                break;
            case TVH_ACTION_NOTIFY:
                if (($data['Class'] ?? '') === 'input_status') {
                    //Der Server meldet jede Sekunde den Zustand der belegten Eingänge samt Werten. "reload" heißt, dass
                    //sich die Liste geändert hat.
                    $message = (array) ($data['Message'] ?? []);
                    if (isset($message['reload'])) {
                        $this->Update();
                    } elseif (isset($message['input'])) {
                        $this->ApplyEntry($message);
                        $this->PushTileThrottled();
                    }
                }
                break;
        }
        return '';
    }

    //Liest den Zustand aller Tuner aus status/inputs.
    public function Update(): void
    {
        $result = $this->ApiRequest('api/status/inputs');
        if ($result['code'] === 403) {
            $this->SendDebug(__FUNCTION__, 'status/inputs needs administrator rights', 0);
            return;
        }
        if ($result['code'] !== 200 || !is_array($result['data'])) {
            return;
        }

        //Erst alle gemeldeten Tuner bekannt machen und ihre Variablen anlegen, danach die Werte setzen.
        $entries = array_values(array_filter((array) ($result['data']['entries'] ?? []), fn ($entry): bool => !empty($entry['input'])));
        $state = $this->GetState();
        foreach ($entries as $entry) {
            $state[(string) $entry['input']] = $state[(string) $entry['input']] ?? $this->IdleState();
        }
        $this->WriteAttributeString('State', json_encode($state));
        $this->MaintainTunerVariables();

        $seen = [];
        foreach ($entries as $entry) {
            $seen[(string) $entry['input']] = true;
            $this->ApplyEntry($entry, false);
        }
        //Tuner, die der Server nicht aufführt, sind frei.
        $state = $this->GetState();
        foreach ($this->TunerNames() as $name) {
            if (!isset($seen[$name])) {
                $state[$name] = array_merge($state[$name] ?? [], $this->IdleState());
            }
        }
        $this->WriteAttributeString('State', json_encode($state));
        foreach ($this->TunerNames() as $name) {
            $this->SetTunerVariables($name, $state[$name] ?? $this->IdleState());
        }
        $this->UpdateSummary();
    }

    //Wird vom Timer nach einer Meldungsflut aufgerufen.
    public function RefreshLater(): void
    {
        $this->SetTimerInterval('Refresh', 0);
        $this->UpdateSummary();
        $this->PushTile($this->BuildTileData());
    }

    //Der Zustand aller Tuner als JSON: Name => {uuid, enabled, busy, subs, mux, bps, sig, snr, ...}.
    public function GetTuners(): string
    {
        $tuners = [];
        $state = $this->GetState();
        foreach ($this->Tuners() as $tuner) {
            $tuners[$tuner['name']] = ['uuid' => $tuner['uuid'], 'enabled' => $tuner['enabled']] + ($state[$tuner['name']] ?? $this->IdleState());
        }
        return json_encode($tuners);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Update':
                $this->LoadFrontends();
                $this->Update();
                break;
        }
    }

    private function IdleState(): array
    {
        return ['busy' => false, 'subs' => 0, 'mux' => '', 'bps' => 0];
    }

    //Übernimmt einen Eintrag aus status/inputs oder aus der Meldung input_status.
    private function ApplyEntry(array $Entry, bool $Summary = true): void
    {
        $name = (string) ($Entry['input'] ?? '');
        if ($name === '') {
            return;
        }
        $state = $this->GetState();
        $subs = (int) ($Entry['subs'] ?? 0);
        $previous = $state[$name] ?? [];
        $current = [
            'busy' => $subs > 0,
            'subs' => $subs,
            'mux'  => (string) ($Entry['stream'] ?? ''),
            'bps'  => (int) ($Entry['bps'] ?? 0),
            'sig'  => (int) ($Entry['signal'] ?? 0),
            'ss'   => (int) ($Entry['signal_scale'] ?? 0),
            'snr'  => (int) ($Entry['snr'] ?? 0),
            'ns'   => (int) ($Entry['snr_scale'] ?? 0),
            'ber'  => (int) ($Entry['ber'] ?? 0),
            'unc'  => (int) ($Entry['unc'] ?? 0),
            'cc'   => (int) ($Entry['cc'] ?? 0),
            'te'   => (int) ($Entry['te'] ?? 0)
        ];
        //Eine Skala 0 heißt "noch nicht gemessen": den letzten Wert behalten.
        foreach (['ss' => 'sig', 'ns' => 'snr'] as $scaleKey => $valueKey) {
            if ($current[$scaleKey] === 0 && isset($previous[$scaleKey])) {
                $current[$scaleKey] = $previous[$scaleKey];
                $current[$valueKey] = $previous[$valueKey] ?? 0;
            }
        }
        $state[$name] = $current;
        $this->WriteAttributeString('State', json_encode($state));
        $this->SetTunerVariables($name, $current);
        if ($Summary) {
            $this->UpdateSummary();
        }
    }

    private function GetState(): array
    {
        return (array) json_decode($this->ReadAttributeString('State'), true);
    }

    //Alle bekannten Tuner nach Name sortiert: Name, UUID (leer, wenn nur status/inputs ihn kennt), enabled.
    private function Tuners(): array
    {
        $tuners = [];
        foreach ((array) json_decode($this->ReadAttributeString('Frontends'), true) as $uuid => $frontend) {
            if (!is_array($frontend)) {
                continue;
            }
            $tuners[(string) $frontend['name']] = ['name' => (string) $frontend['name'], 'uuid' => (string) $uuid, 'enabled' => (bool) ($frontend['enabled'] ?? true)];
        }
        foreach (array_keys($this->GetState()) as $name) {
            $tuners[(string) $name] = $tuners[(string) $name] ?? ['name' => (string) $name, 'uuid' => '', 'enabled' => true];
        }
        uasort($tuners, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return array_values($tuners);
    }

    private function TunerNames(): array
    {
        return array_map(fn (array $tuner): string => $tuner['name'], $this->Tuners());
    }

    private function UpdateSummary(): void
    {
        $state = $this->GetState();
        $tuners = $this->Tuners();
        $enabled = 0;
        $busy = 0;
        $rows = [];
        foreach ($tuners as $tuner) {
            $entry = $state[$tuner['name']] ?? [];
            $isBusy = !empty($entry['busy']);
            $enabled += $tuner['enabled'] ? 1 : 0;
            $busy += $isBusy ? 1 : 0;
            $rows[] = [
                $tuner['name'],
                !$tuner['enabled'] ? $this->Translate('Disabled') : ($isBusy ? $this->Translate('Busy') : $this->Translate('Free')),
                $this->FormatScaled((int) ($entry['sig'] ?? 0), (int) ($entry['ss'] ?? 0)),
                $this->FormatScaled((int) ($entry['snr'] ?? 0), (int) ($entry['ns'] ?? 0)),
                (int) round(((int) ($entry['bps'] ?? 0)) / 1000) . ' kbit/s',
                $entry['mux'] ?? ''
            ];
        }
        $this->SetValue('TunerCount', count($tuners));
        $this->SetValue('EnabledCount', $enabled);
        $this->SetValue('BusyCount', $busy);
        $this->SetValue('FreeCount', max(0, $enabled - $busy));
        $this->SetValue('TunerList', $this->BuildTable(['Tuner', 'State', 'Signal', 'SNR', 'Bitrate', 'Mux'], $rows));
    }

    private function PushTileThrottled(): void
    {
        if (time() - (int) $this->GetBuffer('LastTilePush') >= self::TILE_INTERVAL) {
            $this->SetBuffer('LastTilePush', (string) time());
            $this->PushTile($this->BuildTileData());
        } else {
            $this->SetTimerInterval('Refresh', (self::TILE_INTERVAL + 1) * 1000);
        }
    }

    private function BuildTileData(): array
    {
        $state = $this->GetState();
        $list = [];
        foreach ($this->Tuners() as $tuner) {
            $entry = $state[$tuner['name']] ?? [];
            $list[] = [
                'n'    => $tuner['name'],
                'u'    => $tuner['uuid'],
                'en'   => $tuner['enabled'],
                'b'    => !empty($entry['busy']),
                'subs' => (int) ($entry['subs'] ?? 0),
                'mux'  => (string) ($entry['mux'] ?? ''),
                'bps'  => (int) ($entry['bps'] ?? 0),
                'sig'  => (int) ($entry['sig'] ?? 0),
                'ss'   => (int) ($entry['ss'] ?? 0),
                'snr'  => (int) ($entry['snr'] ?? 0),
                'ns'   => (int) ($entry['ns'] ?? 0)
            ];
        }
        return [
            'type'   => 'tuners',
            'online' => $this->HasActiveParent(),
            't'      => $this->TileTexts([
                'all'   => 'All', 'busy' => 'Busy', 'free' => 'Free', 'disabled' => 'Disabled', 'search' => 'Search', 'of' => 'of',
                'empty' => 'No tuners', 'noMatch' => 'No matching tuners', 'offline' => 'Server not reachable', 'signal' => 'Signal',
                'snr'   => 'SNR', 'subs' => 'streams', 'stream' => 'stream', 'idle' => 'Free'
            ]),
            'tuners' => $list
        ];
    }

    //Tvheadend liefert Signal und SNR relativ (Skala 1, 0 bis 65535) oder in 1/1000 dB (Skala 2).
    private function FormatScaled(int $Value, int $Scale): string
    {
        switch ($Scale) {
            case 1:
                return round($Value * 100 / 65535) . ' %';
            case 2:
                return round($Value / 1000, 1) . ' dB';
        }
        return '-';
    }

    //Legt für jeden Tuner die Variablen an (oder entfernt sie, wenn sie abgeschaltet sind).
    private function MaintainTunerVariables(): void
    {
        $keep = $this->ReadPropertyBoolean('CreateVariables');
        $position = 10;
        foreach ($this->Tuners() as $tuner) {
            $key = $this->TunerKey($tuner['name']);
            $definitions = [
                [$key . '_Enabled', 'Enabled', VARIABLETYPE_BOOLEAN, $this->BooleanPresentation('Yes', 'No')],
                [$key . '_Busy', 'Busy', VARIABLETYPE_BOOLEAN, $this->BooleanPresentation('Yes', 'No')],
                [$key . '_Signal', 'Signal', VARIABLETYPE_FLOAT, $this->ValuePresentation(' %', 0)],
                [$key . '_Snr', 'SNR', VARIABLETYPE_FLOAT, $this->ValuePresentation(' %', 0)],
                [$key . '_Bitrate', 'Bitrate', VARIABLETYPE_INTEGER, $this->ValuePresentation(' kbit/s')]
            ];
            foreach ($definitions as [$ident, $caption, $type, $presentation]) {
                $this->MaintainVariable($ident, $tuner['name'] . ' - ' . $this->Translate($caption), $type, $presentation, $position++, $keep);
            }
        }
    }

    //Setzt die Variablen eines Tuners aus seinem Zustand.
    private function SetTunerVariables(string $Name, array $State): void
    {
        if (!$this->ReadPropertyBoolean('CreateVariables')) {
            return;
        }
        $key = $this->TunerKey($Name);
        if (!@$this->GetIDForIdent($key . '_Busy')) {
            //Ein Tuner, den der Server neu meldet: seine Variablen entstehen jetzt.
            $this->MaintainTunerVariables();
        }
        foreach ($this->Tuners() as $tuner) {
            if ($tuner['name'] === $Name) {
                $this->SetValue($key . '_Enabled', $tuner['enabled']);
                break;
            }
        }
        $this->SetValue($key . '_Busy', (bool) ($State['busy'] ?? false));
        $this->SetValue($key . '_Bitrate', (int) round(((int) ($State['bps'] ?? 0)) / 1000));
        $this->SetScaled($key . '_Signal', (int) ($State['sig'] ?? 0), (int) ($State['ss'] ?? 0));
        $this->SetScaled($key . '_Snr', (int) ($State['snr'] ?? 0), (int) ($State['ns'] ?? 0));
    }

    //Wechselt die Skala, bekommt die Variable die passende Einheit.
    private function SetScaled(string $Ident, int $Value, int $Scale): void
    {
        if ($Scale !== 1 && $Scale !== 2) {
            return;
        }
        $scales = (array) json_decode($this->ReadAttributeString('Scales'), true);
        if (($scales[$Ident] ?? 0) !== $Scale) {
            $scales[$Ident] = $Scale;
            $this->WriteAttributeString('Scales', json_encode($scales));
            $id = $this->GetIDForIdent($Ident);
            $this->MaintainVariable($Ident, IPS_GetName($id), VARIABLETYPE_FLOAT, $this->ValuePresentation($Scale === 1 ? ' %' : ' dB', $Scale === 1 ? 0 : 1), (int) IPS_GetObject($id)['ObjectPosition'], true);
        }
        $this->SetValue($Ident, $Scale === 1 ? round($Value * 100 / 65535, 0) : round($Value / 1000, 1));
    }

    //Ident-Teil aus dem Namen des Tuners: nur Buchstaben und Ziffern, stabil und kurz.
    private function TunerKey(string $Name): string
    {
        return 'T' . substr(md5($Name), 0, 10);
    }

    //Liest die Tuner aus dem Hardwarebaum.
    private function LoadFrontends(): void
    {
        if (!($this->GetServerInfo()['admin'] ?? false)) {
            return;
        }
        $frontends = [];
        $this->WalkTree('root', 0, $frontends);
        if (count($frontends) > 0) {
            $this->WriteAttributeString('Frontends', json_encode($frontends));
        }
    }

    private function WalkTree(string $Uuid, int $Depth, array &$Frontends): void
    {
        if ($Depth >= self::TREE_DEPTH) {
            return;
        }
        $result = $this->ApiRequest('api/hardware/tree', ['uuid' => $Uuid]);
        if ($result['code'] !== 200 || !is_array($result['data'])) {
            return;
        }
        foreach ($result['data'] as $node) {
            if (!is_array($node) || !isset($node['uuid'])) {
                continue;
            }
            if (preg_match(self::FRONTEND_PATTERN, (string) ($node['class'] ?? ''))) {
                $enabled = true;
                foreach ((array) ($node['params'] ?? []) as $param) {
                    if (($param['id'] ?? '') === 'enabled') {
                        $enabled = !empty($param['value']);
                    }
                }
                $Frontends[(string) $node['uuid']] = ['name' => (string) ($node['text'] ?? $node['caption'] ?? $node['uuid']), 'enabled' => $enabled];
            } elseif (empty($node['leaf'])) {
                $this->WalkTree((string) $node['uuid'], $Depth + 1, $Frontends);
            }
        }
    }
}
