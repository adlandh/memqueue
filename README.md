# memqueue
Simple Memcached based queue

#Usage

require_once("MemQueue.php");

$mq=new MemQueue\MemQueue("my_queue"); //prefix for queue

$mq->push("test1");
$mq->push("test2");
$mq->push("test3");

while($res=$mq->pop())
{
 echo $res;
}

// test1test2test3

