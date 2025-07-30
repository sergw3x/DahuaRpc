<?php

class DahuaRpc
{
    private string $host;
    private string $username;
    private string $password;
    private ?string $session_id;
    private int $id;
    private int $token;
    private int $totalCount;
    private \CurlHandle $curl;

    public function __construct($host, $username, $password)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->session_id = null;
        $this->id = 0;
        $this->token = 0;
        $this->totalCount = 0;

        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_HEADER, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);

//        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, true);
//        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 2);

        // Устанавливаем таймауты
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 3);

//        curl_setopt($this->curl, CURLOPT_VERBOSE, true);
//        $verbose = fopen('php://temp', 'w+');
//        curl_setopt($this->curl, CURLOPT_STDERR, $verbose);
    }

    private function request($method, $params = null, $object_id = null, $extra = null, $url = null)
    {
        $this->id++;
        $data = [
            'method' => $method,
            'id' => $this->id
        ];
        if ($params !== null) {
            $data['params'] = $params;
        }
        if ($object_id !== null) {
            $data['object'] = $object_id;
        }
        if ($extra !== null) {
            $data = array_merge($data, $extra);
        }
        if ($this->session_id !== null) {
            $data['session'] = $this->session_id;
        }

        if (!$url) {
            $url = "http://{$this->host}/RPC2";
        }

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($this->curl);
        if ($response === false) {
            throw new Exception('CURL error: ' . curl_error($this->curl));
        }

        $json = json_decode($response, true);
        if ($json === null) {
            throw new Exception('Invalid JSON response');
        }
        return $json;
    }

    public function login()
    {
        $url_login = "http://{$this->host}/RPC2_Login";
        $params_login = [
            'userName' => $this->username,
            'password' => "",
            'clientType' => "Web5.0"
        ];

        $res = $this->request("global.login", $params_login, null, [], $url_login);
        if (!isset($res['session'])) {
            throw new Exception("Login failed: no session");
        }

        $this->session_id = $res['session'];

        $realm = $res['params']['realm'];
        $random = $res['params']['random'];

        // Шифр пароля
        $pwd_phrase = $this->username . ":" . $realm . ":" . $this->password;
        $pwd_hash = strtoupper(md5($pwd_phrase));

        $pass_phrase = $this->username . ':' . $random . ':' . $pwd_hash;
        $pass_hash = strtoupper(md5($pass_phrase));

        $params_login2 = [
            'userName' => $this->username,
            'password' => $pass_hash,
            'clientType' => "Web5.0",
            'loginType' => "Direct",
            'authorityType' => "Default",
            'passwordType' => "Default",
        ];

        $res2 = $this->request("global.login", $params_login2, null, [], $url_login);
        if (isset($res2['result']) && $res2['result'] === false) {
            throw new Exception("Login failed: " . json_encode($res2));
        }

        $this->session_id = $res2['session'];

    }

    public function logout()
    {
        $res = $this->request("global.logout");
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Logout error");
        }
        $this->session_id = null;
    }

    public function get_current_time()
    {
        $res = $this->request("global.getCurrentTime");
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Error getting time." . print_r($res, true));
        }
        return $res['params']['time'];
    }

    public function get_product_def()
    {
        $res = $this->request("magicBox.getProductDefinition", ["name" => "Traffic"]);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Error getting product definition");
        }
        return $res;
    }

    public function keep_alive()
    {
        $res = $this->request("global.keepAlive", ['timeout' => 300, 'active' => false]);
        if (isset($res['result']) && $res['result'] === true) {
            return true;
        } else {
            throw new Exception("Keep alive failed");
        }
    }

    public function get_traffic_info()
    {
        $res = $this->request("RecordFinder.factory.create", ["name" => "TrafficSnapEventInfo"]);
        if (!isset($res['result'])) {
            throw new Exception("Error getting traffic info");
        }
        return $res['result'];
    }

    public function start_find($object_id)
    {
        $method = "RecordFinder.startFind";
        $params = [
            "condition" => [
                "Time" => ["<>", 1558925818, 1559012218]
            ]
        ];
        $res = $this->request($method, $params, $object_id);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Start find failed");
        }
    }

    public function do_find($object_id)
    {
        $method = "RecordFinder.doFind";
        $params = ["count" => 50000];
        $res = $this->request($method, $params, $object_id);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Do find failed");
        }
        return $res;
    }

    public function set_config($params)
    {
        $res = $this->request("configManager.setConfig", $params);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Set config failed");
        }
    }

    public function reboot()
    {
        $method_obj = "magicBox.factory.instance";
        $res_obj = $this->request($method_obj);
        $object_id = $res_obj['result'];

        $res_reboot = $this->request("magicBox.reboot", [], $object_id);
        if (isset($res_reboot['result']) && $res_reboot['result'] === false) {
            throw new Exception("Reboot failed");
        }
    }

    public function current_time()
    {
        $res = $this->request("global.getCurrentTime");
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Error getting current time");
        }
        return $res['params']['time'];
    }

    public function ntp_sync($address, $port, $time_zone)
    {
        $res_obj = $this->request("netApp.factory.instance");
        $object_id = $res_obj['result'];

        $params = [
            'Address' => $address,
            'Port' => $port,
            'TimeZone' => $time_zone
        ];
        $res = $this->request("netApp.adjustTimeWithNTP", $params, $object_id);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("NTP sync failed");
        }
    }

    public function get_split()
    {
        $res_obj = $this->request("split.factory.instance", ['channel' => 0]);
        $object_id = $res_obj['result'];

        $res_mode = $this->request("split.getMode", [], $object_id);
        if (isset($res_mode['result']) && $res_mode['result'] === false) {
            throw new Exception("Get split mode failed");
        }
        $modeStr = $res_mode['params']['mode'];
        $mode = intval(substr($modeStr, 5));
        $view = intval($res_mode['params']['group']) + 1;
        return ['mode' => $mode, 'view' => $view];
    }

    /**
     * @throws Exception
     */
    public function attach_event($event = [])
    {
        if (!$event) return [];
        $res = $this->request("eventManager.attach", ['codes' => $event]);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Attach event failed");
        }
        return $res['params'];
    }

    public function listen_events($callback = null)
    {
        $url = "http://{$this->host}/SubscribeNotify.cgi?sessionId={$this->session_id}";
        curl_setopt($this->curl, CURLOPT_URL, $url);
//        curl_setopt($this->curl, CURLOPT_STREAM, true);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($this->curl);
        if ($response === false) {
            throw new Exception('CURL error: ' . curl_error($this->curl));
        }
        // Тут можно добавить слушатель, если нужно
    }

    public function set_split($mode, $view)
    {
        if (is_int($mode)) {
            $modeStr = "Split{$mode}";
        } else {
            $modeStr = $mode;
        }
        $group = $view - 1;
        $res_obj = $this->request("split.factory.instance", ['channel' => 0]);
        $object_id = $res_obj['result'];

        $params = [
            'displayType' => "General",
            'workMode' => "Local",
            'mode' => $modeStr,
            'group' => $group
        ];

        $res = $this->request("split.setMode", $params, $object_id);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Set split mode failed");
        }
    }

    // Методы для работы со статистикой
    public function get_people_counting_info()
    {
        $res = $this->request("videoStatServer.factory.instance", ['channel' => 0]);
        if (isset($res['result'])) {
            return $res['result'];
        } else {
            throw new Exception("Get people counting info failed");
        }
    }

    public function start_find_statistics_data($object_id, $StartTime, $EndTime, $AreaID)
    {
        $params = [
            'condition' => [
                'StartTime' => $StartTime,
                'EndTime' => $EndTime,
                'Granularity' => 'Hour',
                'RuleType' => 'NumberStat',
                'PtzPresetId' => 0,
                'AreaID' => [$AreaID]
            ]
        ];
        $res = $this->request("videoStatServer.startFind", $params, $object_id);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Start find stats failed " . print_r($res, true));
        }
        $this->token = $res['params']['token'];
        $this->totalCount = $res['params']['totalCount'];
        return $this->totalCount;
    }

    public function do_find_statistics_data($object_id)
    {
        $params = [
            'token' => $this->token,
            'beginNumber' => 0,
            'count' => $this->totalCount
        ];
        $res = $this->request("videoStatServer.doFind", $params, $object_id);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Do find stats failed");
        }
        return $res['params']['info'];
    }

    public function stop_find_statistics_data($object_id)
    {
        $params = ['token' => $this->token];
        $res = $this->request("videoStatServer.stopFind", $params, $object_id);
        if (isset($res['result']) && $res['result'] === false) {
            throw new Exception("Stop find stats failed");
        }
        $this->token = 0;
        $this->totalCount = 0;
    }
}
