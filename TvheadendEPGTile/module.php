<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';

class TvheadendEPGTile extends IPSModuleStrict
{
    use TvheadendChild;

    private const VIEW_TIMELINE = 0;
    private const VIEW_LIST = 1;

    //Kanäle, die die Kachel pro Anfrage lädt, und Obergrenze gegen zu große Anfragen.
    private const MAX_CHANNELS_PER_REQUEST = 20;
    //Höhe der Senderlogos in Pixeln, größere Bilder werden verkleinert.
    private const LOGO_HEIGHT = 48;
    //Mindestabstand zwischen zwei Aktualisierungen, die durch Meldungen vom Server ausgelöst werden (Sekunden).
    private const MIN_REFRESH_INTERVAL = 15;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('DefaultView', self::VIEW_TIMELINE);
        $this->RegisterPropertyInteger('WindowHours', 3);

        //Kanalliste aus channel/grid, sortiert nach Kanalnummer: [{u, n, name, icon}].
        $this->RegisterAttributeString('Channels', '[]');

        //Ab Symcon 9.1 zeigt die Kachel ihr HTML auch im Vollbild, davor nur in der normalen Kachel. Die Konstanten für den
        //Kachel-Typ gibt es erst ab 9.1, auf 9.0 fehlt auch INSTANCE_VISUALIZATION_TYPE_HTML, deshalb steht dafür der Wert 1.
        $this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') ? INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN : 1);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() === KR_READY && $this->HasActiveParent()) {
            $this->LoadChannels();
        }
        $this->UpdateVisualizationValue(json_encode($this->BuildInit()));
    }

    public function GetVisualizationTile(): string
    {
        if (count($this->GetChannels()) === 0 && $this->HasActiveParent()) {
            $this->LoadChannels();
        }
        $html = file_get_contents(__DIR__ . '/module.html');
        //json_encode zweimal: handleMessage bekommt einen JSON-Text wie bei UpdateVisualizationValue.
        return $html . '<script>handleMessage(' . json_encode(json_encode($this->BuildInit())) . ');</script>';
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        switch ($data['Action'] ?? '') {
            case TVH_ACTION_SYNC:
                if (($data['Online'] ?? false) === true) {
                    $this->LoadChannels();
                    $this->UpdateVisualizationValue(json_encode($this->BuildInit()));
                }
                break;
            case TVH_ACTION_NOTIFY:
                if (in_array($data['Class'] ?? '', ['epg', 'dvrentry', 'dvr_entry'], true)) {
                    $last = (int) $this->GetBuffer('LastRefresh');
                    if (time() - $last >= self::MIN_REFRESH_INTERVAL) {
                        $this->SetBuffer('LastRefresh', (string) time());
                        //Die geöffneten Kacheln laden ihren sichtbaren Ausschnitt dann selbst neu.
                        $this->UpdateVisualizationValue(json_encode(['type' => 'refresh']));
                    }
                }
                break;
        }
        return '';
    }

    //Aktionen der Kachel. Der Wert ist ein JSON-Text.
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $args = json_decode((string) $Value, true);
        $args = is_array($args) ? $args : [];
        switch ($Ident) {
            case 'Load':
                $this->SendEvents($args);
                break;
            case 'Logos':
                $this->SendLogos((array) ($args['uuids'] ?? []));
                break;
            case 'Reload':
                $this->LoadChannels();
                $this->UpdateVisualizationValue(json_encode($this->BuildInit()));
                break;
            case 'Record':
            case 'Series':
            case 'Stop':
            case 'Cancel':
                $this->RunDvrAction($Ident, $args);
                break;
        }
    }

    //Daten, mit denen die Kachel startet: Texte, Einstellungen, Kanäle und Aufnahmeprofile.
    private function BuildInit(): array
    {
        $channels = [];
        foreach ($this->GetChannels() as $channel) {
            $channels[] = ['u' => $channel['u'], 'n' => $channel['n'], 'name' => $channel['name']];
        }
        return [
            'type'     => 'init',
            'view'     => $this->ReadPropertyInteger('DefaultView') === self::VIEW_LIST ? 'list' : 'timeline',
            'hours'    => max(1, min(12, $this->ReadPropertyInteger('WindowHours'))),
            'online'   => $this->HasActiveParent() && isset($this->GetServerInfo()['version']),
            'channels' => $channels,
            'configs'  => $this->LoadConfigs(),
            't'        => $this->Texts()
        ];
    }

    private function Texts(): array
    {
        $keys = [
            'timeline'   => 'Timeline', 'list' => 'List', 'now' => 'Now', 'next' => 'Next', 'record' => 'Record',
            'series'     => 'Record series', 'cancel' => 'Delete recording', 'stop' => 'Stop recording', 'close' => 'Close',
            'scheduled'  => 'Scheduled for recording', 'recording' => 'Recording now', 'completed' => 'Recorded',
            'profile'    => 'Recording profile', 'default' => 'Default', 'offline' => 'Server not reachable',
            'noEvents'   => 'No program data', 'loading' => 'Loading...', 'ok' => 'Done', 'failed' => 'Failed',
            'noChannels' => 'No channels available', 'until' => 'until'
        ];
        $texts = [];
        foreach ($keys as $key => $caption) {
            $texts[$key] = $this->Translate($caption);
        }
        return $texts;
    }

    //Sendet die Sendungen der angefragten Kanäle im Zeitfenster [from, to].
    private function SendEvents(array $Args): void
    {
        $from = (int) ($Args['from'] ?? time());
        $to = (int) ($Args['to'] ?? $from + 10800);
        $uuids = array_slice(array_filter((array) ($Args['uuids'] ?? []), 'is_string'), 0, self::MAX_CHANNELS_PER_REQUEST);
        //Weit in der Zukunft liegende Fenster brauchen mehr Einträge pro Kanal.
        $limit = $from > time() + 86400 ? 200 : 80;

        $events = [];
        foreach ($uuids as $uuid) {
            $events[$uuid] = [];
            $result = $this->ApiRequest('api/epg/events/grid', ['channel' => $uuid, 'sort' => 'start', 'limit' => $limit]);
            if ($result['code'] !== 200 || !is_array($result['data'])) {
                continue;
            }
            foreach ((array) ($result['data']['entries'] ?? []) as $event) {
                $start = (int) ($event['start'] ?? 0);
                $stop = (int) ($event['stop'] ?? 0);
                if ($stop <= $from || $start >= $to) {
                    continue;
                }
                $description = (string) ($event['description'] ?? $event['summary'] ?? '');
                $events[$uuid][] = [
                    'i'  => (int) $event['eventId'],
                    's'  => $start,
                    'e'  => $stop,
                    't'  => (string) ($event['title'] ?? ''),
                    'st' => (string) ($event['subtitle'] ?? ''),
                    'd'  => mb_substr($description, 0, 600),
                    'hd' => !empty($event['hd']),
                    'dv' => (string) ($event['dvrState'] ?? ''),
                    'du' => (string) ($event['dvrUuid'] ?? '')
                ];
            }
        }
        $this->SendDebug('Events', count($uuids) . ' channels, ' . array_sum(array_map('count', $events)) . ' events, ' . date('d.m. H:i', $from) . ' - ' . date('d.m. H:i', $to), 0);
        $this->UpdateVisualizationValue(json_encode([
            'type'   => 'events',
            'id'     => $Args['id'] ?? 0,
            'from'   => $from,
            'to'     => $to,
            'events' => $events
        ]));
    }

    //Führt eine Aufnahmeaktion aus und meldet das Ergebnis an die Kachel.
    private function RunDvrAction(string $Action, array $Args): void
    {
        $config = (string) ($Args['config'] ?? '');
        $params = [];
        switch ($Action) {
            case 'Record':
                $path = 'api/dvr/entry/create_by_event';
                $params = ['event_id' => (int) ($Args['eventId'] ?? 0)];
                break;
            case 'Series':
                $path = 'api/dvr/autorec/create_by_series';
                $params = ['event_id' => (int) ($Args['eventId'] ?? 0)];
                break;
            case 'Stop':
                $path = 'api/dvr/entry/stop';
                $params = ['uuid' => (string) ($Args['uuid'] ?? '')];
                break;
            default:
                $path = 'api/dvr/entry/cancel';
                $params = ['uuid' => (string) ($Args['uuid'] ?? '')];
        }
        if ($config !== '' && ($Action === 'Record' || $Action === 'Series')) {
            $params['config_uuid'] = $config;
        }

        $result = $this->ApiRequest($path, $params, 'POST');
        $this->SendDebug(__FUNCTION__, $Action . ' => ' . $result['code'], 0);
        $this->UpdateVisualizationValue(json_encode([
            'type' => 'result',
            'ok'   => $result['code'] === 200,
            'text' => $this->Translate($result['code'] === 200 ? 'Done' : 'Failed')
        ]));
        $this->UpdateVisualizationValue(json_encode(['type' => 'refresh']));
    }

    //Aufnahmeprofile des Servers: [{uuid, name}].
    private function LoadConfigs(): array
    {
        $configs = [];
        $result = $this->ApiRequest('api/dvr/config/grid', ['limit' => 50]);
        if ($result['code'] === 200 && is_array($result['data'])) {
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                if (!empty($entry['uuid'])) {
                    $configs[] = ['uuid' => (string) $entry['uuid'], 'name' => (string) ($entry['name'] ?? '')];
                }
            }
        }
        return $configs;
    }

    //Liest die Kanäle vom Server und sortiert sie nach Kanalnummer.
    private function LoadChannels(): void
    {
        $result = $this->ApiRequest('api/channel/grid', ['limit' => 2000, 'sort' => 'number']);
        if ($result['code'] !== 200 || !is_array($result['data'])) {
            return;
        }
        $channels = [];
        foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
            if (empty($entry['uuid']) || empty($entry['enabled'])) {
                continue;
            }
            $channels[] = [
                'u'    => (string) $entry['uuid'],
                'n'    => (string) ($entry['number'] ?? ''),
                'name' => (string) ($entry['name'] ?? ''),
                'icon' => (string) ($entry['icon_public_url'] ?? ''),
                'src'  => (string) ($entry['icon'] ?? '')
            ];
        }
        //Kanäle ohne Nummer ans Ende, sonst numerisch (auch Nummern wie "36.1").
        usort($channels, function (array $a, array $b): int
        {
            $na = $a['n'] === '' ? PHP_FLOAT_MAX : (float) $a['n'];
            $nb = $b['n'] === '' ? PHP_FLOAT_MAX : (float) $b['n'];
            return $na <=> $nb ?: strcasecmp($a['name'], $b['name']);
        });
        $this->WriteAttributeString('Channels', json_encode($channels));
    }

    private function GetChannels(): array
    {
        return (array) json_decode($this->ReadAttributeString('Channels'), true);
    }

    //Sendet die Logos der angefragten Kanäle als Data-URI. Der Browser kann sie nicht selbst laden,
    //weil der Server eine Anmeldung verlangt.
    private function SendLogos(array $Uuids): void
    {
        $icons = [];
        foreach ($this->GetChannels() as $channel) {
            $icons[$channel['u']] = [$channel['icon'], $channel['src'] ?? ''];
        }
        $logos = [];
        foreach (array_slice($Uuids, 0, 40) as $uuid) {
            $uri = isset($icons[$uuid]) ? $this->GetLogo($icons[$uuid][0], $icons[$uuid][1]) : '';
            if ($uri !== '') {
                $logos[$uuid] = $uri;
            }
        }
        $this->SendDebug('Logos', count($logos) . ' of ' . count($Uuids) . ' available', 0);
        if (count($logos) > 0) {
            $this->UpdateVisualizationValue(json_encode(['type' => 'logos', 'logos' => $logos]));
        }
    }

    //Logo als Data-URI. Verkleinert und in einer Datei zwischengespeichert, damit der Server nur einmal gefragt wird.
    //$IconPath ist die Adresse des Image Cache (imagecache/ID), $Source die ursprüngliche Adresse des Logos.
    //Der Image Cache muss auf dem Server eingeschaltet sein, sonst antwortet er mit 404. Dann hilft nur ein Logo
    //mit einer normalen Internetadresse.
    private function GetLogo(string $IconPath, string $Source = ''): string
    {
        if ($IconPath === '' && $Source === '') {
            return '';
        }
        $directory = IPS_GetKernelDir() . 'media' . DIRECTORY_SEPARATOR . 'tvheadend_epg_' . $this->InstanceID;
        $file = $directory . DIRECTORY_SEPARATOR . md5($IconPath . $Source) . '.txt';
        if (is_file($file)) {
            return (string) file_get_contents($file);
        }

        $raw = $IconPath !== '' ? $this->ApiRaw($IconPath) : ['code' => 0, 'body' => ''];
        if ($raw['code'] !== 200 && preg_match('#^https?://#i', $Source)) {
            $body = @file_get_contents($Source, false, stream_context_create(['http' => ['timeout' => 5]]));
            $raw = ['code' => $body === false ? 0 : 200, 'body' => (string) $body];
        }
        if ($raw['code'] !== 200 || $raw['body'] === '' || !function_exists('imagecreatefromstring')) {
            return '';
        }
        $image = @imagecreatefromstring($raw['body']);
        if ($image === false) {
            return '';
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($height > self::LOGO_HEIGHT) {
            $scaled = imagescale($image, (int) max(1, round($width * self::LOGO_HEIGHT / $height)), self::LOGO_HEIGHT);
            if ($scaled !== false) {
                $image = $scaled;
            }
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $uri = 'data:image/png;base64,' . base64_encode($png);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }
        @file_put_contents($file, $uri);
        return $uri;
    }
}
