<?php namespace oGuz;

define('DBHOST', 'localhost');
define('DBUSER', '');
define('DBPASS', '');
define('DBNAME', '');


class DB {

	/**
	 * DB Class Instance
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Database query select fields array
	 *
	 * @var array
	 */
	private $select = [];

	/**
	 * Datbase query where array
	 *
	 * @var array
	 */
	private $where = [];

	/**
	 * Datbase query or where array
	 *
	 * @var array
	 */
	private $orWhere = [];

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $from = '';

	/**
	 * table join string
	 *
	 * @var string
	 */
	private $join = '';

	/**
	 * For pagination, offset
	 *
	 * @var int
	 */
	private $offset = 0;

	/**
	 * For pagination, limit
	 *
	 * @var int
	 */
	private $limit = 25;

	/**
	 * PDO Object
	 *
	 * @var
	 */
	private $db;

	/**
	 * PDO::execute( $this->executeArray )
	 *
	 * @var array
	 */
	private $executeArray = [];

	/**
	 * This array for "order by"
	 *
	 * @var array
	 */
	private $order = [];

	/**
	 * We doing override this method for calling static type
	 *
	 * @param string $name
	 * @param array  $data
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function __callStatic( $name, $data ) {
		$_this = null;

		if( is_null( static::$instance ) ) {
			static::$instance = $_this = new DB();
			$_this->connect();
		}

		if( is_null( $_this ) ) {
			$_this = static::$instance;
		}

		$_this->refresh();

		$method = '_' . $name;

		if( ! method_exists($_this, $method) ) {
			throw new \Exception('Method bulunamadi.');
		}

		return call_user_func_array([$_this, $method], $data);
	}

	/**
	 * For adding prefix to method
	 *
	 * @param string $name
	 * @param array  $data
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __call( $name, $data ) {
		$method = '_' . $name;

		if( method_exists($this, $method) ) {
			return call_user_func_array([$this, $method], $data);
		}

		throw new \Exception('Method bulunamadi.');
	}

	/**
	 * Hello
	 *
	 * @return string
	 */
	public function _hello() {
		return 'hello';
	}

	/**
	 * Fields select
	 *
	 * ->select(['id', 'name', 'title'])
	 *
	 * @param array $select
	 *
	 * @return $this
	 */
	public function _select(array $select ) {
		$this->select = $select;

		return $this;
	}

	/**
	 * Usage: single array or multiple array
	 *
	 * ->where(['id', '=', 1])
	 * ->where([
	 *      ['id', '>', 1],
	 *      ['name', 'like', '%test%']
	 * ])
	 *
	 * @param array $where
	 *
	 * @return $this
	 */
	public function _where(array $where) {
		$this->where[] = $where;

		return $this;
	}

	/**
	 * Usage: single array or multiple array
	 *
	 * ->orWhere(['id', '=', 1])
	 * ->orWhere([
	 *      ['id', '>', 1],
	 *      ['name', 'like', '%test%']
	 * ])
	 *
	 * @param array $where
	 *
	 * @return $this
	 */
	public function _orWhere(array $where) {
		$this->orWhere[] = $where;

		return $this;
	}

	/**
	 * Table name select
	 *
	 * @param string $from
	 *
	 * @return $this
	 */
	public function _from( $from ) {
		$this->from = $from;

		return $this;
	}

	/**
	 * Table join
	 *
	 * ->join( function() {
	 *		return 'left join TABLE on TABLE.ID = JOINTABLE.ID'
	 * } )
	 *
	 * @param $callBack
	 *
	 * @return $this
	 */
	public function _join( $callBack ) {
		if( is_callable( $callBack ) ) {
			$this->join .= $callBack();
		}

		return $this;
	}

	/**
	 * Adding limit
	 *
	 * @param int $offset
	 * @param int $limit
	 *
	 * @return $this
	 */
	public function _limit($offset, $limit) {
		$this->offset = $offset;
		$this->limit = $limit;

		return $this;
	}

	/**
	 * Order the list
	 *
	 * @param string|array $field
	 * @param string $order
	 *
	 * @return $this
	 */
	public function _orderBy($field, $order = 'asc') {
		$this->order['fields'] 	= $field;
		$this->order['order'] 	= $order;

		return $this;
	}

	/**
	 * Return single row or multiple rows
	 *
	 * DB::from('table')->get()
	 * DB::from('table')->where(['id', '=', 1])->get( true )
	 *
	 * @param bool $onlyOneRow
	 *
	 * @return mixed
	 */
	public function _get( $onlyOneRow = false ) {
		if( $onlyOneRow ) {
			$this->_limit( 0, 1 );
		}

		$exec = $this->executeSql( $this->makeSelectQueryString() );

		return $onlyOneRow ? $exec->fetch() : $exec->fetchAll();
	}

	/**
	 * Insert new record
	 *
	 * @param array $data
	 *
	 * @return int
	 */
	public function _insert(array $data) {
		$sql = 'insert into ' . $this->from . ' (';

		if( count($data) > 0 ) {
			$i = 0;

			foreach ( $data as $key => $val ) {
				$sql .= $this->parseFieldName($key) . ',';
				$this->executeArray[] = $val;
				$i++;
			}

			$sql = rtrim($sql, ',');
			$sql .= ') values (' . str_repeat('?,', $i);
			$sql = rtrim($sql, ',');
			$sql .=  ')';
		}

		$insert = $this->executeSql($sql);

		return $insert ? $this->db->lastInsertId() : 0;
	}

