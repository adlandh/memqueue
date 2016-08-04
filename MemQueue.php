<?php
/**
 * Created by PhpStorm.
 * User: dh
 * Date: 18.07.16
 * Time: 12:56
 */

namespace MemQueue;

class MemQueue
{
    private $queue_name;
    private $mc;
    private $error_code;
    private $error_line;

    const QUERY_IS_BUSY=1001;
    const SUCCESS=1000;
    const NOT_SUCCESS=0;
    const MEMCACHED_ERROR=1002;
    const EMPTY_VALUE=1003;
    const QUERY_IS_EMPTY=1004;
    const NOT_FOUND=1005;


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
     * @return mixed
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
            $this->error_line = __LINE__;
            $this->error_code = self::MEMCACHED_ERROR;
            return false;
        }

    }


    public function __construct($queue_name="memqueue", $mc_servers="127.0.0.1:11211")
    {
        if($this->setMemcachedConn($mc_servers))
            $this->setQueueName($queue_name);
    }

    /**
     * @params mixed $val
     * @return mixed
     */

    public function push($val)
    {
        if(!$this->mc)
        {
            $this->error_line = __LINE__;
            $this->error_code = self::MEMCACHED_ERROR;
            return false;
        }

        if(empty($val))
        {
            $this->error_line = __LINE__;
            $this->error_code = self::EMPTY_VALUE;
            return false;
        }

        $sem=$this->getSem();
        if($sem==1)
        {
            $this->error_line = __LINE__;
            $this->error_code = self::QUERY_IS_BUSY;
            return false;
        }
        elseif($sem===false)
        {
            $this->error_line = __LINE__;
            return false;
        }

        if(!$this->setSem(1))
        {
            return false;
        }

        $key=$this->getKey();
        if($key===false)
        {
            $this->error_line = __LINE__;
            return false;
        }

        $key++;

        if(!$this->setKey($key))
        {
            $this->error_line = __LINE__;
            return false;
        }

        if(!$this->setVal($key,$val))
        {
            $this->error_line = __LINE__;
            return false;
        }
        if(!$this->setSem(0))
        {
            $this->error_line = __LINE__;
            return false;
        }

        $this->error_code = self::SUCCESS;
        return true;

    }

    /**
     * @return mixed
     */

    public function pop()
    {
        if(!$this->mc)
        {
            $this->error_line = __LINE__;
            $this->error_code = self::MEMCACHED_ERROR;
            return false;
        }

        $sem=$this->getSem();
        if($sem===false)
        {
            return false;
        }
        if($sem==1)
        {
            $this->error_line = __LINE__;
            $this->error_code = self::QUERY_IS_BUSY;
            return false;
        }

        if(!$this->setSem(1))
        {
            $this->error_line = __LINE__;
            return false;
        }

        $key=$this->getKey();
        if($key===false)
        {
            $this->error_line = __LINE__;
            return false;
        }
        elseif($key==0)
        {
            $this->error_line = __LINE__;
            $this->error_code = self::QUERY_IS_EMPTY;
            $this->setSem(0);
            return false;
        }
        $key--;
        if(!$this->setKey($key))
        {
            $this->error_line = __LINE__;
            return false;
        }

        if(($val=$this->getElem("1"))===FALSE)
        {
            if ($this->error_code != self::SUCCESS)
            {
                $this->error_line = __LINE__;
                return false;
            }
        }

        for($tmp_key=1;$tmp_key<=$key;$tmp_key++)
        {
            if(($tmp_val=$this->getElem($tmp_key+1))===FALSE)
            {
                if ($this->error_code != self::SUCCESS)
                {
                    $this->error_line = __LINE__;
                    return false;
                }
            }

            if(!$this->setElem($tmp_key,$tmp_val))
            {
                $this->error_line = __LINE__;
                return false;
            }
         }

        if(!$this->setSem(0))
        {
            $this->error_line = __LINE__;
            return false;
        }

        $this->error_code = self::SUCCESS;
        return $val;
    }

    /**
     * @return mixed
     */

    public function getLastError()
    {
       return $this->error_code;
    }

    /**
     * @return mixed
     */

    public function getLastErrorLine()
    {
        return $this->error_line;
    }

    /**
     * @return mixed
     */

    private function getSem()
    {
        return $this->getVal("sem");
    }

    /**
     * @params $val 1 || 0
     * @return mixed
     */

    private function setSem($val)
    {
        return $this->setVal("sem",$val,1);
    }

    /**
     * @return mixed
     */

    private function getKey()
    {
        return $this->getVal("key");
    }

    /**
     * @return mixed
     */

    private function setKey($val)
    {
        return $this->setVal("key",$val);
    }

    /**
     * @return mixed
     */

    private function getVal($key)
    {
        if(!($val=$this->mc->get($this->queue_name."_".$key)))
        {
            if ($this->mc->getResultCode() != \Memcached::RES_NOTFOUND && $this->mc->getResultCode()!= \Memcached::RES_SUCCESS)
            {
                $this->error_code = self::MEMCACHED_ERROR;
                return false;
            }
            else
            {
                $this->error_code = self::SUCCESS;
                return 0;
            }
        }
        return $val;
    }


    private function setVal($key,$val,$ttl=0)
    {
        if(!$this->mc->set($this->queue_name."_".$key,$val,$ttl))
        {
            $this->error_code = self::MEMCACHED_ERROR;
            return false;
        }

        return true;
    }

    private function getElem($key)
    {
        if(!($val=$this->mc->get($this->queue_name."_".$key)))
        {
            if ($this->mc->getResultCode() != \Memcached::RES_NOTFOUND && $this->mc->getResultCode()!= \Memcached::RES_SUCCESS)
            {
                $this->error_code = self::MEMCACHED_ERROR;
                return false;
            }
            else
            {
                $this->error_code = self::SUCCESS;
                return false;
            }
        }
        return $val;
    }

    private function setElem($key,$val)
    {
        return $this->setVal($key,$val);
    }


}