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
	use nutshell\plugin\db\exception\DbException;
	
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
		 * You must return the modelName
		 */
		abstract function getModelName();
		
		
		
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
					$result[] = $this->getIndividualRecord($individualRecord, $request);
				}
			}
			else
			{
				$result = $this->getIndividualRecord($data, $request);
			}
			
			return $result;
		}

		protected function onBeforeGet(&$data, &$request)
		{
			// Do nothing
		}

		protected function onAfterGet(&$data)
		{
			// Do nothing
		}

		protected function configureReadFields(&$queryObject)
		{
			// Do nothing
		}
		
		protected function getIndividualRecord($data, $request=null)
		{
			// Trigger a get hook
			$this->onBeforeGet($data, $request);

			if(!$request) $request = new BtlRequestObject();
			
			$model = $this->getModel();
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
					
					if($val===null)
					{
						$data[$key] = array(MvcQueryObjectData::IS => null);
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
			$queryObject->setModel($model);
			$queryObject->setWhere($data);
			$this->configureReadFields($queryObject);
			
			$result = $this->plugin->MvcQuery->query($queryObject);
			
			if($query)
			{
				// Trigger a get hook
				foreach($result as $i=>$record)
				{
					$this->onAfterGet($record);
					$result[$i] = $record;
				}
			}
			else
			{
				//TODO What do we want to do if there are no results?
				if(sizeof($result) == 0)
				{
					// Trigger a get hook
					$this->onAfterGet($result);
					return false;
				}
				if(sizeof($result) != 1)
				{ 
					throw new GsrcException
					(
						GsrcException::INVALID_NUMBER_OF_RESULTS,
						'RESULT:',
						$result,
						'LAST QUERY:',
						$this->plugin->MvcQuery->db->getLastQueryObject()
					);
				}
				
				$result = $result[0];
				$result['_type'] = $data->_type;
			}

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
					$result[] = $this->setIndividualRecord($individualRecord, $request);
				}
			}
			else
			{
				$result = $this->setIndividualRecord($data, $request);
			}
			
			return $result;
		}

		protected function onBeforeSet(&$data)
		{
			// Do nothing
		}

		protected function onAfterSet(&$data)
		{
			// Do nothing
		}
		
		protected function setIndividualRecord($data, $request)
		{
			// Trigger a set hook
			$this->onBeforeSet($data);

			// Some sanity checking
			$tableName = $this->getTableName();
			$this->performTypeCheck($data, $tableName);
			
			// Create the Query Object
			$model = $this->getModel();
			$queryObject = new MvcQueryObject($data);
			$queryObject->setModel($model);
			$queryObject->setWhere($data);
			
			// Insert or Update
			if(isset($data->id) && $data->id)
			{
				$queryObject->setType('update');
			}
			else
			{
				$queryObject->setType('insert');
			}
			
			
			// Set It
			$result = $this->plugin->MvcQuery->query($queryObject);

			// Trigger a set hook
			$this->onAfterSet($data);			
			
			
			$newRequestData = new MvcQueryObject();
			$newRequestData->_type = $tableName;
			if($queryObject->getType()=='insert')
			{
				$newRequestData->id = $result;
			}
			else
			{
				$newRequestData->id = $data->id;
			}
			
			// return .get()
			$request = new BtlRequestObject();
			$request->setQuery($newRequestData);
			$response = $this->get($request);
			if($response && is_array($response) && sizeof($response))
			{
				$response = $response[0];
			}
			return $response;
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
				$result = $this->removeIndividualRecord($data);
			}
			
			return $result;
		}

		protected function onBeforeRemove(&$data)
		{
			// Do nothing
		}

		protected function onAfterRemove(&$data)
		{
			// Do nothing
		}
		
		protected function removeIndividualRecord($data)
		{
			// Trigger a remove hook
			$this->onBeforeRemove($data);

			$tableName = $this->getTableName();
			$model = $this->getModel();
			$this->performTypeCheck($data, $tableName);
			
			$queryObject = new MvcQueryObject();
			$queryObject->setType('delete');
			$queryObject->setModel($model);
			$queryObject->setWhere($data);
			
			try
			{
				$affected = $this->plugin->MvcQuery->query($queryObject);
				$data->_affected = $affected;
			}
			catch(DbException $e)
			{
				$data->_affected = 0;
			}
			
			// Trigger a remove hook
			$this->onAfterRemove($data);

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
			$model = $this->getModel();
			$tableName = $model->name;
			if(isset($model->originalName)) $tableName = $model->originalName;
			if(!isset($data->_type)) throw new GsrcException(GsrcException::TYPE_CHECK_FAIL, 'type not defined');
			if($tableName !== $data->_type) throw new GsrcException(GsrcException::TYPE_CHECK_FAIL, "[$data->_type] is not [$tableName]");
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

		protected function debug()
		{
			var_dump($this->plugin->MvcQuery->db->debug());
		}
		
		/**
		 * Takes the modelName and returns the actual model
		 */
		public function getModel()
		{
			return $this->plugin->MvcQuery->getModel($this->getModelName());
		}
		
		public function getTableName()
		{
			$model = $this->getModel();
			return $model->name;
		}
		
		protected function parseOptions($results, $aggregateColumns)
		{
			$tableName = $this->getTableName();
			
			$return = array();
			foreach($results as $record)
			{
				if(!isset($record['id'])) return $results;
				$id = $record['id'];
				foreach($aggregateColumns as $aggregateColumnName => $aggregateColumnQuery)
				{
					if($record['_has'.$aggregateColumnName])
					{
						$record[$aggregateColumnName] = $this->getResultFromQuery($aggregateColumnQuery, $id);
					}
					else
					{
						$record[$aggregateColumnName] = array();
					}
				}
				$return[] = $record;
			}
			
			return $return;
		}
	}
}
