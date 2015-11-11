<?php
/**
 * Dnode Synchronous Client for PHP
 *
 * @copyright 2012 erasys GmbH - see ./LICENSE.txt for more info
 */
namespace DNodeClient;

class RpcDnodeClient {
	private $etcd_host;
	private $etcd_port;
    
    public  function __construct($etcd_host,$etcd_port){
       $this->etcd_host = $etcd_host;
       $this->etcd_port = $etcd_port;
    }

    public  function setEtcd($etcd_host,$etcd_port){
        $this->etcd_host = $etcd_host;
        $this->etcd_port = $etcd_port;        
    }
    
	       
	public function getSync($name,$params){		
		try{
			$bestHost = $this->getBestService($name);
			if($bestHost === false){
				return false;
			}

		    $dnode = new \DNodeSync\DnodeSyncClient();		    
		    $connection = $dnode->connect($bestHost['ip'], $bestHost['port']);
		    $response = $connection->call($bestHost['call_name'], $params);
		    return $response;		    
	    }catch(\Exception $e){			
		   error_log($e->getMessage());
		   return false;
		}			
	}
	
	
	public function get($name,$params,$callback){		
		try{
			$bestHost = $this->getBestService($name);
			if($bestHost === false){
				return false;
			}
			$loop = new \React\EventLoop\StreamSelectLoop();
		    $dnode = new \DNode\DNode($loop);
			$dnode->connect($bestHost['ip'],$bestHost['port'], function($remote, $connection) use($bestHost,$params,$callback) { 
				$remote->{$bestHost['call_name']}($params, function($n) use ($connection,$callback) {
					$connection->end();
					$callback($n);
				});
			});
			$loop->run();
	    }catch(\Exception $e){
			error_log($e->getMessage());
			$callback(false);	
		}			
	}
	
	private function getBestService($name){
		$services = $this->getNodes($name);
		if($services === false){
		    return false;	
		}
		$num = count($services);
		$index=0;
		if($num > 1){
			$index = mt_rand(1,$num) -1;
		}
		$bestHost=$services[$index];
		return $bestHost;
	}
	
	private function getNodes($name){
		$s = explode(".",strtolower($name));
		if(count($s) !=2){
		   return false;	
		}
		$etcd_uri = "http://{$this->etcd_host}:{$this->etcd_port}";
		$s_uri= "/services/projects/" . implode('/',$s);		
        $services = array();
	
		$client = new \LinkORB\Component\Etcd\Client($etcd_uri);
		$nodes = $client->getNode($s_uri);
		foreach($nodes['nodes'] as $host){
			$val = json_decode($host['value'],true);
			$services[] = $val;
		}
		
		
		if(empty($services)){
			return false;	
		}
		return $services;
	}
}