	/**
	 * Update records
	 *
	 * @param array $data
	 *
	 * @return mixed
	 */
	public function _update(array $data) {
		$sql = 'update ' . $this->from . ' set ';

		if( count($data) > 0 ) {
			foreach ( $data as $key => $val ) {
				$sql .= $this->parseFieldName($key) . ' = ? ,';
				$this->executeArray[] = $val;
			}
			$sql = rtrim($sql, ',');
		}

		// where
		$sql .= $this->makeWhereQueryString();

		return $this->executeSql( $sql );
	}

	/**
	 * Delete records
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	public function _delete( $id = 0 ) {
		if( $id > 0 ) {
			$this->_where(['id', '=', $id]);
		}

		$sql = 'delete from ' . $this->from;
		$sql .= $this->makeWhereQueryString();

		$delete = $this->executeSql( $sql );

		return $delete ? true : false;
	}

	/**
	 * Refresh class variables
	 */
	private function refresh() {
		$this->select = $this->where = $this->order = $this->executeArray = [];
		$this->from = $this->join = '';
	}

	/**
	 * Connect to database
	 */
	private function connect() {
		try {
			$this->db = new \PDO('mysql:host=' . DBHOST .';dbname=' . DBNAME,
				DBUSER,
				DBPASS,
				[\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ]
			);
		}
		catch (\PDOException $e) {
			echo "Hata!: " . $e->getMessage() . "<br/>";
			die();
		}
	}

	/**
	 * Make sql query string
	 *
	 * @return string
	 */
	private function makeSelectQueryString() {
		$sql = 'select ';

		//select
		if( count($this->select) > 0 ) {
			foreach ( $this->select as $select ) {
				$sql .= $this->parseFieldName($select) . ',';
			}
			$sql = rtrim($sql, ',');
		}
		else {
			$sql .= '*';
		}

		// from
		$sql .= ' from ' . $this->from;

		// join
		$sql .= ' ' . $this->join . ' ';

		// where
		$sql .= $this->makeWhereQueryString();

		// order by
		if( count($this->order) > 0 ) {
			$sql .= ' order by ';

			if( is_array( $this->order['fields'] ) and count( $this->order['fields'] ) > 0 ) {
				$i = 0;
				foreach ( $this->order['fields'] as $f ) {

					$sql .= ($i === 0 ? '' : ', ') . $this->parseFieldName($f);
					$i++;

				}
			}
			else {
				$sql .= $this->parseFieldName($this->order['fields']);
			}

			$sql .= ' ' . $this->order['order'];
		}

		// limit
		$sql .= ' limit ' . $this->offset . ', ' . $this->limit;

		return $sql;
	}

	/**
	 * Make criteria string
	 *
	 * @return string
	 */
	private function makeWhereQueryString() {
		$sql = ' where ( 1=1 ';

		// where
		if( count( $this->where ) > 0 ) {
			$i = 0;
			foreach ( $this->where as $where ) {
				if( is_array( $where[0] ) ) {
					foreach ( $where as $w ) {
						$sql .= ($i === 0 ? '' : ', ') . ' and ' . $this->parseFieldName($w[0]) . ' ' . $w[1] . ' ? ';
						$this->executeArray[] = $w[2];
					}
				}
				else {
					$sql .= ($i === 0 ? '' : ', ') . ' and ' . $this->parseFieldName($where[0]) . ' ' . $where[1] . ' ? ';
					$this->executeArray[] = $where[2];
				}
				$i++;
			}
		}

		$sql .= ')';

		// orWhere
		if( count( $this->orWhere ) > 0 ) {
			$sql .= ' and (';
			$i = 0;

			foreach ( $this->orWhere as $where ) {
				if( is_array( $where[0] ) ) {
					foreach ( $where as $w ) {
						$sql .= ($i === 0 ? '' : ' or ') . $this->parseFieldName($w[0]) . ' ' . $w[1] . ' ? ';
						$this->executeArray[] = $w[2];
						$i++;
					}
				}
				else {
					$sql .= ($i === 0 ? '' : ' or ') . $this->parseFieldName($where[0]) . ' ' . $where[1] . ' ? ';
					$this->executeArray[] = $where[2];
				}
			}
			$sql .= ') ';
		}

		return $sql;
	}

	/**
	 * Execute sql string
	 *
	 * @param $sql
	 *
	 * @return mixed
	 */
	private function executeSql( $sql ) {
		try {
			$q = $this->db->prepare( $sql );
			$q->execute( $this->executeArray );

			return $q;
		}
		catch (\PDOException $e) {
			echo $e->getMessage();
			die();
		}
	}

	/**
	 * name => `name`
	 * user.name => `user`.`name`
	 *
	 * @param string $field
	 *
	 * @return string
	 */
	private function parseFieldName( $field ) {

		if( ! preg_match("/\./", $field) ) {
			return '`' . $field . '`';
		}
		else {
			$e = explode('.', $field);
			return '`' . $e[0] . '`.`' . $e[1] . '`';
		}

	}

}