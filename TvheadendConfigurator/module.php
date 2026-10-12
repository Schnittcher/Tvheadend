<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';

class TvheadendConfigurator extends IPSModuleStrict
{
    use TvheadendChild;

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $gatewayID = (int) (IPS_GetInstance($this->InstanceID)['ConnectionID'] ?? 0);
        $online = $gatewayID > 0 && $this->HasActiveParent();
        $admin = (bool) ($online ? ($this->GetServerInfo()['admin'] ?? false) : false);

        //Server, DVR und EPG gibt es je Gateway einmal.
        $values = [];
        $singles = [
            [TVH_MODULE_SERVER, 'Server', 'Status, connections, subscriptions, tuners and disk space'],
            [TVH_MODULE_TUNER, 'Tuners', 'All tuners: state, signal, busy and a list tile'],
            [TVH_MODULE_DVR, 'DVR', 'Recordings: scheduled, running, finished and failed'],
            [TVH_MODULE_EPG, 'EPG', 'On air and next for chosen channels, search, recording from the program guide'],
            [TVH_MODULE_RULES, 'Recording rules', 'Series and time recordings: create, edit, switch and delete'],
            [TVH_MODULE_EPG_TILE, 'EPG tile', 'Program guide as a tile for the visualization'],
            [TVH_MODULE_STREAMING, 'Streaming', 'Who is watching: streams and connected clients'],
            [TVH_MODULE_CHANNELS, 'Channels', 'Channel management: enable, disable and rename'],
            [TVH_MODULE_DIAGNOSTICS, 'Diagnostics', 'Server log problems and EPG grabbers']
        ];
        foreach ($singles as [$moduleID, $name, $description]) {
            $values[] = [
                'name'        => $this->Translate($name),
                'description' => $this->Translate($description),
                'instanceID'  => $this->FindInstance($moduleID, $gatewayID),
                'create'      => ['moduleID' => $moduleID, 'configuration' => new stdClass()]
            ];
        }

        if ($online) {
            $this->AddGroup($values, 'networks', 'Networks', $admin ? $this->BuildNetworkRows($gatewayID) : []);
        }

        foreach ($form['actions'] as &$element) {
            if (($element['name'] ?? '') === 'Configurator') {
                $element['values'] = $values;
            }
        }
        return json_encode($form);
    }

    //Fügt eine aufklappbare Gruppe samt Zeilen hinzu. Eine leere Gruppe bleibt weg.
    private function AddGroup(array &$Values, string $Id, string $Caption, array $Rows): void
    {
        if (count($Rows) === 0) {
            return;
        }
        $Values[] = ['id' => $Id, 'name' => $this->Translate($Caption) . ' (' . count($Rows) . ')', 'description' => '', 'expanded' => true];
        foreach ($Rows as $row) {
            $row['parent'] = $Id;
            $Values[] = $row;
        }
    }

    private function BuildNetworkRows(int $GatewayID): array
    {
        $rows = [];
        $result = $this->ApiRequest('api/mpegts/network/grid', ['limit' => 200]);
        foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
            if (empty($entry['uuid'])) {
                continue;
            }
            $rows[] = [
                'name'        => (string) ($entry['networkname'] ?? $entry['uuid']),
                'description' => sprintf('%d %s, %d %s', (int) ($entry['num_mux'] ?? 0), $this->Translate('Muxes'), (int) ($entry['num_svc'] ?? 0), $this->Translate('Services')),
                'instanceID'  => $this->FindInstanceByProperty(TVH_MODULE_NETWORK, $GatewayID, 'NetworkUuid', (string) $entry['uuid']),
                'create'      => [
                    'moduleID'      => TVH_MODULE_NETWORK,
                    'configuration' => ['NetworkUuid' => (string) $entry['uuid']]
                ]
            ];
        }
        return $rows;
    }

    //Instanz des Moduls, die am selben Gateway hängt (0, wenn es noch keine gibt).
    private function FindInstance(string $ModuleID, int $GatewayID): int
    {
        foreach (IPS_GetInstanceListByModuleID($ModuleID) as $instanceID) {
            if ((int) (IPS_GetInstance($instanceID)['ConnectionID'] ?? 0) === $GatewayID) {
                return $instanceID;
            }
        }
        return 0;
    }

    //Wie FindInstance, zusätzlich mit passendem Wert einer Property (zum Beispiel die UUID einer Regel).
    private function FindInstanceByProperty(string $ModuleID, int $GatewayID, string $Property, string $Value): int
    {
        foreach (IPS_GetInstanceListByModuleID($ModuleID) as $instanceID) {
            if ((int) (IPS_GetInstance($instanceID)['ConnectionID'] ?? 0) === $GatewayID
                && strcasecmp((string) IPS_GetProperty($instanceID, $Property), $Value) === 0) {
                return $instanceID;
            }
        }
        return 0;
    }
}
