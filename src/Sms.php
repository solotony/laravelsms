<?php
namespace solotony\laravelsms;

use App\Http\Controllers\Controller;

class SMS extends Controller
{
    private $charset;

    ///Проверка баланса
    function balance()
    {
        return $this->get($this->request("balance"), "account");
    }

    function reports($start = "0000-00-00", $stop = "0000-00-00", $dop = array())
    {
        if (!isset($dop["source"])) $dop["source"] = "%";
        if (!isset($dop["number"])) $dop["number"] = "%";

        $result = $this->request("report", array(
            "start" => $start,
            "stop" => $stop,
            "source" => $dop["source"],
            "number" => $dop["number"],
        ));
        if ($this->get($result, "code") != 1) {
            $return = array("code" => $this->get($result, "code"), "descr" => $this->get($result, "descr"));
        } else {
            $return = array(
                "code" => $this->get($result, "code"),
                "descr" => $this->get($result, "descr"),
            );
            if (isset($result['sms'])) {
                if (!isset($result['sms'][0])) $result['sms'] = array($result['sms']);
                $return["sms"] = $result['sms'];
            }
        }
        return $return;
    }

    function sendedSmsList($start = "0000-00-00", $stop = "0000-00-00", $number = false)
    {
        $result = $this->request("reportNumber", array(
            "start" => $start,
            "stop" => $stop,
            "number" => $number
        ));
        return $result;
    }

    function detailReport($smsid)
    {
        $result = $this->request("report", array("smsid" => $smsid));
        if ($this->get($result, "code") != 1) {
            $return = array("code" => $this->get($result, "code"), "descr" => $this->get($result, "descr"));
        } else {
            $detail = $result["detail"];
            $return = array(
                "code" => $this->get($result, "code"),
                "descr" => $this->get($result, "descr"),
                "delivered" => $detail['delivered'],
                "notDelivered" => $detail['notDelivered'],
                "waiting" => $detail['waiting'],
                "process" => $detail['process'],
                "enqueued" => $detail['enqueued'],
                "cancel" => $detail['cancel'],
                "onModer" => $detail['onModer'],
            );
            if (isset($result['sms'])) $return["sms"] = $result['sms'];
        }
        return $return;
    }

    //отправка смс
    //params = array (text => , source =>, datetime => , action =>, onlydelivery =>, smsid =>)
    function send($params = array(), $phones = array())
    {
        $phones = (array)$phones;
        if (!isset($params["action"])) $params["action"] = "send";
        $someXML = "";
        if (isset($params["text"])) $params["text"] = htmlspecialchars($params["text"], null, 'utf-8');
        foreach ($phones as $phone) {
            if (is_array($phone)) {
                if (isset($phone["number"])) {
                    $someXML .= "<to number='" . $phone['number'] . "'>";
                    if (isset($phone["text"])) {
                        $someXML .= htmlspecialchars($phone["text"], null, 'utf-8');
                    }
                    $someXML .= "</to>";
                }
            } else {
                $someXML .= "<to number='$phone'></to>";
            }
        }

        $result = $this->request("send", $params, $someXML);
        if ($this->get($result, "code") != 1) {
            $return = array("code" => $this->get($result, "code"), "descr" => $this->get($result, "descr"));
        } else {
            $return = array(
                "code" => 1,
                "descr" => $this->get($result, "descr"),
                "datetime" => $this->get($result, "datetime"),
                "action" => $this->get($result, "action"),
                "allRecivers" => $this->get($result, "allRecivers"),
                "colSendAbonent" => $this->get($result, "colSendAbonent"),
                "colNonSendAbonent" => $this->get($result, "colNonSendAbonent"),
                "priceOfSending" => $this->get($result, "priceOfSending"),
                "colsmsOfSending" => $this->get($result, "colsmsOfSending"),
                "price" => $this->get($result, "price"),
                "smsid" => $this->get($result, "smsid"),
            );
        }
        return $return;

    }

    function get($responce, $key)
    {
        if (isset($responce[$key])) return $responce[$key];
        return false;
    }

    function getURL($action)
    {
        if (config('sms.use_https'))
            $address = config('sms.https_address') . "API/XML/" . $action . ".php";
        else
            $address = config('sms.http_address') . "API/XML/" . $action . ".php";
        $address .= "?returnType=json";
        return $address;
    }


