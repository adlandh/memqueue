<?php
/**
 * Created by PhpStorm.
 * User: dh
 * Date: 18.07.16
 * Time: 12:56
 */

class MemQueue
{
    private $queue_name;
    private $mc;


    /**
     * @return mixed
     */
    public function getQueueName()
    {
        return $this->queue_name;
    }

    /**
     * @param mixed $queue_name
     */
    public function setQueueName($queue_name)
    {
        $this->queue_name = $queue_name;
    }

    /**
     * @param mixed $mc_servers
     * @return true or false
     */
    public function setMemcachedConn($mc_servers="127.0.0.1:11211")
    {
        try
        {
            $this->mc =  new \Memcached();
            $servers = explode(",", $mc_servers);
            foreach ($servers as $server)
            {
                list($host,$port)=explode(":",$server);
                $this->mc->addServer($host,$port);
            }
            return true;

        }
        catch (Exception $e)
        {
            echo $e->getMessage();
            $this->mc=false;
            return false;
        }

    }

    public function __construct($queue_name="memqueue", $mc_servers="127.0.0.1:11211")
    {
        if($this->setMemcachedConn($mc_servers))
            $this->setQueueName($queue_name);
    }

    /**
     * @return true or false
     */

    public function is_empty()
    {
        $head = $this->mc->get($this->queue_name."_head");
        $tail = $this->mc->get($this->queue_name."_tail");

        if($head >= $tail || $head === FALSE || $tail === FALSE)
            return TRUE;
        else
            return FALSE;
    }

    /**
     * @params mixed $val
     */

    public function push($val)
    {

    }

    /**
     * @return mixed
     */

    public function pop()
    {

    }

}