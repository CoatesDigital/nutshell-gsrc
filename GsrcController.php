<?php
namespace application\plugin\gsrc
{
	use nutshell\plugin\mvc\Controller;
	use application\plugin\gsrc\GsrcException;
	use application\plugin\mvcQuery\MvcQueryObject;
	use application\plugin\mvcQuery\MvcQuery;
	
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
		 * Get / Set / Remove / Check handlers
		 */
		
		public function get($data, $options=null)
		{
			if(is_array($data))
			{
				$result = array();
				foreach($data as $block)
				{
					/*
					 * todo: make this smarter. instead of a series of:
					 * 'where a = b'
					 * 'where a = c'
					 * 'where x = y' etc.
					 * 
					 * make it:
					 * where a in (b, c) OR x in (y)  etc.
					 * 
					 */
					$result[] = $this->getIndividualRecord($block, $options);
				}
			}
			else
			{
				$result = $this->getIndividualRecord($data, $options);
			}
			
			return $result;
		}
		
		public function set($data)
		{
			if(is_array($data))
			{
				$result = array();
				foreach($data as $block)
				{
					$result[] = $this->setIndividualRecord($block);
				}
			}
			else
			{
				$result = $this->setIndividualRecord($data);
			}
			
			return $result;
		}
		
		public function remove($data)
		{
			if(is_array($data))
			{
				$result = array();
				foreach($data as $block)
				{
					$result[] = $this->removeIndividualRecord($block);
				}
			}
			else
			{
				$result = $this->removeIndividualRecord($data);
			}
			
			return $result;
		}
		
		public function check($data)
		{
			return 0; // TODO See btl plugin's data structure doc (search "check") for spec.
		}
		
		
		
		/*
		 * Handlers for individual records
		 */
		
		private function getIndividualRecord($data, $options=null)
		{
			$query = (is_array($options) && isset($options['query']) && isset($options['query']));
			
			$additionalPartSQL = '';
			if(is_array($options) && isset($options['additionalPartSQL'])) $additionalPartSQL = $options['additionalPartSQL'];
			
			$readColumns = array();
			if(is_array($options) && isset($options['readColumns'])) $readColumns = $options['readColumns'];
			
			$readColumnsRaw = (is_array($options) && isset($options['readColumnsRaw']) && $options['readColumnsRaw']);
			
			$tableName = $this->getTableName();
			if(!$query) $this->performTypeCheck($data, $tableName);
			
			$queryObject = new MvcQueryObject();
			$queryObject->setType('select');
			$queryObject->setTable($tableName);
			$queryObject->setWhere($data);
			$queryObject->setAdditionalPartSQL($additionalPartSQL);
			$queryObject->setReadColumns($readColumns);
			$queryObject->setReadColumnsRaw($readColumnsRaw);
			$queryObject->setLoose($query);
			
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
			
			return $result; 
		}
		
		private function setIndividualRecord($data)
		{
			$tableName = $this->getTableName();
			$this->performTypeCheck($data, $tableName);
			
			$queryType = 'insert';
			if(isset($data->id) && $data->id) $queryType='update';
			
			$queryObject = new MvcQueryObject();
			$queryObject->setType($queryType);
			$queryObject->setTable($tableName);
			$queryObject->setWhere($data);
			
			$result = $this->plugin->MvcQuery->query($queryObject);
			
			// Get the full row, return that.
			if($queryType=='insert')
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
		
		private function removeIndividualRecord($data)
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
		
		private function performTypeCheck($data, $modelName)
		{
			if(!isset($data->_type)) throw new GsrcException(GsrcException::TYPE_CHECK_FAIL, 'type not defined');
			
			// If the model name has any number of backslashes, just get the last part
			$modelName = explode('\\', $modelName);
			$modelName = $modelName[sizeof($modelName)-1];
			
			if(strtolower($modelName) !== strtolower($data->_type)) throw new GsrcException(GsrcException::TYPE_CHECK_FAIL, "[$data->_type] is not [$modelName]");
		}
	}
}
?>