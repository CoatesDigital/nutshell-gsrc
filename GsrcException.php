<?php
namespace application\plugin\gsrc
{
	use nutshell\core\exception\NutshellException;

	/**
	 * Throws exceptions down a nice little JSON packages
	 * @author Dean Rather
	 */
	class GsrcException extends NutshellException
	{
		/** When getting or setting, an object's _type metadata must match that of the table */
		const TYPE_CHECK_FAIL = 1;
		
		/** When getting an individual result, 1 result is expected. No more, No less */
		const INVALID_NUMBER_OF_RESULTS = 2;
		
		/** Any controller who extends GSRC must provide the table name in getModelName() */
		const TABLE_NAME_NOT_DEFINED = 3;
		
		/** 'type' must be one of 'select', 'insert', 'update', 'delete' */
		const QUERY_INVALID_TYPE = 4;
		
		/** Queries must have a 'type' */
		const QUERY_NEEDS_TYPE = 5;
		
		/** Queries must have a 'table' */
		const QUERY_NEEDS_TABLE = 6;
	}
}
?>