    function request($action, $params = array(), $someXML = "")
    {
        $xml = $this->makeXML($params, $someXML);
        if (config('sms.https_method') == "curl") {
            $res = $this->request_curl($action, $xml);
        } elseif (config('sms.https_method') == "file_get_contents") {
            $opts = array('http' =>
                array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $xml
                )
            );

            $context = stream_context_create($opts);

            $res = file_get_contents($this->getURL($action), false, $context);
        }
        if (isset($res)) {
            $res = json_decode($res, true);
            if (isset($res["data"])) return $res["data"];
            return array();
        }
        $this->error("В настройках указан неизвестный метод запроса - '" . config('sms.https_method') . "'");
    }

    function request_curl($action, $xml)
    {
        $address = $this->getURL($action);
        $ch = curl_init($address);
        curl_setopt($ch, CURLOPT_URL, $address);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    function makeXML($params, $someXML = "")
    {
        $xml = "<?xml version='1.0' encoding='UTF-8'?>
		<data>
			<login>" . htmlspecialchars(config('sms.https_login'), null) . "</login>
			<password>" . htmlspecialchars(config('sms.https_password'), null) . "</password>
			";
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $xml .= "<$key ";
                foreach ($value as $attr => $v) {
                    $xml .= " $attr='" . addslashes(htmlspecialchars($v)) . "' ";
                }
                $xml .= " />";
            } else $xml .= "<$key>$value</$key>";
        }
        $xml .= "$someXML
		</data>";
        return $xml;
    }

    function detectCharset($string, $pattern_size = 50)
    {
        $first2 = substr($string, 0, 2);
        $first3 = substr($string, 0, 3);
        $first4 = substr($string, 0, 3);

        $UTF32_BIG_ENDIAN_BOM = chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF);
        $UTF32_LITTLE_ENDIAN_BOM = chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00);
        $UTF16_BIG_ENDIAN_BOM = chr(0xFE) . chr(0xFF);
        $UTF16_LITTLE_ENDIAN_BOM = chr(0xFF) . chr(0xFE);
        $UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);

        if ($first3 == $UTF8_BOM) return 'UTF-8';
        elseif ($first4 == $UTF32_BIG_ENDIAN_BOM) return 'UTF-32';
        elseif ($first4 == $UTF32_LITTLE_ENDIAN_BOM) return 'UTF-32';
        elseif ($first2 == $UTF16_BIG_ENDIAN_BOM) return 'UTF-16';
        elseif ($first2 == $UTF16_LITTLE_ENDIAN_BOM) return 'UTF-16';

        $list = array('CP1251', 'UTF-8', 'ASCII', '855', 'KOI8R', 'ISO-IR-111', 'CP866', 'KOI8U');
        $c = strlen($string);
        if ($c > $pattern_size) {
            $string = substr($string, floor(($c - $pattern_size) / 2), $pattern_size);
            $c = $pattern_size;
        }

        $reg1 = '/(\xE0|\xE5|\xE8|\xEE|\xF3|\xFB|\xFD|\xFE|\xFF)/i';
        $reg2 = '/(\xE1|\xE2|\xE3|\xE4|\xE6|\xE7|\xE9|\xEA|\xEB|\xEC|\xED|\xEF|\xF0|\xF1|\xF2|\xF4|\xF5|\xF6|\xF7|\xF8|\xF9|\xFA|\xFC)/i';

        $mk = 10000;
        $enc = 'UTF-8';
        foreach ($list as $item) {
            $sample1 = @iconv($item, 'cp1251', $string);
            $gl = @preg_match_all($reg1, $sample1, $arr);
            $sl = @preg_match_all($reg2, $sample1, $arr);
            if (!$gl || !$sl) continue;
            $k = abs(3 - ($sl / $gl));
            $k += $c - $gl - $sl;
            if ($k < $mk) {
                $enc = $item;
                $mk = $k;
            }
        }
        return $enc;
    }

    function error($text)
    {
        die($text);
    }

    function __construct()
    {
        if (!config('app.https_login')) $this->error("Не удалось подключить конфигурационный файл.");
    }

}