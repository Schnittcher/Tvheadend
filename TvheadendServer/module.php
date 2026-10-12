<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';

class TvheadendServer extends IPSModuleStrict
{
    use TvheadendChild;

    private const GIB = 1073741824;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('WolMac', '');
        $this->RegisterPropertyString('WolBroadcast', '');
        $this->RegisterPropertyInteger('SleepLeadMinutes', 15);

        $this->RegisterVariableBoolean('Online', $this->Translate('Online'), $this->BooleanPresentation('Online', 'Offline'), 1);
        $this->RegisterVariableString('Version', $this->Translate('Version'), $this->ValuePresentation(), 2);
        $this->RegisterVariableFloat('DiskFree', $this->Translate('Free Disk Space'), $this->ValuePresentation(' GB', 1), 7);
        $this->RegisterVariableFloat('DiskUsed', $this->Translate('Used Disk Space'), $this->ValuePresentation(' GB', 1), 8);
        $this->RegisterVariableFloat('DiskTotal', $this->Translate('Total Disk Space'), $this->ValuePresentation(' GB', 1), 9);
        $this->RegisterVariableInteger('NextActivity', $this->Translate('Next Activity'), $this->DateTimePresentation(), 10);
        //Holt nach einer Flut von Meldungen einmal Tuner und Aktivität neu (die letzte Meldung wird sonst verschluckt).
        $this->RegisterTimer('Refresh', 0, 'TVHS_RefreshLater($_IPS[\'TARGET\']);');
        $this->RegisterVariableBoolean('SleepAllowed', $this->Translate('Sleep Allowed'), $this->BooleanPresentation('Yes', 'No'), 11);
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
                } else {
                    $this->SetValue('Online', false);
                }
                break;
            case TVH_ACTION_NOTIFY:
                $this->HandleNotification((string) ($data['Class'] ?? ''), (array) ($data['Message'] ?? []));
                break;
        }
        return '';
    }

    //Liest alle Werte neu vom Server.
    public function Update(): void
    {
        $info = $this->GetServerInfo();
        if (!isset($info['version'])) {
            $this->SetValue('Online', false);
            return;
        }
        $this->SetValue('Online', true);
        $this->SetValue('Version', (string) $info['version']);

        //Den Speicherplatz meldet der Server über den WebSocket (diskspaceUpdate, accessUpdate).
        if (!($info['admin'] ?? false)) {
            //Status und Aktivität brauchen Administratorrechte.
            $this->SendDebug(__FUNCTION__, 'User is not an administrator, status data skipped', 0);
            return;
        }
        $this->UpdateActivity();
    }

    //Wird vom Timer nach einer Meldungsflut aufgerufen.
    public function RefreshLater(): void
    {
        $this->SetTimerInterval('Refresh', 0);
        $this->UpdateActivity();
    }

    //Sendet ein Wake-on-LAN-Paket an den Server.
    public function WakeUp(): bool
    {
        $mac = $this->ReadPropertyString('WolMac');
        $broadcast = $this->ReadPropertyString('WolBroadcast');
        if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', $mac) || filter_var($broadcast, FILTER_VALIDATE_IP) === false) {
            $this->LogMessage($this->Translate('MAC address or broadcast address is invalid'), KL_WARNING);
            return false;
        }

        $hardware = '';
        foreach (preg_split('/[:-]/', $mac) as $byte) {
            $hardware .= chr((int) hexdec($byte));
        }
        $packet = str_repeat(chr(255), 6) . str_repeat($hardware, 16);

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            $this->LogMessage(socket_strerror(socket_last_error()), KL_ERROR);
            return false;
        }
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        $sent = socket_sendto($socket, $packet, strlen($packet), 0, $broadcast, 9);
        socket_close($socket);
        $this->SendDebug(__FUNCTION__, 'Magic packet sent: ' . var_export($sent !== false, true), 0);
        return $sent !== false;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Update':
                $this->Update();
                break;
            case 'WakeUp':
                $this->WakeUp();
                break;
        }
    }

    private function HandleNotification(string $Class, array $Message): void
    {
        switch ($Class) {
            case 'accessUpdate':
            case 'diskspaceUpdate':
                $this->UpdateDiskSpace($Message);
                break;
            case 'subscriptions':
                //Laufende Streams entscheiden, ob der Server schlafen darf. Die Meldung kommt jede Sekunde.
                if ($this->Throttle('Activity', 10)) {
                    $this->UpdateActivity();
                } else {
                    $this->SetTimerInterval('Refresh', 11000);
                }
                break;
        }
    }

    //Erlaubt eine Aktion höchstens alle $Seconds Sekunden (true, wenn sie jetzt dran ist).
    private function Throttle(string $Name, int $Seconds): bool
    {
        $last = (int) $this->GetBuffer('Throttle' . $Name);
        if (time() - $last < $Seconds) {
            return false;
        }
        $this->SetBuffer('Throttle' . $Name, (string) time());
        return true;
    }

    private function UpdateDiskSpace(array $Data): void
    {
        $map = ['freediskspace' => 'DiskFree', 'useddiskspace' => 'DiskUsed', 'totaldiskspace' => 'DiskTotal'];
        foreach ($map as $field => $ident) {
            if (isset($Data[$field]) && is_numeric($Data[$field])) {
                $this->SetValue($ident, round((float) $Data[$field] / self::GIB, 1));
            }
        }
    }

    private function UpdateActivity(): void
    {
        $result = $this->ApiRequest('api/status/activity');
        if ($result['code'] !== 200 || !is_array($result['data'])) {
            //status/activity gibt es erst ab Tvheadend 4.3-2405.
            return;
        }
        $next = (int) ($result['data']['next_activity'] ?? 0);
        $now = (int) ($result['data']['current_time'] ?? time());
        $busy = (int) ($result['data']['subscription_count'] ?? 0) > 0;
        $soon = $next > 0 && $next < $now + $this->ReadPropertyInteger('SleepLeadMinutes') * 60;

        $this->SetValue('NextActivity', $next);
        $this->SetValue('SleepAllowed', !$busy && !$soon);
    }
}
