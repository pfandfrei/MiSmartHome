<?php
/*
 * Homegear Xiaomi Smarthome for Homegear with PHP 7.4
 * (c) Frank Motzkau 2020
 */

$basedir = __DIR__.'/../../';
if (file_exists($basedir.'lib/Homegear/'))
{
    include $basedir.'lib/Homegear/Homegear.php';
}
include_once "MiCentral.php";
include_once "MiLogger.php";

use parallel\{Channel,Runtime,Events,Events\Event};

$peerId = (integer) $argv[0];
if (!$peerId)
{
    echo "#### MiSmartHome for Homegear 0.7.x ####\r\n";
    echo "#### auto-discovering devices ...   ####\r\n";

    $hg = new \Homegear\Homegear();
    
    $central = new MiCentral();
    $central->discover();
    echo PHP_EOL.PHP_EOL."found the following devices:".PHP_EOL;
    $central->listDevices();
    echo PHP_EOL."Installation completed!".PHP_EOL.PHP_EOL;
    exit(0);
}

$suffix = bin2hex(random_bytes(4));
$eventChannelId = "eventeventChannel-".$suffix;
$listenerChannelId = "listenerChannel-".$suffix;
$workerChannelId = "workererChannel-".$suffix;

$eventThread = function(string $scriptId, int $peerId, Channel $eventChannel, Channel $workerChannel)
{
    $basedir = __DIR__.'/../../';
    if (file_exists($basedir.'lib/Homegear/'))
    {
        include $basedir.'lib/Homegear/Homegear.php';
    }

    $hg = new \Homegear\Homegear();
    if ($hg->registerThread($scriptId) === false)
    {
        $hg->log(2, 'Could not register thread.');
        return false;
    }
    $hg->subscribePeer($peerId);

    $events = new Events();
    $events->addChannel($eventChannel);
    $events->setBlocking(false);
    while (true)
    {
        $result = $hg->pollEvent();
        if (is_array($result))
        {
            if ( array_key_exists('TYPE', $result))
            {
                if ($result['TYPE'] == 'event')
                {
                    $workerChannel->send(['name' => 'updateEvent', 'value' => $result]);
                }
                else if ($result['TYPE'] == 'updateDevice')
                {
                    $workerChannel->send(['name' => 'updateDevice', 'value' => $result]);
                }
            }
        }
       
        try
        {
            $event = NULL;
            do
            {                
                $event = $events->poll();
                if ($event)
                {
                    $events->addChannel($eventChannel);
                    $action = $event->value['name'];
                    if ($event->type == Event\Type::Close)
                    {
                        return true; //Stop
                    }
                    else if ($event->type == Event\Type::Read)
                    { 
                        $action = $event->value['name'];
                        switch ($action)
                        {
                            case 'stop':
                            {          
                                 return true; //Stop
                            }
                            case 'subscribePeer':
                            {
                                $peerId = $event->value['value'];
                                $hg->subscribePeer((integer)$peerId);
                                break;
                            }
                        }
                    }
                }
            }
            while ($event);
        }
        catch (Events\Error\Timeout $ex)
        {
        }
    }
};

