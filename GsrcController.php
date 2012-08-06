<?php
namespace application\plugin\gsrc
{
	use nutshell\plugin\mvc\Controller;
	use application\plugin\gsrc\GsrcException;
	use application\plugin\mvcQuery\MvcQuery;
	use application\plugin\mvcQuery\MvcQueryObject;
	use application\plugin\mvcQuery\MvcQueryObjectData;
	use application\plugin\btl\BtlRequestObject;
	use nutshell\Nutshell;
	
	// Nutshell::getInstance()->plugin->Gsrc;
	
	
	/**
	 * Provides Get / Set / Remove / Check functionality.
	 * Similar to CRUD, except that Set does both Create and Update (depending on whether an 'id' is defined
	 * Also it provides a "Check" ability to poll for changes
	 * @author Dean Rather
	 */
	abstract class GsrcController extends Controller
	{
		/**
		 * You must return the tableName
		 */
		abstract function getTableName();
		
		
		
		/*
		 * GET
		 */
		
		public function get($request)
		{
			$data = $request->getData();
			if(is_array($data))
			{
				$result = array();
				foreach($data as $individualRecord)
				{
					$result[] = $this->getIndividualRecord($individualRecord);
				}
			}
			else
			{
				$result = $this->getIndividualRecord($data, $request);
			}
			
			return $result;
		}
		
		private function getIndividualRecord($data, $request=null)
		{
			if(!$request) $request = new BtlRequestObject();
			
			$tableName = $this->getTableName();
			
			$query = $request->getQuery();
			if($query)
			{
				$data = array();
				foreach($query as $key=>$val)
				{
					if($key[0] == '_')
					{
						$data[$key] = $val;
					}
					else
					{
						$data[$key] = array(MvcQueryObjectData::LIKE => $val);
					}
				}
			}
			elseif($data)
			{
				$this->performTypeCheck($data, $tableName);
			}
			else
			{
				// they passed in neither a 'data' nor a 'query' part, assume it's a query for everything
				$query = true;
			}
			
			$queryObject = new MvcQueryObject($query);
			$queryObject->setType('select');
			$queryObject->setTable($tableName);
			$queryObject->setWhere($data);
			
			$result = $this->plugin->MvcQuery->query($queryObject);
			
			if(!$query) {
				//TODO What do we want to do if there are no results?
				if(sizeof($result) == 0) return false;
				if(sizeof($result) != 1) throw new GsrcException
				(
					GsrcException::INVALID_NUMBER_OF_RESULTS,
					'RESULT:',
					$result,
					'LAST QUERY:',
					$this->plugin->MvcQuery->db->getLastQueryObject()
				);
				
				$result = $result[0];
				$result['_type'] = $data->_type;
			}
			
			// $result['_query'] = $this->plugin->MvcQuery->db->getLastQueryObject();
			// $result['_query'] = $result['_query']['sql'];
			return $result; 
		}
		
		
		
		/*
		 * SET
		 */
		
		public function set($request)
		{
			$data = $request->getData();
			if(is_array($data))
			{
				$result = array();
				foreach($data as $individualRecord)
				{
					$result[] = $this->setIndividualRecord($individualRecord);
				}
			}
			else
			{
				$result = $this->setIndividualRecord($data, $request);
			}
			
			return $result;
		}
		
		private function setIndividualRecord($data, $request=null)
		{
			if(!$request) $request = new BtlRequestObject();
			
			$tableName = $this->getTableName();
			$this->performTypeCheck($data, $tableName);
			
			$queryObject = new MvcQueryObject($data);
			$queryObject->setTable($tableName);
			$queryObject->setWhere($data);
			if(isset($data->id) && $data->id)
			{
				$queryObject->setType('update');
			}
			else
			{
				$queryObject->setType('insert');
			}
			
			$result = $this->plugin->MvcQuery->query($queryObject);
			
			if($queryObject->getType()=='insert')
			{
				$data->id = $result;
			}
			
			// Create a dummy request object which is the same object we would have gotten from json_decoding a 'get' request
			$record = new \stdClass();
			$record->_type = $data->_type;
			$record->id = $data->id;
			
			$result = $this->getIndividualRecord($record);
			return $result;
		}
		
		
		/*
		 * REMOVE
		 */
		
		public function remove($request)
		{
			$data = $request->getData();
			if(is_array($data))
			{
				$result = array();
				foreach($data as $individualRecord)
				{
					$result[] = $this->removeIndividualRecord($individualRecord);
				}
			}
			else
			{
				$result = $this->removeIndividualRecord($data, $request);
			}
			
			return $result;
		}
		
		private function removeIndividualRecord($data, $request=null)
		{
			$tableName = $this->getTableName();
			$this->performTypeCheck($data, $tableName);
			
			$queryObject = new MvcQueryObject();
			$queryObject->setType('delete');
			$queryObject->setTable($tableName);
			$queryObject->setWhere($data);
			
			$affected = $this->plugin->MvcQuery->query($queryObject);
			
			$data->_affected = $affected;
			$result = (array)$data;
			
			return $result; 
		}
		
		
		
		/*
		 * CHECK
		 */
		
		
		public function check($data)
		{
			return 0; // TODO See btl plugin's data structure doc (search "check") for spec.
		}
		
		
		
		/*
		 * UTILITY FUNCTIONS
		 */
		
		private function performTypeCheck($data, $modelName)
		{
			
			if(!isset($data->_type)) throw new GsrcException(GsrcException::TYPE_CHECK_FAIL, 'type not defined');
			
			// If the model name has any number of backslashes, just get the last part
			$modelName = explode('\\', $modelName);
			$modelName = $modelName[sizeof($modelName)-1];
			
			if(strtolower($modelName) !== strtolower($data->_type)) throw new GsrcException(GsrcException::TYPE_CHECK_FAIL, "[$data->_type] is not [$modelName]");
		}
		
		/*
		 * A Helper function to make calling a query directly a little easier.
		 */
		protected function getResultFromQuery(/* query, list of vals */)
		{
			return call_user_func_array
			(
				array($this->plugin->MvcQuery->db, 'getResultFromQuery'),
				func_get_args()
			);
		}
	}
}
