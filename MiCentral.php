<?php
/*
 * Homegear Xiaomi Smarthome V0.1 for homegear 0.7.x
 * (c) Frank Motzkau 2020
 */


$basedir = __DIR__.'/../../';
if (file_exists($basedir.'lib/Homegear/'))
{
    include $basedir.'lib/Homegear/Homegear.php';
    include $basedir.'lib/Homegear/Constants.php';

    define('FILTER_SERIAL', \Homegear\Constants\GetPeerId::Filter_Serial);
}
else
{
    define('FILTER_SERIAL', 1);
}

include_once 'MiConstants.php';
include_once 'MiGateway.php';
include_once 'MiLogger.php';
   
use parallel\{Channel,Runtime,Events,Events\Event};

class MiCentral 
{
    const FAMILY_ID = 254;  // miscellaneous device

    private $hg;
    private $_socket;
    private $_gateways = [];
    private $_eventChannel = NULL;

    public function __construct()
    {       
        $this->hg = new \Homegear\Homegear();
    }

    public function discover()
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, 0);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, true);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => MiConstants::DISCOVER_TIMEOUT, 'usec' => '0'));
        socket_sendto($socket, MiConstants::CMD_WHOIS, strlen(MiConstants::CMD_WHOIS), 0, MiConstants::MULTICAST_ADDRESS, MiConstants::SERVER_PORT);
        MiLogger::Instance()->debug_log(MiConstants::CMD_WHOIS);
        do
        {
            $data = null;
            socket_recvfrom($socket, $data, 1024, MSG_WAITALL, $from, $port);
            if (!is_null($data))
            {
                MiLogger::Instance()->debug_log($data);
                $response = json_decode($data);
                if (($response->cmd == MiConstants::ACK_IAM)
                    && ($response->model == MiConstants::MODEL_GATEWAY))
                {
                    $this->_gateways[] = new MiGateway($response);
                }
            }
        }
        while (!is_null($data));
        socket_close($socket);

        foreach ($this->_gateways as $gateway)
        {
            $gateway->get_id_list($this->hg);
            $this->createDevices($gateway);
        }
    }

    public function init(Channel $eventChannel)
    {       
        $this->_eventChannel = $eventChannel;
    }

    public function listDevices()
    {         
        echo "─────────┼───────────────────────────┼───────────────┼──────┼───────────────────────────".PHP_EOL; 
        echo "      ID │ Name                      │ Serial Number │ Type │ Type String               ".PHP_EOL;            
        echo "─────────┼───────────────────────────┼───────────────┼──────┼───────────────────────────".PHP_EOL; 
        
        foreach ($this->_gateways as $gateway)
        {
            foreach ($gateway->getDevicelist() as $sid)
            {
                if ($device = $gateway->getDevice($sid))
                {
                    $id = $device->getPeerId();
                    $config = $this->hg->getAllConfig($id);
                    $name = $config[0]['NAME'];
                    $serial = $config[0]['ADDRESS'];
                    $typeId = $config[0]['TYPE_ID'];
                    $type = $config[0]['TYPE'];
                    echo sprintf("%8d | %25s |    %10s | %4X | %s".PHP_EOL, $id, $name, $serial, $typeId, $type);
                }
            }
        }
    }

    private function createDevices($gateway)
    {
        // create gateway device
        list($address, $serial) = $gateway->encodeSid($gateway->getSid());
        $peerIds = $this->hg->getPeerId(FILTER_SERIAL, $serial);
        if (0 == count($peerIds))
        {
            $peerId = $this->hg->createDevice(MiCentral::FAMILY_ID, MiGateway::TYPE_ID, $serial, intval($address), /*protoversion*/0x0107);
            $this->hg->putParamset($peerId, 0, ['SID'=> $gateway->getSid(), 'IP' => $gateway->getIpAddress(), 'PORT' => MiConstants::MULTICAST_PORT]);            
            $gateway->setPeerId($peerId);
            if ($this->_eventChannel)
            {
                $this->_eventChannel->send(['name' => 'subscribePeer', 'value' => $peerId]);
            }
        }
        else
        {
            $gateway->setPeerId($peerIds[0]);
            $gateway->getParamset($this->hg, 0);
            if ($this->_eventChannel)
            {
                $this->_eventChannel->send(['name' => 'subscribePeer', 'value' => $peerIds[0]]);
            }
        }

        foreach ($gateway->getDevicelist() as $sid)
        {
            if ($device = $gateway->getDevice($sid))
            {
                list($address, $serial) = $gateway->encodeSid($sid);
                $peerIds = $this->hg->getPeerId(FILTER_SERIAL, $serial);
                if (0 == count($peerIds))
                {                 
                    $peerId = $this->hg->createDevice(MiCentral::FAMILY_ID, $device->getTypeId(), $serial, intval($address), /*protoversion*/0x0107);
                    $this->hg->putParamset($peerId, 0, ['SID' => $sid]);
                    $device->setPeerId($peerId);
                    
                    if ($this->_eventChannel)
                    {
                        $this->_eventChannel->send(['name' => 'subscribePeer', 'value' => $peerId]);
                    }
                }
                else
                {
                    $device->setPeerId($peerIds[0]);
                    if ($this->_eventChannel)
                    {
                        $this->_eventChannel->send(['name' => 'subscribePeer', 'value' => $peerIds[0]]);
                    }
                }
            }
        }

        $gateway->getDeviceData($this->hg);
    }

    private function updateDevice($sid, $data)
    {
        $result = FALSE;
        try
        {
            foreach ($this->_gateways as $gateway)
            {
                if ($gateway->updateDevice($this->hg, $sid, $data))
                {
                    $result = TRUE;
                }
            }
        }
        catch (\Homegear\HomegearException $e)
        {
            MiLogger::Instance()->exception_log($e);
        }
        catch (Exception $e)
        {
            MiLogger::Instance()->exception_log($e);
        }
        return $result;
    }

    public function updateEvent($data)
    {
        try
        {
            foreach ($this->_gateways as $gateway)
            {
                // Pass result to main thread
                $gateway->updateEvent($this->hg, $data);
            }
        }
        catch (\Homegear\HomegearException $e)
        {
            MiLogger::Instance()->exception_log($e);
        }
        catch (Exception $e)
        {
            MiLogger::Instance()->exception_log($e);
        }
    }

    public function updateParamset($data)
    { 
        try
        {
            foreach ($this->_gateways as $gateway)
            {
                // Pass result to main thread
                $gateway->getParamset($this->hg, $data['CHANNEL']);
            }
        }
        catch (\Homegear\HomegearException $e)
        {
            MiLogger::Instance()->exception_log($e);
        }
        catch (Exception $e)
        {
            MiLogger::Instance()->exception_log($e);
        }
    }

    public function handleData($json)
    {
        try
        {
            $log_unknown = TRUE;
            $response = json_decode($json);
            $data = json_decode($response->data);
            if (property_exists($data, 'error'))
            {
                // todo: error handling
            }
                    
            MiLogger::Instance()->debug_log($json);
            
            switch ($response->cmd)
            {
                case MiConstants::HEARTBEAT:
                case MiConstants::REPORT:
                case MiConstants::ACK_READ:
                    if ($response->model == MiConstants::MODEL_GATEWAY)
                    {
                        foreach ($this->_gateways as $gateway)
                        {
                            if ($gateway->getSid() == $response->sid)
                            {
                                $log_unknown = FALSE;
                                $gateway->updateData($this->hg, $response);
                                return TRUE;
                            }
                        }
                            
                        if ($log_unknown)
                        {
                            // this gateway is not discovered yet   
                            $log_unknown = TRUE;
                            // add the new gateway
                            $this->_gateways[] = new MiGateway($response);                                        
                            foreach ($this->_gateways as $gateway)
                            {
                                if ($gateway->getSid() == $response->sid)
                                {
                                    // read devices from gateway
                                    $log_unknown = FALSE;
                                    $gateway->get_id_list($this->hg);
                                    $this->createDevices($gateway);
                                }
                            }
                        }
                    }
                    else
                    {
                        if (FALSE !== $this->updateDevice($response->sid, $data))
                        {
                            $log_unknown = FALSE;
                        }
                    }
                    break;
                case MiConstants::ACK_WRITE:
                    // todo error handling 
                    $log_unknown = FALSE;
                    break;
            }
            
            if ($log_unknown)
            {
                $peerId = 0;
                foreach ($this->_gateways as $gateway)
                {
                    if ($peerId = $gateway->createDevice($this->hg, $response->sid))
                    {
                        if ($this->_eventChannel)
                        {
                            $this->_eventChannel->send(['name' => 'subscribePeer', 'value' => $peerId]);
                        }
                        break;
                    }
                }
                if ($peerId == 0)
                {
                    MiLogger::Instance()->unknown_log($json);
                }
            }
        }
        catch (\Homegear\HomegearException $e)
        {
            MiLogger::Instance()->exception_log($e);
        }
        catch (Exception $e)
        {
            MiLogger::Instance()->exception_log($e);
        }
    }
}