$listenerThread = function(string $scriptId, Channel $listenerChannel, Channel $workerChannel)
{
    $basedir = __DIR__.'/../../';
    if (file_exists($basedir.'lib/Homegear/'))
    {
        include $basedir.'lib/Homegear/Homegear.php';
    }
    include_once "MiConstants.php";
    include_once "MiLogger.php";

    if (FALSE === ($socket_recv = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
    {
        die("$errstr ($errno)");
    }
    $res = socket_set_option($socket_recv, IPPROTO_IP, MCAST_JOIN_GROUP, array('group' => MiConstants::MULTICAST_ADDRESS, 'interface' => 0));
    $res = @socket_bind($socket_recv, '0.0.0.0', MiConstants::MULTICAST_PORT);
    if ($res===FALSE) 
    {
        // socket is used by another gateway => stop thread and notify worker
        $workerChannel->send(['name' => 'stop', 'value' => true]);
        return true;
    }

    $hg = new \Homegear\Homegear();
    if ($hg->registerThread($scriptId) === false)
    {
        $hg->log(2, 'Could not register thread.');
        return false;
    }

    $hg = new \Homegear\Homegear();
    
    do
    {
        try
        {
            $json = null;
            socket_recvfrom($socket_recv, $json, 1024, MSG_WAITALL, $from, $port);
            if (!is_null($json))
            {
                $workerChannel->send(['name' => 'handleData', 'value' => $json]);
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
    while (TRUE);
};

$workerThread = function(string $scriptId, Channel $workerChannel, Channel $eventChannel)
{
    $basedir = __DIR__.'/../../';
    if (file_exists($basedir.'lib/Homegear/'))
    {
        include $basedir.'lib/Homegear/Homegear.php';
    }
    include "MiCentral.php";

    $hg = new \Homegear\Homegear();
    if ($hg->registerThread($scriptId) === false)
    {
        $hg->log(2, 'Could not register thread.');
        return false;
    }

    $central = new MiCentral();
    $central->discover();
    $central->init($eventChannel);

    $events = new Events();
    $events->addChannel($workerChannel);
    $events->setBlocking(true);
    //$events->setTimeout(1000000);
    while (true)
    {
        try
        {            
            $event = NULL;
            do
            {
                $event = $events->poll();
                if ($event)
                {
                    $events->addChannel($workerChannel);
                    if ($event->type == Event\Type::Close)
                    {
                        return true; //Stop
                    }
                    else if ($event->type == Event\Type::Read)
                    {
                        $action = $event->value['name'];
                        switch ($action)
                        {
                            case 'stop':
                            {               
                                // notify event thread
                                $eventChannel->send(['name' => 'stop', 'value' => true]);
                                return true; //Stop
                            }
                            case 'handleData':
                            {
                                $data = $event->value['value'];
                                $central->handleData($data);
                                break;
                            }      
                            case 'updateEvent':
                            { 
                                $data = $event->value['value'];
                                $central->updateEvent($data);
                                break;
                            }                       
                            case 'updateDevice':
                            { 
                                $data = $event->value['value'];
                                $central->updateParamset($data);
                                break;
                            }                    
                        }
                    }
                }
            }
            while ($event);
        }
        catch (Events\Error\Timeout $ex)
        {
        }
    }
    return true;
};

class HomegearDevice extends HomegearDeviceBase
{
    private $hg = NULL;
    private $peerId = NULL;
    private $eventRuntime = NULL;
    private $eventFuture = NULL;
    private $eventChannel = NULL;
    private $listenerRuntime = NULL;
    private $listenerFuture = NULL;
    private $listenerChannel = NULL;
    private $workerRuntime = NULL;
    private $workerFuture = NULL;
    private $workerChannel = NULL;

    public function __construct()
    {
        $this->hg = new \Homegear\Homegear();
    }

    public function __destruct()
    {
        $this->stop();
        $this->waitForStop();
    }

    public function init($peerId): bool
    {
        $this->peerId = $peerId;
        return true;
    }

    public function start(): bool
    {
        global $eventChannelId;
        global $listenerChannelId;
        global $workerChannelId;
        global $eventThread;
        global $listenerThread;
        global $workerThread;

        $this->eventChannel = Channel::make($eventChannelId, Channel::Infinite);
        $this->listenerChannel = Channel::make($listenerChannelId, Channel::Infinite);
        $this->workerChannel = Channel::make($workerChannelId, Channel::Infinite);

        $this->eventRuntime = new Runtime();
        $this->eventFuture = $this->eventRuntime->run($eventThread, [$this->hg->getScriptId(), $this->peerId, $this->eventChannel, $this->workerChannel]);

        $this->workerRuntime = new Runtime();
        $this->workerFuture = $this->workerRuntime->run($workerThread, [$this->hg->getScriptId(), $this->workerChannel, $this->eventChannel]);

        $this->listenerRuntime = new Runtime();
        $this->listenerFuture = $this->listenerRuntime->run($listenerThread, [$this->hg->getScriptId(), $this->listenerChannel, $this->workerChannel]);

        return true;
    }

    public function stop()
    {
        if ($this->listenerChannel)
            $this->listenerChannel->send(['name' => 'stop', 'value' => true]);
        if ($this->eventChannel)
            $this->eventChannel->send(['name' => 'stop', 'value' => true]);
        if ($this->workerChannel)
        {
            $this->workerChannel->send(['name' => 'stop', 'value' => true]);
        }
    }

    public function waitForStop()
    {
        if ($this->listenerFuture)
        {
            $this->listenerFuture->value();
            $this->listenerFuture = NULL;
        }
        if ($this->eventFuture)
        {
            $this->eventFuture->value();
            $this->eventFuture = NULL;
        }
        if ($this->workerFuture)
        {
            $this->workerFuture->value();
            $this->workerFuture = NULL;
        }
        if ($this->listenerChannel)
        {
            $this->listenerChannel->close();
            $this->listenerChannel = NULL;
        }
        if ($this->eventChannel)
        {
            $this->eventChannel->close();
            $this->eventChannel = NULL;
        }
        if ($this->workerChannel)
        {
            $this->workerChannel->close();
            $this->workerChannel = NULL;
        }
        if ($this->listenerRuntime)
        {
            $this->listenerRuntime->close();
            $this->listenerRuntime = NULL;
        }
        if ($this->eventRuntime)
        {
            $this->eventRuntime->close();
            $this->eventRuntime = NULL;
        }
        if ($this->workerRuntime)
        {
            $this->workerRuntime->close();
            $this->workerRuntime = NULL;
        }
    }
}


$mainDevice = new HomegearDevice();
$mainDevice->init($peerId);
$mainDevice->start();
$mainDevice->waitForStop();