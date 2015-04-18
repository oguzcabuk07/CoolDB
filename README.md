# CoolDB

Mysql class width PDO

### Usage

```PHP
use oGuz\DB AS DB;

/*
*  Multiple rows
*/

$result = DB::select(['u.name', 'u.email', 'p.post'])
		->from('users AS u')
		->join( function() {
			return 'left join posts AS p on p.user_id = u.id';
		} )
		->where(['u.id', '>', 0])
		->limit(0, 2)
		->orderBy('u.name', 'asc')
		->get();

```


```PHP

/*
* Single row
* ->get( true )
*/

$row = DB::from('users')->where(['id', '=', 1])->get( true );

```


```PHP

$insertID = DB::from('users')->insert([
		'name' => 'user name',
		'email' => 'test@testuser.com'
	]);

```

```PHP

DB::from('users')
	->where(['id', '=', 1])
	->update([
		'name' => 'user name',
        'email' => 'test@testuser.com'
	]);

```

```PHP

$delete = DB::from('users')->delete( $id = 1 );

$delete = DB::from('users')->where(['name', '=', 'test'])->delete();

```